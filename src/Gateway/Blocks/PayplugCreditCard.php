<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

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

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$options = get_option('woocommerce_payplug_settings', []);
		if( $options['payment_method'] === "integrated" && $options['can_use_integrated_payments'] ){
			wp_register_script(
				'payplug-integrated-payments-api',
				'https://cdn-qa.payplug.com/js/integrated-payment/v1@1/index.js',
				array(),
				'v1.1',
				true
			);
		}
		wp_enqueue_script('payplug-integrated-payments-api');
		return parent::get_payment_method_script_handles();

	}
}
