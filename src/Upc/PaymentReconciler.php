<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Card\CardRecorderFromOperation;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\InvalidOperationDataException;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;
use PayplugUnifiedCore\Exceptions\PayplugException;
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
     * The order-received page re-fires reconcile() on every load with no other backoff - a
     * shopper stuck on a pending order who refreshes repeatedly would otherwise hammer the
     * Unified API with an OAuth token fetch plus getOperation() (up to ~20s of synchronous calls,
     * WooCommerceUnifiedApiHttpClient's 10s timeout each) on every single request.
     */
    private const RECONCILE_THROTTLE_SECONDS = 60;

    /**
     * @param bool $bypass_throttle true when the caller must know the current state right now
     *                              (HostedFieldsPaymentProcessor::process_payment() deciding whether a new
     *                              attempt may be created), rather than on a page render
     */
    public function reconcile(\WC_Order $order, bool $bypass_throttle = false): void
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

        if (!$bypass_throttle && false !== get_transient($throttle_key)) {
            return;
        }

        set_transient($throttle_key, '1', self::RECONCILE_THROTTLE_SECONDS);

        // The operation resource, not the payment one: getPayment() answers 404 for both of the
        // order's ids, while the public operation endpoint answers for operationIds[0] (the alt
        // id) with the execCode, amount and paymentMethod this needs (observed on staging,
        // 2026-10-05). Lock and treated marker stay keyed on the primary id.
        $fetch_id = (string) $order->get_meta('_payplug_upc_operation_id_alt');
        $fetch_id = '' !== $fetch_id ? $fetch_id : $operation_id;

        try {
            $response = (new UnifiedApiPaymentServiceFactory())->create()->getOperation($fetch_id);
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

        $order_amount = UpcOrderAmount::total($order);

        if (null === $order_amount) {
            // Already logged: the amount can't be checked, so nothing is applied - same as a mismatch.
            return;
        }

        if (null === $amount || $order_amount !== $amount) {
            (new WooCommerceLogger())->error(sprintf('UPC payment reconciliation amount mismatch for order #%s.', $order->get_id()));

            return;
        }

        // Through OperationOutcomeProcessor, like Front\UpcWebhook: a webhook delivery landing at the
        // same moment as this reconciliation must not apply the outcome a second time. LOCKED or
        // ALREADY_TREATED means the webhook is handling (or has handled) this exact operation.
        try {
            (new OperationOutcomeProcessor())->process(
                new OperationData(
                    $operation_id,
                    $exec_code,
                    $outcome,
                    $order_amount,
                    (string) $order->get_id()
                ),
                function () use ($order, $data): void {
                    // The body just fetched is the operation resource, the same
                    // paymentMethod.{id, card, details} shape the webhook carries - no second fetch.
                    (new CardRecorderFromOperation())->maybe_persist($order, $data);
                    (new UpcTransactionMetadataRecorder())->record_card($order, $data);
                }
            );
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
        }
    }
}
