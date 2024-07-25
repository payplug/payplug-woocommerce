<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugCreditCard extends PayplugGenericBlock
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "payplug";

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {

		$data = parent::get_payment_method_data();
		$data['icon'] =  [
			"src" => ('it_IT' === get_locale()) ?
				PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_PostePay.svg' :
				PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg',
			'icon_alt' => "Visa & Mastercard",
		];
		return $data;
	}

}