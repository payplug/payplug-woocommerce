<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

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

		$data['ajax_url_applepay_get_shippings'] = \WC_AJAX::get_endpoint('applepay_get_shippings');
		$data['ajax_url_place_order_with_dummy_data'] = \WC_AJAX::get_endpoint('place_order_with_dummy_data');
		$data['ajax_url_update_applepay_order'] = \WC_AJAX::get_endpoint('update_applepay_order');
		$data['ajax_url_update_applepay_payment'] = \WC_AJAX::get_endpoint('update_applepay_payment');
		$data['ajax_url_applepay_get_order_totals'] = \WC_AJAX::get_endpoint('applepay_get_order_totals');
		$data['ajax_url_applepay_cancel_order'] = \WC_AJAX::get_endpoint('applepay_cancel_order');

		$data['countryCode'] = WC()->customer->get_billing_country();
		$data['currencyCode'] = get_woocommerce_currency();
		$data['apple_pay_domain'] = $_SERVER['HTTP_HOST'];
		$data['payplug_authorized_carriers'] = $this->gateway->get_carriers();
		$data['payplug_carriers'] = $this->get_carriers();

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

	public function get_carriers() {

		$packages = WC()->cart->get_shipping_packages();
		$shippings = [];

		foreach ( $packages as $package_key => $package ) {
			$shipping_methods = $this->get_shipping_methods_from_package($package);

			foreach ( $shipping_methods as $shipping_method ) {

				if (!$shipping_method->supports('shipping-zones') || !$shipping_method->is_enabled()) {
					continue;
				}

				$rates = $shipping_method->get_rates_for_package($package);
				if($this->checkApplePayShipping($shipping_method)){
					$shipping_rate = $rates[$shipping_method->get_rate_id()];

					array_push($shippings, [
						'identifier' => $shipping_method->id,
						'label' => $shipping_method->method_title,
						'detail' => strip_tags($shipping_method->method_description),
						'amount' =>$shipping_rate->get_cost()+$shipping_rate->get_shipping_tax()
					]);
				}
			}
		}
		return $shippings;
	}

	/**
	 * @param $shipping
	 * @return bool
	 */
	public function checkApplePayShipping($shipping = []){
		if(empty($shipping)){
			return false;
		}

		$apple_pay_options = PayplugWoocommerceHelper::get_applepay_options();
		$apple_pay_carriers = $apple_pay_options['carriers'];

		$exists = false;
		foreach($apple_pay_carriers as $carrier => $carrier_id){
			if($carrier_id === $shipping->id){
				return true;
			}
		}

		return $exists;
	}


	public function get_shipping_methods_from_package($package){
		$shipping_zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
		return $shipping_zone->get_shipping_methods( true );
	}

}
