<?php

namespace Payplug\PayplugWoocommerce\Controller\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Payplug\PayplugWoocommerce\PayplugWoocommerce;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Controller\ApplePay;

/**
 * Dummy Payments Blocks integration
 *
 * @since 1.0.3
 */
final class ApplePayBlocks extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Dummy
	 */
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
		$this->settings['name'] = $this->name;
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = new ApplePay();
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
		$script_path       = 'js/frontend/blocks.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_DIR . 'js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        = PAYPLUG_GATEWAY_PLUGIN_URL . $script_path;

		wp_register_script(
			'wc-payplug-apple-pay-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'wc-payplug-apple-pay-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->gateway->method_title,
			'description' => $this->gateway->description
		];
	}
}
