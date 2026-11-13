<?php

namespace Payplug\PayplugWoocommerce\Upc;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommercePaymentRepository;
use Payplug\PayplugWoocommerce\Upc\Card\CardDataExtractor;
use Payplug\PayplugWoocommerce\Upc\Card\CardRecorder;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\ApiException;
use PayplugUnifiedCore\Exceptions\InvalidOperationDataException;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;
use PayplugUnifiedCore\Output\PaymentOutput;
use PayplugUnifiedCore\Utilities\Helpers\ExecCodeMapper;

class PaymentCaptureOutcomeApplier
{
    private const REDIRECT_HTML_TTL_SECONDS = 600;

    private const MAX_SUPERSEDED_OPERATION_IDS = 20;

    /**
     * Fallback execCode for the two 3DS-pending shapes: the Unified API's raw-redirect response
     * does not always carry an execCode of its own, and ExecCodeMapper maps "0001"
     * ("Authentification 3DSecure requise") to PaymentOutcome::THREE_DS_PENDING - exactly the
     * state those two branches leave the payment in.
     */
    private const PENDING_THREE_DS_EXEC_CODE = '0001';

    public function apply(\WC_Order $order, PaymentOutput $output, bool $save_card = false, array $card_fallback = []): array
    {
        $data = json_decode($output->body, true);
        $data = is_array($data) ? $data : [];
        $exec_code = isset($data['execCode']) && is_scalar($data['execCode']) ? (string) $data['execCode'] : '';

        if (null !== $output->redirectHtml) {
            $this->persist_operation(
                $order,
                $data,
                '' !== $exec_code ? $exec_code : self::PENDING_THREE_DS_EXEC_CODE,
                PaymentOutcome::THREE_DS_PENDING,
                $save_card,
                true
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
                PaymentOutcome::THREE_DS_PENDING,
                $save_card,
                true
            );

            return ['result' => 'success', 'redirect' => $output->redirectUrl];
        }

        $outcome = ExecCodeMapper::toPaymentOutcome($exec_code);

        $this->persist_operation($order, $data, $exec_code, $outcome, $save_card);

        $this->apply_terminal_outcome($order, $data, $exec_code, $outcome);

        if ($save_card && null !== $output->aliasId && PaymentOutcome::FAILED !== $outcome) {
            $this->maybe_persist_uhf_card($order, $output->aliasId, $data, $card_fallback);
        }

        if (PaymentOutcome::FAILED === $outcome) {
            (new WooCommerceLogger())->error(sprintf('UPC payment creation failed for order #%s (execCode %s).', $order->get_id(), $exec_code));
            wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
    }

    /**
     * By the time this runs, persist_operation() has already bound the order to this operation
     * id, so an IPN for the exact same operation can legitimately race in concurrently with this
     * still-in-flight synchronous request - hence OperationOutcomeProcessor, shared with
     * Front\UpcWebhook and PaymentReconciler, so the outcome is applied exactly once.
     *
     * @param array<string, mixed> $data the json_decode()'d payment-creation response body
     */
    private function apply_terminal_outcome(\WC_Order $order, array $data, string $exec_code, string $outcome): void
    {
        $operation_id = self::extract_operation_id($data);
        $order_amount = UpcOrderAmount::total($order);

        if ('' === $operation_id || '' === $exec_code || null === $order_amount) {
            // persist_operation() already logged this - the order isn't bound to any operation,
            // so no webhook can ever race this attempt: apply directly.
            (new WooCommerceOrderStateMutator())->apply((string) $order->get_id(), $outcome);

            return;
        }

        try {
            $result = (new OperationOutcomeProcessor())->process(new OperationData(
                $operation_id,
                $exec_code,
                $outcome,
                $order_amount,
                (string) $order->get_id()
            ));
        } catch (InvalidOperationDataException | PaymentNotFoundException $e) {
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment creation could not apply outcome "%s" for order #%s: %s',
                $outcome,
                $order->get_id(),
                $e->getMessage()
            ));

            return;
        }

