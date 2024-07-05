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
	protected $name = 'payplug';

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
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
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
			'wc-payplug-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		return [ 'wc-payplug-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->gateway->method_title,
			'name'       => $this->gateway->id,
			'description' => $this->gateway->new_method_label,
			'local' => get_locale(),
			'icon' => $this->gateway->get_icon()
		];
	}
}
