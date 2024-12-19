<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugOney extends PayplugGenericBlock {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "oney";

	protected $icon = '';

	protected $cart;

	protected $total_price;

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {

		$data  = parent::get_payment_method_data();

		if ( is_checkout() ) {
			$this->cart = WC()->cart;
			$this->total_price = floatval( WC()->cart->total );
		}

		if ( PayplugWoocommerceHelper::is_cart_block() && is_cart() ) {
			$data['oney_cart_label'] = __( 'OR PAY IN', 'payplug' );
			if ($this->gateway->settings['oney_type'] == 'without_fees') {
				$data['oney_cart_logo'] = esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/Oneywithoutfees3x4x.png' );
			} else {
				$data['oney_cart_logo'] = esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/lg-3x4xoney.png' );
			}
		}

		$data['icon']          = [
			'src'   => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->icon ),
			'class' => 'payplug-payment-icon',
			'alt'   => $this->gateway->title
		];
		$data['description']   = $this->gateway->description;
		$data['oney_response'] = $this->gateway->api->simulate_oney_payment( $this->total_price, 'with_fees' );

		$data['currency'] = get_woocommerce_currency_symbol( get_option( 'woocommerce_currency' ) );

		$data['translations']['bring']               = __( 'Bring', 'payplug' );
		$data['translations']['oney_financing_cost'] = __( 'oney_financing_cost', 'payplug' );
		$data['translations']['1st_monthly_payment'] = __( '1st monthly payment', 'payplug' );
		$data['translations']['2nd_monthly_payment'] = __( '2nd monthly payment', 'payplug' );
		$data['translations']['oney_total']          = __( 'oney_total', 'payplug' );
		$data['allowed_country_codes']               = $this->gateway->allowed_country_codes;

		$data['requirements'] = [
			'max_quantity'          => $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM,
			'max_threshold'         => $this->gateway->oney_thresholds_max * 100,
			'min_threshold'         => $this->gateway->oney_thresholds_min * 100,
			'allowed_country_codes' => $this->gateway->allowed_country_codes
		];

		return $data;

	}

}
