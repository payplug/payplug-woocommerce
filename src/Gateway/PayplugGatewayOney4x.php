<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPlug WooCommerce Gateway.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class PayplugGatewayOney4x extends PayplugGatewayOney3x
{

    public function __construct()
    {
        parent::__construct();
        $this->id                 = 'oney_x4_with_fees';
        $this->method_title       = _x('PayPlug Oney 4x', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug Oney 4x for your customers.', 'payplug');
        $this->title              = __('Pay by card in 4x with Oney', 'payplug');
		$this->has_fields = false;
    }

    /**
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {
        if ($this->check_oney_is_available() === true) {
	        $total_price = floatval(WC()->cart->total);
	        $this->oney_response = $this->api->simulate_oney_payment($total_price, 'with_fees');
            $currency = get_woocommerce_currency_symbol(get_option('woocommerce_currency'));
            $f = function ($fn) {
                return $fn;
            };
            if(is_array($this->oney_response)) {
                $this->description = <<<HTML
                <p>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('Bring', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_with_fees']['down_payment_amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('1st monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_with_fees']['installments'][0]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('2nd monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_with_fees']['installments'][1]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('3rd monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_with_fees']['installments'][2]['amount']} {$currency}</div>
                    </div>
                </p>
HTML;
            } else {
                $this->description = $this->oney_response;
            }
        }
        $available_img = 'x4_with_fees.svg';

		$disable='';
		if($this->check_oney_is_available() != true){
			$disable='disable-checkout-icons';
		}

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
