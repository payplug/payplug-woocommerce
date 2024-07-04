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

		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/frontend/blocks.asset.php';
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
			'title'       => __('Pay by card in 3x with Oney', 'payplug'),
			"description" => '<p>
                    <div class="payplug-oney-flex">
                        <div>TEST TEXT</div>
                        <div>11111</div>
                    </div>
                    <div class="payplug-oney-flex">
	<small>( TEST TEXT <b>TEST TEXT</b> TAEG : <b>TEST TEXT</small>
</div>
                    <div class="payplug-oney-flex">
                        <div>TEST TEXT:</div>
                        <div>TEST TEXT</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>TEST TEXT:</div>
                        <div>TEST TEXT</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div><b>TEST TEXT</b></div>
                        <div><b>TEST TEXT</b></div>
                    </div>
                </p>'
			//'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];
	}


}
