<?php
namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Controller\ApplePay as Gateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use function is_cart;
use function is_checkout;
use function is_product;

class ApplePay {

	public function __construct() {
		add_action( 'woocommerce_after_cart_totals', [ $this, 'applepayButton' ] );
		add_action( 'wc_ajax_applepay_get_shippings', [ $this, 'applepay_get_shippings' ] );
	}

	public function applepayButton() {
		if (is_cart()) {
			$apple_pay = new Gateway();
			wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
			wp_enqueue_script('payplug-apple-pay-card', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay-card.js',
				[
					'jquery',
					'apple-pay-sdk'
				], PAYPLUG_GATEWAY_VERSION, true);
			wp_localize_script( 'payplug-apple-pay-card', 'apple_pay_params',
				array(
					'ajax_url_payplug_create_order' => \WC_AJAX::get_endpoint('payplug_create_order'),
					'ajax_url_applepay_update_payment' => \WC_AJAX::get_endpoint('applepay_update_payment'),
					'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
					'ajax_url_applepay_get_shippings' => \WC_AJAX::get_endpoint('applepay_get_shippings'),
					'countryCode' => WC()->customer->get_billing_country(),
					'currencyCode' => get_woocommerce_currency(),
					'total'  => WC()->cart->total,
					'apple_pay_domain' => $_SERVER['HTTP_HOST']
				)
			);
			$apple_pay->add_apple_pay_css();
			if ($apple_pay->checkDeviceComptability()) {
				echo $apple_pay->description;
			}

		}
	}

	public function applepay_get_shippings() {
		$dummy_address = array(
			'country'   => 'FR',
			'state'     => '',
			'city'      => 'Parisy',
			'postcode'  => '12345',
			'address_1' => '123 Main St',
		);
		WC()->customer->set_shipping_country('FR');
		WC()->customer->set_shipping_city('Paris');
		WC()->customer->set_shipping_postcode('12345');
		WC()->customer->set_shipping_address('sadsa dsadsa');
	//	$available_shippings = WC()->shipping()->get_shipping_methods();
		$applepay_shippings = PayplugWoocommerceHelper::get_applepay_options()['carriers'];
		$packages = WC()->cart->get_shipping_packages();
		$available_shippings = WC()->shipping()->calculate_shipping($packages);
		$shippings = [];

		foreach ($available_shippings as $shipping) {
			array_push($shippings, [
				'identifier' => $shipping->id,
				'label' => $shipping->method_title,
				'detail' => $shipping->method_description,
				'amount' => WC()->shipping()->calculate_shipping($packages)
			]);
		}

		return $shippings;

	}

}
