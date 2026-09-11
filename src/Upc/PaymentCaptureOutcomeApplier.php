<?php

namespace Payplug\PayplugWoocommerce\Upc;

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

        (new WooCommerceOrderStateMutator())->apply((string) $order->get_id(), $outcome);

        if (PaymentOutcome::FAILED === $outcome) {
            (new WooCommerceLogger())->error(sprintf('UPC payment creation failed for order #%s (execCode %s).', $order->get_id(), $exec_code));
            wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
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

            $order->save();
        } catch (InvalidOperationDataException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC operation "%s" could not be persisted for order #%s: %s', $operation_id, $order->get_id(), $e->getMessage()));
        } catch (PaymentNotFoundException $e) {
            (new WooCommerceLogger())->error(sprintf('UPC operation "%s" could not be persisted for order #%s: %s', $operation_id, $order->get_id(), $e->getMessage()));
        }
    }
}
