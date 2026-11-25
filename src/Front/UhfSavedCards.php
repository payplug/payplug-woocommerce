<?php

namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

/**
 * A UHF alias is never a WC_Payment_Token (see Controller/HostedFields.php), so it never
 * appears in WooCommerce's own native "Payment methods" account table nor its native
 * delete-payment-method flow. This class adds the equivalent for UHF cards: their rows are
 * injected directly into WC's own native list (woocommerce_saved_payment_methods_list, the
 * same filter WC_Payment_Token-based rows go through - see wc_get_account_saved_payment_
 * methods_list() in WooCommerce core), rather than a separate table of our own. Besides
 * matching styling for free, this is what makes WooCommerce's own "No saved methods found."
 * notice not show up when the customer's only saved method is a UHF card: that notice is
 * keyed off wc_get_customer_saved_methods_list() being empty, which is this same filter.
 * Deletion is local-only - there is no alias-deletion endpoint on the Unified API today,
 * mirroring how the existing Retail/Integrated Payment token deletion (WC_Payment_Tokens::
 * delete(), native WooCommerce) never calls the Retail API to revoke the card either.
 * The rows carry SAVED_METHOD_GATEWAY as their gateway, so the Blocks checkout leaves them
 * out of its own saved-methods list: the checkout shows UHF cards through the plugin itself.
 * Outside EUR, the class also removes the Retail card rows from that list, except on My Account.
 */
class UhfSavedCards
{
    /**
     * method.gateway of the injected rows. The Store API (PaymentUtils::get_saved_payment_methods(),
     * used by the Blocks checkout) reads that key on every row without a guard, and keeps a row only
     * when it names an enabled gateway: this value must never be a registered gateway id.
     */
    public const SAVED_METHOD_GATEWAY = 'payplug_uhf';

    private const NONCE_ACTION_PREFIX = 'payplug-delete-uhf-card-';

    public function __construct()
    {
        add_filter('woocommerce_saved_payment_methods_list', [$this, 'add_cards_to_list'], 10, 2);
        add_filter('woocommerce_saved_payment_methods_list', [$this, 'hide_retail_cards_outside_eur'], 20, 2);
        add_action('template_redirect', [$this, 'maybe_handle_delete']);
        add_filter('woocommerce_credit_card_type_labels', [$this, 'add_cb_label']);
    }

    /**
     * The native My Account table renders a card's brand through wc_get_credit_card_type_label(),
     * whose own label map has no "cb" entry - without this, a CB-branded card (UHF or, just as
     * much, an existing Retail/Integrated Payment WC_Payment_Token_CC one) falls through to that
     * function's generic title-case fallback and shows as "Cb" instead of "CB".
     */
    public function add_cb_label(array $labels): array
    {
        $labels['cb'] = 'CB';

        return $labels;
    }

    public function add_cards_to_list(array $list, int $customer_id): array
    {
        $mode = $this->get_gateway_mode();

        if (null === $mode) {
            return $list;
        }

        foreach (UhfCard::get_customer_cards($customer_id, $mode) as $card) {
            $delete_url = wp_nonce_url(
                add_query_arg('payplug_delete_uhf_card', (int) $card->id, wc_get_endpoint_url('payment-methods')),
                self::NONCE_ACTION_PREFIX . (int) $card->id
            );

            $list['cc'][] = [
                'method' => [
                    'gateway' => self::SAVED_METHOD_GATEWAY,
                    'brand' => $card->brand,
                    'last4' => $card->last4,
                ],
                // Full 4-digit year, matching the checkout radio list's own format
                // (Controller/HostedFields.php, the Blocks component) rather than WC's native
                // 2-digit convention for Retail/IP tokens in this same table - so the same UHF
                // card doesn't look different depending on where the customer sees it.
                'expires' => sprintf('%02d/%d', (int) $card->exp_month, (int) $card->exp_year),
                'is_default' => false,
                'actions' => [
                    'delete' => [
                        'url' => $delete_url,
                        'name' => esc_html__('Delete', 'woocommerce'),
                    ],
                ],
            ];
        }

        return $list;
    }

    /**
     * The Retail API is EUR-only (see PayplugGateway::process_payment()) and a token carries
     * no currency: outside EUR a saved Retail card could be listed at checkout but never
     * charged. My Account lists the cards to manage them, not to pay: keep them there.
     * Priority 20: after WC's own builder and add_cards_to_list() (both 10).
     */
    public function hide_retail_cards_outside_eur(array $list, int $customer_id): array
    {
        if (empty($list['cc']) || PayplugWoocommerceHelper::is_eur_shop() || is_wc_endpoint_url('payment-methods')) {
            return $list;
        }

        $list['cc'] = array_values(array_filter($list['cc'], static fn (array $row): bool => 'payplug' !== ($row['method']['gateway'] ?? '')));

        if ([] === $list['cc']) {
            unset($list['cc']);
        }

        return $list;
    }

    public function maybe_handle_delete(): void
    {
        if (!isset($_GET['payplug_delete_uhf_card'])) {
            return;
        }

        $customer_id = get_current_user_id();
        $card_id = absint($_GET['payplug_delete_uhf_card']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (0 === $customer_id || 0 === $card_id || false === wp_verify_nonce($nonce, self::NONCE_ACTION_PREFIX . $card_id)) {
            wc_add_notice(__('payplug_uhf_saved_cards_invalid_notice', 'payplug'), 'error');
            wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
            exit;
        }

        $mode = $this->get_gateway_mode();
        $card = null !== $mode ? UhfCard::find_for_customer($card_id, $customer_id, $mode) : null;

        if (null === $card) {
            wc_add_notice(__('payplug_uhf_saved_cards_invalid_notice', 'payplug'), 'error');
        } else {
            UhfCard::delete($card_id, $customer_id);
            wc_add_notice(__('payplug_uhf_saved_cards_deleted_notice', 'payplug'));
        }

        wp_safe_redirect(wc_get_account_endpoint_url('payment-methods'));
        exit;
    }

    private function get_gateway_mode(): ?string
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return null;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();

        if (!isset($gateways['payplug']) || empty($gateways['payplug']->mode)) {
            return null;
        }

        return $gateways['payplug']->mode;
    }
}
