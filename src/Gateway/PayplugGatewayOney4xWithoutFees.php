<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPlug WooCommerce Gateway.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class PayplugGatewayOney4xWithoutFees extends PayplugGatewayOney3x
{

    public function __construct()
    {
        parent::__construct();
        $this->id                 = 'oney_x4_without_fees';
        $this->method_title       = _x('PayPlug Oney 4x', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug Oney 4x for your customers.', 'payplug');
	    $this->title              = __('Pay by credit card in 4x installments without fees with Oney', 'payplug');
		$this->has_fields = false;
    }

    /**
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {
		$disable = '';
        if ($this->check_oney_is_available() === true) {
	        $total_price = floatval(WC()->cart->total);
	        $this->oney_response = $this->api->simulate_oney_payment($total_price, 'without_fees');
            $currency = get_woocommerce_currency_symbol(get_option('woocommerce_currency'));
            $f = function ($fn) {
                return $fn;
            };

	        $total_price_oney = floatval($this->oney_response['x4_without_fees']['down_payment_amount']);
	        foreach ($this->oney_response['x4_without_fees']['installments'] as $installment) {
		        $total_price_oney = $total_price_oney + floatval($installment['amount']);
	        }

	        if(is_array($this->oney_response)) {
                $this->description = <<<HTML
					<div class="payplug-oney-flex">
	                        <div><b>{$f(__('oney_total', 'payplug'))}</b></div>
	                        <div><b>{$total_price_oney} {$currency}</b></div>
	                </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('Bring', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_without_fees']['down_payment_amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('1st monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_without_fees']['installments'][0]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('2nd monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_without_fees']['installments'][1]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('3rd monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x4_without_fees']['installments'][2]['amount']} {$currency}</div>
                    </div>
                    <div>
{$f(__('oney_financing_cost', 'payplug'))} {$this->oney_response['x4_without_fees']['nominal_annual_percentage_rate']} {$currency} TAEG : {$this->oney_response['x4_without_fees']['effective_annual_percentage_rate']} %
                    </div>
HTML;
            } else {
                $this->description = $this->oney_response;
            }
        }else{
			$disable='disable-checkout-icons';
		}

		$country = PayplugWoocommerceHelper::getISOCountryCode();

		if( file_exists(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x4_without_fees_' .  $country . '.svg') ){
			$image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x4_without_fees_' .  $country . '.svg';
		}else{
			$image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x4_without_fees_FR.svg';
		}

        $icons = apply_filters('payplug_payment_icons', [
            'payplug' => sprintf('<img src="%s" alt="Oney 4x" class="payplug-payment-icon ' . $disable . '" />', esc_url( $image )),
        ]);
        $icons_str = '';
        foreach ($icons as $icon) {
            $icons_str .= $icon;
        }
        return $icons_str;
    }
}
