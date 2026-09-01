<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\Gateway\Oney\OneyAccountMapper;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugOney extends PayplugGenericBlock
{
    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'oney';

    protected $icon = '';

    protected $cart;

    protected $total_price;

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * The bundled JS calls the official Oney widget (window.loadOneyWidget /
     * window.oneyMerchantApp), which is exposed by a separate external loader script - not
     * something webpack bundles. Register and prepend it here. Execution order between the
     * loader and the bundle doesn't matter: the bundle only calls loadOneyWidget() from inside
     * a useEffect, which fires after the checkout page has finished mounting - i.e. well after
     * every enqueued script has already executed.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script('payplug-oney-loader', PayplugWoocommerceHelper::get_oney_loader_url(), [], PAYPLUG_GATEWAY_VERSION, true);

        return array_merge(['payplug-oney-loader'], parent::get_payment_method_script_handles());
    }

    /**
     * Returns an associative array of data to be exposed for the payment method's client side.
     */
    public function get_payment_method_data()
    {
        $data = parent::get_payment_method_data();

        if (is_checkout()) {
            $this->cart = WC()->cart;
            $this->total_price = floatval(WC()->cart->total);
        }

        $account = PayplugWoocommerceHelper::get_account_data_from_options();
        $oney_settings = $this->gateway->get_configuration()->get_option('payment_methods.configuration.oney');
        $country = PayplugWoocommerceHelper::getISOCountryCode();
        $mapper = OneyAccountMapper::map((array) $account, $oney_settings, $country);
        $fee_mode = !empty($oney_settings['with_fees']) ? 'with_fees' : 'without_fees';

        if (PayplugWoocommerceHelper::is_cart_block() && is_cart() && !PayplugWoocommerceHelper::is_subscription()) {
            $data['oney_cart_label'] = __('OR PAY IN', 'payplug');
            if ('with_fees' === $fee_mode) {
                $data['oney_cart_logo'] = esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/lg-3x4xoney.png');
            } else {
                $data['oney_cart_logo'] = esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/Oneywithoutfees3x4x.png');
            }
        }

        $data['icon'] = [
            'src' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->icon),
            'class' => 'payplug-payment-icon',
            'alt' => $this->gateway->title,
        ];
        $data['description'] = $this->gateway->description;
        $data['currency'] = get_woocommerce_currency_symbol(get_option('woocommerce_currency'));
        $data['allowed_country_codes'] = $this->gateway->allowed_country_codes;

        $data['oney_widget'] = [
            'merchant_guid' => $mapper->get_merchant_guid(),
            'business_transaction_code' => $mapper->business_transaction_code_for_gateway($this->gateway->id),
            'business_transaction_codes' => $mapper->business_transaction_codes($fee_mode),
            'country' => $country,
            'language' => strtoupper(substr(get_locale(), 0, 2)),
            'payment_amount' => $this->total_price,
        ];

        $oney_amount = json_decode($oney_settings['custom_amounts'], true);
        $data['requirements'] = [
            'max_quantity' => $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM,
            'min_threshold' => $oney_amount['min'],
            'max_threshold' => $oney_amount['max'],
            'allowed_country_codes' => $this->gateway->allowed_country_codes,
        ];

        return $data;
    }
}
