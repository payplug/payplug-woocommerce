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
use PayplugUnifiedCore\Output\PaymentOutput;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;
use PayplugUnifiedCore\Utilities\Helpers\ExecCodeMapper;

class PaymentCaptureOutcomeApplier
{
    private const REDIRECT_HTML_TTL_SECONDS = 600;

    /**
     * Fallback execCode for the two 3DS-pending shapes: the Unified API's raw-redirect response
     * does not always carry an execCode of its own, and ExecCodeMapper maps "0001"
     * ("Authentification 3DSecure requise") to PaymentOutcome::THREE_DS_PENDING - exactly the
     * state those two branches leave the payment in.
     */
    private const PENDING_THREE_DS_EXEC_CODE = '0001';

    /**
     * Same lock key format/TTL as Front\UpcWebhook and PaymentReconciler - this direct/final
     * branch is the third path that can apply a terminal outcome for a given operation, and
     * persist_operation() has already bound the order to it by the time apply_terminal_outcome()
     * runs, so a webhook for this exact operation can race in concurrently.
     */
    private const LOCK_TTL_SECONDS = 30;

    public function apply(\WC_Order $order, PaymentOutput $output): array
    {
        $data = json_decode($output->body, true);
        $data = is_array($data) ? $data : [];
        $exec_code = isset($data['execCode']) && is_scalar($data['execCode']) ? (string) $data['execCode'] : '';

        if (null !== $output->redirectHtml) {
            $this->persist_operation(
                $order,
                $data,
                '' !== $exec_code ? $exec_code : self::PENDING_THREE_DS_EXEC_CODE,
                PaymentOutcome::THREE_DS_PENDING
            );

            set_transient('payplug_upc_redirect_html_' . $order->get_id(), $output->redirectHtml, self::REDIRECT_HTML_TTL_SECONDS);

            return [
                'result' => 'success',
                'redirect' => add_query_arg(
                    [
                        'order_id' => $order->get_id(),
                        'order_key' => $order->get_order_key(),
                    ],
                    WC()->api_request_url('payplug_upc_3ds')
                ),
            ];
        }

        if (null !== $output->redirectUrl) {
            $this->persist_operation(
                $order,
                $data,
                '' !== $exec_code ? $exec_code : self::PENDING_THREE_DS_EXEC_CODE,
                PaymentOutcome::THREE_DS_PENDING
            );

            return ['result' => 'success', 'redirect' => $output->redirectUrl];
        }

        $outcome = ExecCodeMapper::toPaymentOutcome($exec_code);

        $this->persist_operation($order, $data, $exec_code, $outcome);

        $this->apply_terminal_outcome($order, $data, $outcome);

        if (PaymentOutcome::FAILED === $outcome) {
            (new WooCommerceLogger())->error(sprintf('UPC payment creation failed for order #%s (execCode %s).', $order->get_id(), $exec_code));
            wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
    }

    /**
     * By the time this runs, persist_operation() has already bound the order to this operation
     * id, so an IPN for the exact same operation can legitimately race in and reach
     * Front\UpcWebhook::receive_notification() concurrently with this still-in-flight synchronous
     * request. Same lock key format, and the same isTreated()/markTreated() guard, as
     * Front\UpcWebhook and PaymentReconciler::apply_reconciled_state() use, so whichever side
     * wins the lock finds the other's application already done and skips re-applying it - closing
     * a race that would otherwise double-fire 'woocommerce_payment_complete'.
     *
     * @param array<string, mixed> $data the json_decode()'d payment-creation response body
     */
    private function apply_terminal_outcome(\WC_Order $order, array $data, string $outcome): void
    {
        $operation_id = isset($data['id']) && is_scalar($data['id']) ? (string) $data['id'] : '';

        if ('' === $operation_id) {
            // persist_operation() already logged this - there is no operation id for any webhook
            // to ever bind to for this attempt, so there is nothing to race against either.
            (new WooCommerceOrderStateMutator())->apply((string) $order->get_id(), $outcome);

            return;
        }

        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_treat_' . $operation_id;

        if (!$lock->acquire($lock_key, self::LOCK_TTL_SECONDS)) {
            // Contended: a webhook delivery (or a reconciliation) for this exact operation is
            // already applying it - let it own the outcome rather than racing it. Logged
            // (unlike Front\UpcWebhook/PaymentReconciler's own contended branches) because,
            // unlike a 3DS-pending order, nothing else retries a direct/final outcome if this
            // contention was actually a transient WooCommerceLock DB error rather than a real
            // concurrent webhook - the shopper would otherwise be redirected to "Thank you" while
            // the order silently never transitions, with no trace of why.
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment creation could not apply outcome "%s" for order #%s: operation "%s" is locked.',
                $outcome,
                $order->get_id(),
                $operation_id
            ));

            return;
        }

