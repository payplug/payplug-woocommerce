<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugApplePay extends PayplugGenericBlock
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "apple_pay";

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = parent::get_payment_method_data();
		$data['icon'] =  [
			"src" => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/' . $this->gateway->image),
			'icon_alt' => $data['name'],
		];

		$data['payplug_locale'] = get_locale();
		$data['payplug_countryCode'] = WC()->customer !== null ? WC()->customer->get_billing_country() : "FR";
		$data['payplug_currencyCode'] = get_woocommerce_currency();
		$data['payplug_apple_pay_domain'] = $_SERVER['HTTP_HOST'];
		$data['ajax_url_applepay_update_payment'] = \WC_AJAX::get_endpoint('applepay_update_payment');
		$data['payplug_create_intent_payment'] = \WC_AJAX::get_endpoint('payplug_create_intent');
		$data['is_cart'] = is_cart() && $this->gateway->get_button_cart();

		$data['payplug_authorized_carriers'] = $this->gateway->get_carriers();
		return $data;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		wp_register_script(
			'apple-pay-sdk',
			'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js',
			array(),
			'1.latest',
			true
		);

		wp_enqueue_style('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-apple-pay.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_script( 'apple-pay-sdk' );

		return parent::get_payment_method_script_handles();
	}

	public function is_active()
	{
		if (class_exists('WC_Blocks_Utils')) {
			if (\WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' )) {

				if( $this->gateway->checkApplePay() && $this->gateway->checkDeviceComptability() && $this->gateway->isSSL() ){
					return true;
				}

				return false;


			}
		}
	}

}
