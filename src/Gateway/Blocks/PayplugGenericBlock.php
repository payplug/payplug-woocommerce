<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugGenericBlock extends AbstractPaymentMethodType
{

	protected $gateway;

	protected $allowed_country_codes;

	public function initialize()
	{
		if (class_exists('WC_Blocks_Utils')) {
			if (\WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' ) ||
			    \WC_Blocks_Utils::has_block_in_page( wc_get_page_id('cart'), 'woocommerce/cart' )
			) {
				$gateways = WC()->payment_gateways->payment_gateways();
				$this->gateway = $gateways[$this->name];
			}
		}


	}

	public function is_active()
	{
		if (class_exists('WC_Blocks_Utils')) {
			if (\WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' )) {

				if(empty($this->gateway)){
					return false;
				}

				$active = false;
				if ( method_exists( $this->gateway, "is_available" ) ) {
					$active = $this->gateway->is_available();
				}

				if ( method_exists( $this->gateway, "checkGateway" ) ) {
					$active = $this->gateway->checkGateway() && $active;
				}

				return $active && $this->gateway->check_gateway( WC()->payment_gateways->payment_gateways() );
			}
		}
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
		$features = $this->gateway->supports;
		
		// Only include tokenization in supported features when order amount is between min and max allowed values
		$cart_total = WC()->cart ? WC()->cart->get_total('') : 0;
		$cart_total_cents = (int) PayplugWoocommerceHelper::get_payplug_amount($cart_total);
		$min_amount = PayplugWoocommerceHelper::get_minimum_amount();
		$max_amount = PayplugWoocommerceHelper::get_maximum_amount();
		
		if (!($cart_total_cents >= $min_amount && $cart_total_cents <= $max_amount)) {
			// Remove tokenization from supported features if outside allowed amount range
			$features = array_diff($features, ['tokenization']);
		}
		
		return $features;
	}


	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$account = PayplugWoocommerceHelper::generic_get_account_data_from_options( $this->name );
		$this->allowed_country_codes = !empty($account["payment_methods"][ $this->name ]['allowed_countries']) ? $account["payment_methods"][ $this->name ]['allowed_countries'] : null;
		
		// Only show saved cards when order amount is between min and max allowed values
		$cart_total = WC()->cart ? WC()->cart->get_total('') : 0;
		$cart_total_cents = (int) PayplugWoocommerceHelper::get_payplug_amount($cart_total);
		$min_amount = PayplugWoocommerceHelper::get_minimum_amount();
		$max_amount = PayplugWoocommerceHelper::get_maximum_amount();
		
		if ($this->gateway->settings['oneclick'] === 'yes' && $cart_total_cents >= $min_amount && $cart_total_cents <= $max_amount) {
			$oneclick = true;
		} else {
			$oneclick = false;
		}
		return [
			'enabled'     => $this->is_active(),
			'name'        => $this->gateway->id,
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'allowed_country_codes' => $this->allowed_country_codes,
			'oneclick'    => $oneclick
		];
	}

}
