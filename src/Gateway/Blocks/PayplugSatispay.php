<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugSatispay extends PayplugGenericBlock
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "satispay";

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = parent::get_payment_method_data();
		$data['icon'] =  [
			"src" => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->gateway->image),
			'icon_alt' => $data['name'],
		];
		return $data;
	}






}
