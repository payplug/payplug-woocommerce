<?php

namespace Payplug\PayplugWoocommerce\Gateway\OneyGateways;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPlug WooCommerce Gateway.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class Oney4xWF extends PayplugGatewayOney
{

    public function __construct()
    {
        parent::__construct();
        $this->id                 = 'oney_x4_without_fees';
        $this->method_title       = _x('PayPlug Oney 4x', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug Oney 4x for your customers.', 'payplug');
	    $this->title              = __('Pay by credit card in 4x installments without fees with Oney', 'payplug');
	    $this->_oney_type         = 'without_fees';
	    $this->logos              = ['Oney4x.png', 'Oney4x_grey.png'];
    }

	/**
	 * Set descriotion according to the simulate_oney_payment
	 *
	 * @param $oney_response
	 * @param $currency
	 * @return string
	 */
	public function set_description($oney_response, $currency) {
		$f = function ($fn) {return $fn;};
		return <<<HTML
                <p>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('Bring', 'payplug'))} :</div>
                        <div>{$oney_response['x4_without_fees']['down_payment_amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('1st monthly payment', 'payplug'))} :</div>
                        <div>{$oney_response['x4_without_fees']['installments'][0]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('2nd monthly payment', 'payplug'))} :</div>
                        <div>{$oney_response['x4_without_fees']['installments'][1]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('3rd monthly payment', 'payplug'))} :</div>
                        <div>{$oney_response['x4_without_fees']['installments'][2]['amount']} {$currency}</div>
                    </div>
                </p>
HTML;
	}

}
