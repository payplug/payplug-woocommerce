<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommercePaymentRepository;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\InvalidOperationDataException;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;
use PayplugUnifiedCore\Exceptions\PayplugException;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;
use PayplugUnifiedCore\Utilities\Helpers\ExecCodeMapper;

/**
 * Closes the gap Front\UpcWebhook's own docblock names as its one real weakness: the webhook is
 * the sole *asynchronous* signal that can resolve a 3DS-pending order, so a misconfigured or
 * blocked Receiver leaves such an order stuck forever, with no path to recovery. The shopper's own
 * return from the bank's challenge to the order-received page is a second, synchronous
 * opportunity this module already controls - reconcile() re-fetches the payment's current state
 * directly and applies it, rather than leaving the order to depend entirely on a webhook that may
 * never arrive.
 */
class PaymentReconciler
{
    /**
     * Same lock key format and TTL as Front\UpcWebhook - deliberately, so a webhook delivery and
     * a reconciliation racing for the same operation contend for the exact same lock, and
     * whichever wins finds the other's markTreated() already applied via isTreated() below.
     */
    private const LOCK_TTL_SECONDS = 30;

    /**
     * The order-received page re-fires reconcile() on every load with no other backoff - a
     * shopper stuck on a pending order who refreshes repeatedly would otherwise hammer the
     * Unified API with an OAuth token fetch plus getPayment() (up to ~20s of synchronous calls,
     * WpUnifiedApiHttpClient's 10s timeout each) on every single request.
     */
    private const RECONCILE_THROTTLE_SECONDS = 60;

    public function reconcile(\WC_Order $order): void
    {
        if (PaymentOutcome::THREE_DS_PENDING !== $order->get_meta('_payplug_upc_outcome')) {
            // Either never went through the UPC hosted-fields flow, or already resolved -
            // nothing to reconcile either way.
            return;
        }

        $operation_id = (string) $order->get_meta('_payplug_upc_operation_id');

        if ('' === $operation_id) {
            return;
        }

        $throttle_key = 'payplug_upc_reconcile_' . $order->get_id();

        if (false !== get_transient($throttle_key)) {
            return;
        }

        set_transient($throttle_key, '1', self::RECONCILE_THROTTLE_SECONDS);

        try {
            $response = (new UnifiedApiPaymentServiceFactory())->create()->getPayment($operation_id);
        } catch (PayplugException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC payment reconciliation failed for order #%s: %s', $order->get_id(), $e->getMessage()));

            return;
        }

        $this->apply_reconciled_state($order, $operation_id, $response['body']);
    }

    /**
     * Split out from reconcile() so the outcome-mapping/persistence logic - the part worth unit
     * testing - doesn't need a real or mocked HTTP round trip to exercise, the same way
     * PaymentCaptureOutcomeApplier::apply() takes an already-built PaymentOutput rather than
     * calling the API itself.
     */
    public function apply_reconciled_state(\WC_Order $order, string $operation_id, string $response_body): void
    {
        $data = json_decode($response_body, true);
        $data = is_array($data) ? $data : [];
        $exec_code = isset($data['execCode']) && is_scalar($data['execCode']) ? (string) $data['execCode'] : '';

        if ('' === $exec_code) {
            return;
        }

        $outcome = ExecCodeMapper::toPaymentOutcome($exec_code);

        if (PaymentOutcome::THREE_DS_PENDING === $outcome) {
            // Still genuinely pending - nothing new to apply, the webhook remains the eventual
            // source of truth.
            return;
        }

        // Same amount check as Front\UpcWebhook::receive_notification(): the order's total can
        // change between the 3DS-pending payment's creation and the shopper landing back here
        // (a line item removed, a stock-out cancelling part of the order) - completing it anyway
        // would apply an outcome for an amount that no longer matches what the order now owes.
        $amount = isset($data['amount']) && is_scalar($data['amount']) ? (int) $data['amount'] : null;

        if (null === $amount || AmountHelper::toCents((float) $order->get_total()) !== $amount) {
            (new WooCommerceLogger())->error(sprintf('UPC payment reconciliation amount mismatch for order #%s.', $order->get_id()));

            return;
        }

        // Same lock + isTreated() guard as Front\UpcWebhook::receive_notification(): without it, a
        // webhook delivery landing at the same moment as this reconciliation could have both read
        // the order as not-yet-treated, both call the mutator, and both fire
        // 'woocommerce_payment_complete' - duplicate emails/accounting-connector calls/subscription
        // activations for the same order.
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_treat_' . $operation_id;

        if (!$lock->acquire($lock_key, self::LOCK_TTL_SECONDS)) {
            // Contended: a webhook delivery (or another reconciliation) is already handling this
            // exact operation.
            return;
        }

        $payment_repository = new WooCommercePaymentRepository();

        try {
            if ($payment_repository->isTreated($operation_id)) {
                return;
            }

            $payment_repository->save(new OperationData(
                $operation_id,
                $exec_code,
                $outcome,
                AmountHelper::toCents((float) $order->get_total()),
                (string) $order->get_id()
            ));

            (new WooCommerceOrderStateMutator())->apply((string) $order->get_id(), $outcome);

            if (PaymentOutcome::PAID === $outcome) {
                $this->maybe_persist_uhf_card($order, $operation_id);
            }

            $payment_repository->markTreated($operation_id);
        } catch (InvalidOperationDataException $e) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment reconciliation built invalid operation data for order #%s: %s',
                $order->get_id(),
                $e->getMessage()
            ));
        } catch (PaymentNotFoundException $e) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment reconciliation could not persist operation "%s" for order #%s: %s',
                $operation_id,
                $order->get_id(),
                $e->getMessage()
            ));
        } finally {
            $lock->release($lock_key);
        }
    }

    /**
     * Closes the gap this class would otherwise leave in Front\UpcWebhook::maybe_persist_uhf_card():
     * without this, a reconciliation that wins the race against the webhook (marks the operation
     * treated first) would silently drop the shopper's save-card intent forever, since the
     * webhook's own isTreated() guard then skips its own card-persistence call entirely once it
     * does arrive. getPayment()'s response body (used above for the outcome/amount check) is not
     * confirmed to carry the same paymentMethod.{id, card, details} shape the webhook and
     * getOperation() both do (per the UHF spec: "la réponse de l'endpoint public d'opération a la
     * même forme que la notification") - so this fetches the operation resource specifically,
     * mirroring PaymentCaptureOutcomeApplier's own best-effort card-metadata fetch. Never fails
     * the reconciliation itself: a fetch failure here only means the card isn't saved this time,
     * with the webhook remaining a second chance if it eventually arrives.
     */
    private function maybe_persist_uhf_card(\WC_Order $order, string $operation_id): void
    {
        try {
            $response = (new UnifiedApiPaymentServiceFactory())->create()->getOperation($operation_id);
        } catch (PayplugException $e) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC reconciliation: card metadata fetch failed for order #%s: %s',
                $order->get_id(),
                $e->getMessage()
            ));

            return;
        }

        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            return;
        }

        (new UhfCardFromOperationPersister())->maybe_persist($order, $decoded);
    }
}
