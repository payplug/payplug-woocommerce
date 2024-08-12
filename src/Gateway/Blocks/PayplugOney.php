<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugOney extends PayplugGenericBlock {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $gatewyas_names = [
		"oney_x3_with_fees" ,
		"oney_x4_with_fees",
		"oney_x3_without_fees",
		"oney_x4_without_fees"
	];

	protected $icon = '';

	protected $cart;

	protected $total_price;

	public function initialize()
	{
		$gateways = WC()->payment_gateways->payment_gateways();
		foreach ($this->gatewyas_names as $gateway) {
			$this->name = $gateway;
			$this->gateway = $gateways[$gateway];
			parent::initialize();
		}
	}

	public function check_oney() {

		$products_qty = (int) $this->cart->cart_contents_count;
		// Min and max
		if ( $this->total_price < $this->gateway->oney_thresholds_min || $this->total_price > $this->gateway->oney_thresholds_max ) {
			$this->description = [
				'text'  => sprintf( __( 'The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug' ), $this->gateway->oney_thresholds_min, $this->gateway->oney_thresholds_max ),
				'class' => 'payment_method_' . $this->name . '_disabled'
			];

			return false;
		}

		// Cart check
		if ( $products_qty >= $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM ) {
			$this->description = [
				'text'  => sprintf( __( 'The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug' ), $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM ),
				'class' => 'payment_method_' . $this->name . '_disabled'
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
				'class' => 'payment_method_' . $this->name . '_disabled'
			];

			return false;
		}

		return true;
	}

	public function oney_enabled() {

		$data                  = parent::get_payment_method_data();
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
		$data['translations']['1st monthly payment'] = __( '1st monthly payment', 'payplug' );
		$data['translations']['2nd monthly payment'] = __( '2nd monthly payment', 'payplug' );
		$data['translations']['oney_total']          = __( 'oney_total', 'payplug' );
		$data['allowed_country_codes']               = $this->gateway->allowed_country_codes;

		$data['requirements'] = [
			'max_quantity'          => $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM,
			'max_threshold'         => $this->gateway->oney_thresholds_max * 100,
			'min_threshold'         => $this->gateway->oney_thresholds_min * 100,
			'allowed_country_codes' => $this->gateway->allowed_country_codes
		];

		$data['oney_disabled'] = $this->oney_disabled();

		return $data;
	}

	public function oney_disabled() {
		$data                     = parent::get_payment_method_data();
		$disable                  = 'disable-checkout-icons';
		$data['icon']['src']      = esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->icon );
		$data['icon']['class']    = "payplug-payment-icon ' . $disable . '";
		$data['icon']['icon_alt'] = $this->gateway->title;
		$data['validations']      = [
			'amount'      => [
				'text'  => sprintf( __( 'The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug' ), $this->gateway->oney_thresholds_min, $this->gateway->oney_thresholds_max ),
				'class' => 'payment_method_' . $this->name . '_disabled'
			],
			'country'     => [
				'text'  => __( 'Unavailable for the specified country.', 'payplug' ),
				'class' => 'payment_method_' . $this->name . '_disabled'
			],
			'items_count' => [
				'text'  => sprintf( __( 'The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug' ), $this->gateway::ONEY_PRODUCT_QUANTITY_MAXIMUM ),
				'class' => 'payment_method_' . $this->name . '_disabled'
			]
		];

		return $data;
	}

	public function get_payment_method_script_handles() {

		$script_path       = '/assets/js/blocks/wc-payplug-oney-blocks.js';
		$script_asset_path = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/blocks/frontend/wc-payplug-oney-blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require( $script_asset_path ) : array('dependencies' => array(), 'version' => '1.0.0');
		$script_url        = PAYPLUG_GATEWAY_PLUGIN_URL. $script_path;

		wp_register_script('wc-payplug-oney-blocks', $script_url, $script_asset[ 'dependencies' ], $script_asset[ 'version' ], true );
		wp_localize_script('wc-payplug-oney-blocks', 'wcPaymentGatewaysData', $this->get_payment_method_data());

		return [ 'wc-payplug-oney-blocks' ];
	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {

/*		if ( is_checkout() ) {
			$this->cart = WC()->cart;

			$this->total_price = floatval( WC()->cart->total );
		}


		return $this->oney_enabled();*/
        $a = 0;
		return [
			'oney_gateways'     => [
				'3x' => [
					'name'        => $this->gateway->id,
					'title'       => $this->gateway->title,
					'description' => $this->gateway->description
				],
				'4x' => [
					'name'        => $this->gateway->id,
					'title'       => $this->gateway->title,
					'description' => $this->gateway->description
				]
			],

		];

	}


}
