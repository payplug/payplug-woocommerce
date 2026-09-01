<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPlug WooCommerce Gateway.
 */
class PayplugGatewayOney4x extends PayplugGatewayOney3x
{
    public function __construct()
    {
        parent::__construct();
        $this->id = 'oney_x4_with_fees';
        $this->method_title = _x('PayPlug Oney 4x', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug Oney 4x for your customers.', 'payplug');
        $this->title = __('Pay by card in 4x with Oney', 'payplug');
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
        $available_img = 'x4_with_fees.svg';

        $icons = apply_filters('payplug_payment_icons', [
            'payplug' => sprintf('<img src="%s" alt="Oney 4x" class="payplug-payment-icon ' . $disable . '" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $available_img)),
        ]);
        $icons_str = '';
        foreach ($icons as $icon) {
            $icons_str .= $icon;
        }

        return $icons_str;
    }
}
