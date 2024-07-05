<?php

namespace Payplug\PayplugWoocommerce\Gateway\blocks;


final class CreditCart extends Payplug
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'payplug';

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$script_path       = '/js/frontend/blocks.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_URL . 'js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        = PAYPLUG_GATEWAY_PLUGIN_URL. $script_path;

		wp_register_script(
			'wc-payplug-cc-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-payplug-cc-blocks', 'woocommerce-payplug', PAYPLUG_GATEWAY_PLUGIN_DIR . 'languages/' );
		}

		return [ 'wc-payplug-cc-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       =>$this->gateway->method_title,
			"description" => $this->gateway->method_description
			//'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}


}
