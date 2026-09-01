<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPlug WooCommerce Gateway.
 */
class PayplugGatewayOney3xWithoutFees extends PayplugGatewayOney3x
{
    public function __construct()
    {
        parent::__construct();
        $this->id = 'oney_x3_without_fees';
        $this->method_title = _x('PayPlug Oney 3x', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug Oney 3x for your customers.', 'payplug');
        $this->title = __('Pay by credit card in 3x installments without fees with Oney', 'payplug');
        $this->has_fields = true;
    }

    /**
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {
        $disable = $this->check_oney_is_available() === true ? '' : 'disable-checkout-icons';
        $country = PayplugWoocommerceHelper::getISOCountryCode();

        if (file_exists(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_' . $country . '.svg')) {
            $image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_' . $country . '.svg';
        } else {
            $image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_FR.svg';
        }

        $icons = apply_filters('payplug_payment_icons', [
            'payplug' => sprintf('<img src="%s" alt="Oney 3x" class="payplug-payment-icon ' . $disable . '" />', esc_url($image)),
        ]);
        $icons_str = '';
        foreach ($icons as $icon) {
            $icons_str .= $icon;
        }

        return $icons_str;
    }
}
