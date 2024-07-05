<?php

namespace Payplug\PayplugWoocommerce\Controller\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Payplug\PayplugWoocommerce\PayplugWoocommerce;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;


/**
 * Apple Pay Blocks integration
 *
 * @since 1.0.3
 */
final class ApplePayBlocks extends AbstractPaymentMethodType {

	/**
	 * plugin settings.
	 */
	protected $settings;

	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'apple_pay';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_payplug_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway = $gateways[ $this->name ];

	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return true;
	}



	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/js/frontend/blocks.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_DIR . 'js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        = PAYPLUG_GATEWAY_PLUGIN_URL . $script_path;

		wp_register_script(
			'wc-payplug-apple_pay-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		wp_enqueue_style('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-apple-pay.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
		wp_enqueue_script('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay.js',
			[
				'jquery',
				'apple-pay-sdk'
			], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script( 'payplug-apple-pay', 'apple_pay_params',
			array(
				'ajax_url_payplug_create_order' => \WC_AJAX::get_endpoint('payplug_create_order'),
				'ajax_url_applepay_update_payment' => \WC_AJAX::get_endpoint('applepay_update_payment'),
				'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
				'countryCode' => WC()->customer->get_billing_country(),
				'currencyCode' => get_woocommerce_currency(),
				'total'  => WC()->cart->total,
				'apple_pay_domain' => $_SERVER['HTTP_HOST']
			)
		);

		return [ 'wc-payplug-apple_pay-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->gateway->method_title,
			'description' => $this->gateway->description,
			'local' => get_locale()
		];
	}
}