        try {
            $payment_repository = new WooCommercePaymentRepository();

            if (!$payment_repository->isTreated($operation_id)) {
                (new WooCommerceOrderStateMutator())->apply((string) $order->get_id(), $outcome);
                $payment_repository->markTreated($operation_id);
            }
        } catch (PaymentNotFoundException $e) {
            // Only reachable when persist_operation() itself bailed out just above (a present
            // "id" but an empty execCode) - the order was never actually bound to $operation_id,
            // so markTreated() has nothing to find. The mutator call above already ran; this
            // only means the operation can't be marked treated for a future webhook to respect.
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment creation could not mark operation "%s" treated for order #%s: %s',
                $operation_id,
                $order->get_id(),
                $e->getMessage()
            ));
        } finally {
            $lock->release($lock_key);
        }
    }

    /**
     * Binds the Unified API's operation id to the order as soon as the payment is created, in
     * every branch - a 3DS-pending payment included. Without it the order carries no payment
     * reference at all until (if ever) a webhook arrives, leaving nothing to reconcile against
     * and nothing for Front\UpcWebhook to check an incoming notification's operation id against.
     *
     * The Unified API's payment-creation response carries the operation id as its top-level "id"
     * field (the same value UPC's OperationData/webhook vocabulary calls operationId - see
     * UnifiedApiPaymentService::createRefund()'s own docblock). Never fatal: a response without a
     * usable id is logged and the checkout flow continues unchanged.
     *
     * A payment is however not the same resource as an operation: it owns an "operationIds" array
     * (see UnifiedApiOperationService's own docblock), and the Sylius implementation of this same
     * integration binds BOTH the top-level id and operationIds[0], matching an incoming
     * notification against either. Which of the two the notifier sends cannot be confirmed without
     * a real Unified API environment, so the second candidate is stored alongside the first in
     * '_payplug_upc_operation_id_alt' and Front\UpcWebhook accepts a match on either. This does
     * not weaken the binding: both are unguessable, API-issued values, and the webhook still
     * requires an exact hash_equals() match to one of them.
     *
     * @param array<string, mixed> $data the json_decode()'d payment-creation response body
     */
    private function persist_operation(\WC_Order $order, array $data, string $exec_code, string $outcome): void
    {
        $operation_id = isset($data['id']) && is_scalar($data['id']) ? (string) $data['id'] : '';
        $operation_id_alt = isset($data['operationIds']) && is_array($data['operationIds']) && isset($data['operationIds'][0]) && is_scalar($data['operationIds'][0])
            ? (string) $data['operationIds'][0]
            : '';

        if ('' === $operation_id || '' === $exec_code) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment creation response for order #%s carries no usable operation id/execCode - nothing persisted, so no notification can be bound to this order.',
                $order->get_id()
            ));

            return;
        }

        // Read before save() below overwrites it: isTreated()/markTreated() (Front\UpcWebhook,
        // PaymentReconciler, and this class's own apply_terminal_outcome()) are keyed by whichever
        // operation id currently matches the order, not by the specific operation that was
        // actually marked - so a genuinely new operation id (a retried attempt after a prior one
        // already completed) must not inherit that prior attempt's 'treated' marker, or the new
        // operation's real outcome would be silently blocked from ever applying.
        $previous_operation_id = (string) $order->get_meta('_payplug_upc_operation_id');

        try {
            (new WooCommercePaymentRepository())->save(new OperationData(
                $operation_id,
                $exec_code,
                $outcome,
                AmountHelper::toCents((float) $order->get_total()),
                (string) $order->get_id()
            ));

            // Always cleared first: a retried checkout whose new response carries no alt id (or
            // the same value as its own primary) must not leave a PREVIOUS attempt's alt id still
            // bound to this order - Front\UpcWebhook would otherwise still accept a late
            // notification for that superseded operation.
            $order->delete_meta_data('_payplug_upc_operation_id_alt');

            if ('' !== $operation_id_alt && $operation_id_alt !== $operation_id) {
                // Second, equally unguessable identity the notification may carry instead. Written
                // as plain meta rather than through the repository: OperationData holds a single
                // operationId, and this is only ever read as an extra accepted value by
                // Front\UpcWebhook - it never becomes the order's primary operation binding.
                $order->update_meta_data('_payplug_upc_operation_id_alt', $operation_id_alt);
            }

            if ($previous_operation_id !== $operation_id) {
                $order->delete_meta_data('_payplug_upc_treated');
            }

            $order->save();
        } catch (InvalidOperationDataException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC operation "%s" could not be persisted for order #%s: %s', $operation_id, $order->get_id(), $e->getMessage()));
        } catch (PaymentNotFoundException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC operation "%s" could not be persisted for order #%s: %s', $operation_id, $order->get_id(), $e->getMessage()));
        }
    }
}
