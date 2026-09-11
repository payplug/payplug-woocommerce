<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommercePaymentRepository;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
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
            $payment_repository->markTreated($operation_id);
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
}
