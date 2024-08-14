<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class WooCommerce_Example_Plugin_Integration implements IntegrationInterface
{
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'payplug';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {

		$script_path       = '/assets/js/payplug-integrated-payments.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/blocks/frontend/wc-payplug-'.$this->get_name().'-blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require( $script_asset_path ) : array('dependencies' => array(), 'version' => '1.0.0');
		$script_url        = PAYPLUG_GATEWAY_PLUGIN_URL. $script_path;

		/**
		 * The assets linked below should be a path to a file, for the sake of brevity
		 * we will assume \WooCommerce_Example_Plugin_Assets::$plugin_file is a valid file path
		 */

		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => "1.1",
			);

		wp_register_script(
			'wc-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'wc-blocks-integration' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'wc-blocks-integration' );
	}

	public function get_script_data()
	{
		$woocommerce_example_plugin_data = [];
		return [
			'expensive_data_calculation' => $woocommerce_example_plugin_data
		];	}
}
