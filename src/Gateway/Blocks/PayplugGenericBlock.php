<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class PayplugGenericBlock extends AbstractPaymentMethodType
{

	protected $gateway;

	public function initialize()
	{
		$gateways = WC()->payment_gateways->payment_gateways();
		$this->gateway = $gateways[$this->name];
	}

	public function is_active()
	{
		$active = $this->gateway->is_available();

		if(method_exists($this->gateway, "checkGateway")){
			$active = $this->gateway->checkGateway() && $active;
		}

		return $active && $this->gateway->check_gateway(WC()->payment_gateways->payment_gateways());
	}



	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$script_path       = '/assets/js/blocks/wc-payplug-'.$this->get_name().'-blocks.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/blocks/frontend/wc-payplug-'.$this->get_name().'-blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require( $script_asset_path ) : array('dependencies' => array(), 'version' => '1.0.0');
		$script_url        = PAYPLUG_GATEWAY_PLUGIN_URL. $script_path;

		wp_register_script('wc-payplug-'.$this->get_name().'-blocks', $script_url, $script_asset[ 'dependencies' ], $script_asset[ 'version' ], true );
		return [ 'wc-payplug-'.$this->get_name().'-blocks' ];
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		return $this->gateway->supports;
	}


	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		return [
			'enabled'     => $this->is_active(),
			'name'        => $this->gateway->id,
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description
		];
	}

}