        if (OperationOutcomeProcessor::LOCKED === $result) {
            // Contended: a webhook delivery (or a reconciliation) for this exact operation is
            // already applying it. Logged (unlike Front\UpcWebhook/PaymentReconciler) because,
            // unlike a 3DS-pending order, nothing else retries a direct/final outcome if this
            // contention was actually a transient WooCommerceLock DB error - the shopper would
            // otherwise be redirected to "Thank you" while the order silently never transitions.
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment creation could not apply outcome "%s" for order #%s: operation "%s" is locked.',
                $outcome,
                $order->get_id(),
                $operation_id
            ));
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
    private function persist_operation(\WC_Order $order, array $data, string $exec_code, string $outcome, bool $save_card = false, bool $three_ds = false): void
    {
        $operation_id = self::extract_operation_id($data);
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

        $order_amount = UpcOrderAmount::total($order);

        if (null === $order_amount) {
            // Already logged. Same consequence as the missing operation id above: nothing is
            // bound to this order.
            return;
        }

        if (!isset($data['id']) || !is_scalar($data['id']) || '' === (string) $data['id']) {
            // Not fatal for the payment itself (webhooks and the reconciler bind by operation id),
            // but refunds are made on the PAYMENT id: on staging, refunding with an operation id
            // fails with "reference transaction not found", so this order can't be refunded
            // through Payplug until the API returns the payment id.
            (new WooCommerceLogger())->error(sprintf(
                'UPC payment creation response for order #%s has no top-level payment id: the order is bound to operation id "%s" instead, and refunding it will likely fail.',
                $order->get_id(),
                $operation_id
            ));
        }

        // Read before save() below overwrites it: isTreated()/markTreated() (Front\UpcWebhook,
        // PaymentReconciler, and this class's own apply_terminal_outcome()) are keyed by whichever
        // operation id currently matches the order, not by the specific operation that was
        // actually marked - so a genuinely new operation id (a retried attempt after a prior one
        // already completed) must not inherit that prior attempt's 'treated' marker, or the new
        // operation's real outcome would be silently blocked from ever applying.
        $previous_operation_id = (string) $order->get_meta('_payplug_upc_operation_id');
        $previous_operation_id_alt = (string) $order->get_meta('_payplug_upc_operation_id_alt');

        try {
            (new WooCommercePaymentRepository())->save(new OperationData(
                $operation_id,
                $exec_code,
                $outcome,
                $order_amount,
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
                self::remember_superseded_operation($order, $previous_operation_id, $previous_operation_id_alt);
            }

            // When this attempt was created: HostedFieldsPaymentProcessor::resolve_in_flight_attempt() only
            // blocks a new payment while a 3DS-pending attempt is recent enough to still complete.
            $order->update_meta_data('_payplug_upc_created_at', time());

            // Same "clear first" pattern as the alt id above - a retried checkout whose
            // savecard choice changed must not leave a previous attempt's intent bound to this
            // order, since Front\UpcWebhook reads this meta with no other way to know the
            // original request's POST data.
            $order->delete_meta_data('_payplug_uhf_save_card');

            if ($save_card) {
                $order->update_meta_data('_payplug_uhf_save_card', '1');
            }

            $order->save();

            (new UpcTransactionMetadataRecorder())->record_created($order, $operation_id, $three_ds);
        } catch (InvalidOperationDataException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC operation "%s" could not be persisted for order #%s: %s', $operation_id, $order->get_id(), $e->getMessage()));
        } catch (PaymentNotFoundException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC operation "%s" could not be persisted for order #%s: %s', $operation_id, $order->get_id(), $e->getMessage()));
        }
    }

    /**
     * Keeps the ids of the operation a retried checkout replaces. A FAILED outcome can hide a
     * payment that actually went through (unmapped "Acceptation" execCodes such as 0002/0003 fall
     * into FAILED), so the shopper may be charged by both attempts. The late PAID notification
     * for the replaced operation no longer matches the order's binding: Front\UpcWebhook looks it
     * up here to flag the possible double charge instead of dropping it silently.
     */
    private static function remember_superseded_operation(\WC_Order $order, string $operation_id, string $operation_id_alt): void
    {
        if ('' === $operation_id) {
            return;
        }

        $superseded = $order->get_meta('_payplug_upc_superseded_operation_ids');
        $superseded = is_array($superseded) ? $superseded : [];

        foreach ([$operation_id, $operation_id_alt] as $id) {
            if ('' !== $id && !in_array($id, $superseded, true)) {
                $superseded[] = $id;
            }
        }

        // Bounded: each retry adds at most two ids, and a shopper retrying more than a handful of
        // times on the same order is not a case worth growing the meta for.
        $order->update_meta_data('_payplug_upc_superseded_operation_ids', array_slice($superseded, -self::MAX_SUPERSEDED_OPERATION_IDS));
    }

    /**
     * The order's primary operation binding: the response's top-level "id" when present, else
     * operationIds[0]. The Unified API does not always send the top-level id - two identical
     * successful payment creations on staging (2026-10-05) came back one with it, one with only
     * operationIds - and without this fallback such an order would be bound to nothing at all.
     *
     * @param array<string, mixed> $data the json_decode()'d payment-creation response body
     */
    private static function extract_operation_id(array $data): string
    {
        if (isset($data['id']) && is_scalar($data['id']) && '' !== (string) $data['id']) {
            return (string) $data['id'];
        }

        return isset($data['operationIds'][0]) && is_scalar($data['operationIds'][0]) ? (string) $data['operationIds'][0] : '';
    }

    /**
     * Best-effort: a direct (non-3DS) success carries the alias id in PaymentOutput::$aliasId,
     * but never its brand/last4/expiration - those are fetched from the public operation
     * endpoint when possible, falling back to the (sanitized, re-validated) values the client
     * submitted. Never fails the payment itself - a fetch failure only means the card metadata
     * falls back further, or the card isn't saved at all.
     *
     * @param array<string, mixed> $data the json_decode()'d payment-creation response body
     * @param array<string, mixed> $card_fallback client-submitted brand/last4/exp_month/exp_year
     */
    private function maybe_persist_uhf_card(\WC_Order $order, string $alias_id, array $data, array $card_fallback): void
    {
        if (0 === $order->get_customer_id()) {
            return;
        }

        // operationIds[0] first: the public operation endpoint only knows the operation, not the
        // payment's own top-level "id" (a 404 for it, observed on staging 2026-10-05). The
        // top-level id stays as a fallback for a response that carries no operationIds.
        $operation_id = isset($data['operationIds'][0]) && is_scalar($data['operationIds'][0])
            ? (string) $data['operationIds'][0]
            : (isset($data['id']) && is_scalar($data['id']) ? (string) $data['id'] : '');
        $fetched = [];

        if ('' !== $operation_id) {
            try {
                $response = (new UnifiedApiPaymentServiceFactory())->create()->getOperation($operation_id);
                $decoded = json_decode($response['body'], true);
                $fetched = is_array($decoded) ? CardDataExtractor::extract($decoded) : [];
            } catch (ApiException $e) {
                (new WooCommerceLogger())->error(sprintf(
                    'UPC best-effort card metadata fetch failed for order #%s: %s',
                    $order->get_id(),
                    $e->getMessage()
                ));
            }
        }

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';

        $persisted = (new CardRecorder())->persist($order->get_customer_id(), $alias_id, $mode, $card_fallback, $fetched);

        if (!$persisted) {
            // The most likely real-world failure mode for this whole feature: an unexpected
            // brand string, a code6x4 in an unanticipated shape, or a malformed validityDate
            // gets rejected by CardRecorder's sanitization, and without this the customer
            // ticks "save my card", the payment still succeeds, no card ever appears, and there
            // was nothing anywhere to explain why.
            (new WooCommerceLogger())->error(sprintf(
                'UPC: save-card was requested for order #%s but the card data was rejected (fetched: %s, fallback: %s).',
                $order->get_id(),
                wp_json_encode($fetched),
                wp_json_encode($card_fallback)
            ));
        }
    }
}
