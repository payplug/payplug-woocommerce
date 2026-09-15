<?php

namespace Payplug\PayplugWoocommerce\Front\Oney;

use Payplug\PayplugWoocommerce\Gateway\Oney\OneyAccountMapper;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class OneyDisplay
{
    public function __construct()
    {
        // Registered on init rather than on wp_enqueue_scripts: on WooCommerce block-theme
        // product templates, content hooks like woocommerce_before_add_to_cart_form can fire
        // BEFORE wp_enqueue_scripts (block rendering doesn't follow the classic head-then-body
        // template lifecycle). wp_localize_script() only needs the handle to be *registered*,
        // not enqueued, so registering on init guarantees it's available by the time
        // show_on_product()/show_on_cart() call wp_localize_script() on it, regardless of which
        // hook happens to fire first on a given request.
        //
        // It must not happen any earlier: this class is built on plugins_loaded, and
        // wp_register_script() before init trips WP's _doing_it_wrong() notice, which prints
        // output before any header() call - breaking redirects and corrupting the JSON of AJAX
        // endpoints such as WooCommerce's update_order_review.
        if (did_action('init')) {
            $this->register_assets();
        } else {
            add_action('init', [$this, 'register_assets']);
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_cart_totals_after_order_total', [$this, 'show_on_cart']);

        $options = PayplugWoocommerceHelper::get_payplug_options();
        if (!empty($options['payment_methods']['configuration']['oney']['cta_product'])) {
            add_action('woocommerce_before_add_to_cart_form', [$this, 'show_on_product']);
        }
    }

    public function register_assets(): void
    {
        wp_register_script('payplug-oney-loader', PayplugWoocommerceHelper::get_oney_loader_url(), [], PAYPLUG_GATEWAY_VERSION, true);
        wp_register_script('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-oney.js', [
            'jquery',
            'payplug-oney-loader',
        ], PAYPLUG_GATEWAY_VERSION, true);
    }

    public function enqueue_assets(): void
    {
        wp_enqueue_style('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-oney.css', [], PAYPLUG_GATEWAY_VERSION);
        wp_enqueue_script('payplug-oney');
    }

    public function show_on_product(): void
    {
        global $product;

        if (!function_exists('is_product') || !is_product() || empty($product)) {
            return;
        }

        if (in_array($product->get_type(), ['subscription', 'downloadable_subscription', 'virtual_subscription', 'variable-subscription'], true)) {
            return;
        }

        $this->render_badge((float) $product->get_price(), 1);
    }

    public function show_on_cart(): void
    {
        if (!function_exists('is_cart') || !is_cart() || PayplugWoocommerceHelper::is_subscription()) {
            return;
        }

        $total_products = 0;
        foreach (WC()->cart->cart_contents as $item) {
            $total_products += $item['quantity'];
        }

        $this->render_badge((float) WC()->cart->total, $total_products);
    }

    private function render_badge(float $price, int $total_products): void
    {
        if (!PayplugWoocommerceHelper::check_order_max_amount($price)) {
            return;
        }

        $account = PayplugWoocommerceHelper::get_account_data_from_options();
        $options = PayplugWoocommerceHelper::get_payplug_options();
        $oney_settings = $options['payment_methods']['configuration']['oney'] ?? [];
        $country = PayplugWoocommerceHelper::getISOCountryCode();

        $mapper = OneyAccountMapper::map((array) $account, $oney_settings, $country);
        if (!$mapper->is_enabled()) {
            return;
        }

        $fee_mode = !empty($oney_settings['with_fees']) ? 'with_fees' : 'without_fees';
        $amount_in_cents = (int) round($price * 100);
        $eligible = $mapper->is_eligible($country, $amount_in_cents);

        // Only country + amount-in-range decide eligibility (see is_eligible()), and country
        // can't change client-side, so probing at the min bound tells us whether country alone
        // already disqualifies this badge - if it does, JS must never re-enable it regardless of
        // quantity; the min/max attributes are omitted so the client has nothing to recalculate.
        $country_eligible = $mapper->is_eligible($country, $mapper->get_min_amount());

        wp_localize_script('payplug-oney', 'payplug_oney_config', [
            'merchant_guid' => $mapper->get_merchant_guid(),
            'business_transaction_codes' => $mapper->business_transaction_codes($fee_mode),
            'country' => $country,
            'language' => strtoupper(substr(get_locale(), 0, 2)),
        ]);

        $logo_class = 'with_fees' === $fee_mode ? 'oney-3x4x' : 'oney-without-fees-3x4x';

        $threshold_attrs = $country_eligible
            ? sprintf(' data-min-oney="%s" data-max-oney="%s"', esc_attr($mapper->get_min_amount() / 100), esc_attr($mapper->get_max_amount() / 100))
            : '';

        printf(
            '<div class="payplug-oney%1$s" data-price="%2$s" data-total-products="%3$d"%6$s>%4$s<div class="payplug-oney-popup"><div class="oney-img %5$s"></div><div id="oney-show-popup" class="bold oney-color">?</div></div></div>',
            $eligible ? '' : ' disabled',
            esc_attr($price),
            (int) $total_products,
            esc_html__('OR PAY IN', 'payplug'),
            esc_attr($logo_class),
            $threshold_attrs
        );
    }
}
