<?php

namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Upc\PaymentReconciler;

/**
 * Everything that happens when the shopper comes back from a UPC (hosted fields) payment: the
 * intermediate page that posts the bank's 3DS challenge form, the notice shown after an
 * abandoned challenge, and the order-received page reconciliation of a still-pending payment.
 */
class UpcCheckoutReturn
{
    public function __construct()
    {
        add_action('woocommerce_api_payplug_upc_3ds', [$this, 'render_upc_3ds_redirect']);
        add_action('template_redirect', [$this, 'maybe_notice_upc_cancelled']);
        add_action('woocommerce_thankyou', [$this, 'maybe_reconcile_pending_upc_payment']);
    }

    /**
     * Echoes the Base64-decoded 3DS-pending HTML (a self-submitting form to the bank's challenge
     * page) stashed by PaymentCaptureOutcomeApplier::apply(), then clears it - single-use, same as
     * the token it was built from.
     */
    public function render_upc_3ds_redirect(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is the bank's own
        // self-submitting 3DS challenge form, already Base64-decoded by
        // PaymentOutput/UnifiedApiPaymentService from a 2xx Unified API response; escaping it
        // would corrupt the form.
        echo $this->resolve_upc_3ds_html();
        exit;
    }

    /**
     * Split out from render_upc_3ds_redirect() so the order-key validation and single-use-transient
     * handling - the security-sensitive part this endpoint depends on - is unit-testable without
     * that method's own exit call, which can't be caught in PHPUnit. A failure path never returns
     * (wp_die() itself exits - or, under the core test suite's wp_die_handler override, throws
     * WPDieException instead).
     */
    public function resolve_upc_3ds_html(): string
    {
        $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $submitted_order_key = isset($_GET['order_key']) ? sanitize_text_field(wp_unslash($_GET['order_key'])) : '';

        $order = wc_get_order($order_id);
        // wc_get_order() also resolves a refund id to a WC_Order_Refund, which has no
        // get_order_key() - an instanceof check, not a "!== false" one, keeps this public,
        // unauthenticated endpoint from fataling on a guessed refund id.
        $order_key_matches = $order instanceof \WC_Order && '' !== $submitted_order_key && hash_equals($order->get_order_key(), $submitted_order_key);

        // Validate the key BEFORE consuming the single-use transient: deleting it first would let
        // anyone who merely guesses an order id destroy the legitimate customer's 3DS challenge.
        if (!$order_key_matches) {
            wp_die(esc_html__('This payment confirmation link has expired.', 'payplug'), '', ['response' => 410]);
        }

        $html = get_transient('payplug_upc_redirect_html_' . $order_id);

        delete_transient('payplug_upc_redirect_html_' . $order_id);

        if (false === $html) {
            wp_die(esc_html__('This payment confirmation link has expired.', 'payplug'), '', ['response' => 410]);
        }

        return $html;
    }

    /**
     * A shopper who abandons the bank's 3DS challenge is sent here (PaymentCaptureContextBuilder
     * sets this order-pay URL as the payment's cancelUrl) rather than to the order-received page,
     * which would otherwise show "Thank you, your order has been received" for a payment that was
     * never completed.
     */
    public function maybe_notice_upc_cancelled(): void
    {
        if (!isset($_GET['payplug_upc_cancelled'])) {
            return;
        }

        // Ties the notice to a real order-pay request for this specific order, rather than
        // firing for anyone who merely appends ?payplug_upc_cancelled=1 to any front-end URL:
        // get_checkout_payment_url() (the only place this query arg is ever set) always resolves
        // to the 'order-pay' endpoint with the order's own key as the 'key' param.
        $order_id = (int) get_query_var('order-pay');
        $submitted_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order || '' === $submitted_key || !hash_equals($order->get_order_key(), $submitted_key)) {
            return;
        }

        wc_add_notice(__('Your payment was not completed. Please try again.', 'payplug'), 'notice');
    }

    /**
     * @param int $order_id
     */
    public function maybe_reconcile_pending_upc_payment($order_id): void
    {
        $order = wc_get_order($order_id);

        if ($order instanceof \WC_Order) {
            (new PaymentReconciler())->reconcile($order);
        }
    }
}
