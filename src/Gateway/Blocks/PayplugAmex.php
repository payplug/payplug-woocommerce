<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugAmex extends PayplugGenericBlock
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "american_express";

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = parent::get_payment_method_data();
		$data['icon'] =  [
			"src" => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->gateway->image),
			'icon_alt' => $data['name'],
		];

		$options = get_option('woocommerce_payplug_settings', []);
		if( $options['payment_method'] === "popup" ){
			$data["popup"] = true;
			$data['payplug_create_intent_payment'] = \WC_AJAX::get_endpoint('payplug_create_intent');
			$data['payplug_create_order'] = \WC_AJAX::get_endpoint('payplug_create_order');
			$data['wp_nonce'] = wp_create_nonce( "woocommerce-process_checkout" );

		}


		return $data;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$options = get_option('woocommerce_payplug_settings', []);
		if ( $options['payment_method'] == "popup" ) {
			$this->popup_scripts();
		}

		return parent::get_payment_method_script_handles();
	}


	private function popup_scripts(){
		wp_register_script('payplug', 'https://api.payplug.com/js/1/form.latest.js', [], null, true);
		wp_enqueue_script('payplug');
	}



}
