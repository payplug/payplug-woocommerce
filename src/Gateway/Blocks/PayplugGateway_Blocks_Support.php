<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class PayplugGateway_Blocks_Support extends AbstractPaymentMethodType
{

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
	protected $name = 'payplug';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_payplug_settings' );
		$this->settings['description'] = "<h2>asdasdasd</h2>";
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$this->gateway->integrated_payments_scripts();

		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => '1.2.0'
			);
		$script_url        = untrailingslashit( plugins_url( '/', __FILE__ ) ) . $script_path;

		wp_register_script(
			'wc-dummy-payments-blocks',
			$script_url,
			$script_asset[ 'dependencies' ],
			$script_asset[ 'version' ],
			true
		);
/*
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-dummy-payments-blocks', 'woocommerce-gateway-dummy', WC_Dummy_Payments::plugin_abspath() . 'languages/' );
		}
*/
		return [ 'wc-dummy-payments-blocks' ];
	}

	public function get_icon()
	{
		$icons_src = [
			'payplug' => [
				'src' => PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg',
				'alt' => "Visa & Mastercard",
			]
		];

		return $icons_src;
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$a = $this->get_icon();

		return [
			'title'       => "FDXXXX",
			'description' => "<p>aqui esta essa cena</p>",
			'icon'       => $this->get_icon(),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		];

/*
		return array_replace_recursive( [
			'title'       => $this->get_setting( 'title' ),
			'description' => "<p>aqui esta essa cena</p>",
			'icons'       => $this->get_icon(),
			'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] )
		]
		);*/
	}


}
