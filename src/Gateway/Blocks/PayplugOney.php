<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugOney extends PayplugGenericBlock {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "oney_x3_with_fees";

	protected $icon = 'x3_with_fees.svg';

	protected $cart;

	protected $total_price;


	public function is_active() {
		return true;
	}

	public function check_oney() {


		$products_qty = (int) $this->cart->cart_contents_count;
		// Min and max
		if ( $this->total_price < $this->gateway->oney_thresholds_min || $this->total_price > $this->gateway->oney_thresholds_max ) {
			$this->description = [
				'text'  => sprintf( __( 'The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug' ), $this->gateway->oney_thresholds_min, $this->gateway->oney_thresholds_max ),
				'class' => 'payment_method_oney_x3_with_fees_disabled'
			];

			return false;
		}

		// Cart check
		if ( $products_qty >= $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM ) {
			$this->description = [
				'text'  => sprintf( __( 'The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug' ), $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM ),
				'class' => 'payment_method_oney_x3_with_fees_disabled'
			];

			return $this->gateway::ONEY_UNAVAILABLE_CODE_CART_SIZE_TOO_HIGH;
		}

		// Country check
		if ( empty( $country_code_shipping ) || empty( $country_code_shipping ) ) {
			$country_code_shipping = WC()->customer->get_shipping_country();
			$country_code_billing  = WC()->customer->get_billing_country();
		}

		//if shipping is different from billing and billing is accepted
		if ( ! $this->gateway->validate_shipping_billing_country( $country_code_shipping, $country_code_billing ) ) {
			$this->description = [
				'text'  => __( 'Unavailable for the specified country.', 'payplug' ),
				'class' => 'payment_method_oney_x3_with_fees_disabled'
			];

			return false;
		}

		return true;
	}

	public function oney_enabled() {

		$data                  = parent::get_payment_method_data();
		$data['icon']          = [
			'src' => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/'.$this->icon),
			'class' => 'payplug-payment-icon',
			'alt' => $this->gateway->title
		];
		$data['description']   = $this->gateway->description;
		$data['oney_response'] = $this->gateway->api->simulate_oney_payment($this->total_price, 'with_fees');
		$data['currency']      = get_woocommerce_currency_symbol( get_option( 'woocommerce_currency' ) );

		$data['translations']['bring']               = __( 'Bring', 'payplug' );
		$data['translations']['oney_financing_cost'] = __( 'oney_financing_cost', 'payplug' );
		$data['translations']['1st monthly payment'] = __( '1st monthly payment', 'payplug' );
		$data['translations']['2nd monthly payment'] = __( '2nd monthly payment', 'payplug' );
		$data['translations']['oney_total']          = __( 'oney_total', 'payplug' );

		return $data;
	}

	public function oney_disabled() {
		$data                     = parent::get_payment_method_data();
		$disable                  = 'disable-checkout-icons';
		$available_img            = 'x3_with_fees.svg';
		$data['icon']['src']      = esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $available_img );
		$data['icon']['class']    = "payplug-payment-icon ' . $disable . '";
		$data['icon']['icon_alt'] = $this->gateway->title;
		$data['description']      = $this->description;

		return $data;
	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {

		if ( $this->check_oney() == true ) {
			return $this->oney_enabled();
		} else {
			return $this->oney_disabled();
		}
	}


}
