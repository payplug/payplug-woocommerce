<?php

namespace Payplug\PayplugWoocommerce\Gateway\OneyGateways;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayPlug WooCommerce Gateway.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class Oney3x extends PayplugGatewayOney {


	public function __construct() {
		parent::__construct();
		$this->id                 = 'oney_x3_with_fees';
		$this->method_title       = _x( 'PayPlug Oney 3x', 'Gateway method title', 'payplug' );
		$this->method_description = __( 'Enable PayPlug Oney 3x for your customers.', 'payplug' );
		$this->title              = __( 'Pay by card in 3x with Oney', 'payplug' );
		$this->_oney_type         = 'with_fees';
		$this->logos              = ['lg-3xoney-checkout.png', 'lg-3xoney-checkout-disabled.png'];
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
                        <div>{$oney_response['x3_with_fees']['down_payment_amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('1st monthly payment', 'payplug'))} :</div>
                        <div>{$oney_response['x3_with_fees']['installments'][0]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('2nd monthly payment', 'payplug'))} :</div>
                        <div>{$oney_response['x3_with_fees']['installments'][1]['amount']} {$currency}</div>
                    </div>
                </p>
HTML;
	}

}
