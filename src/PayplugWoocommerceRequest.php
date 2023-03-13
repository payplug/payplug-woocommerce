<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use WC_Payment_Tokens;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PayplugWoocommerceRequest
 * @package Payplug\PayplugWoocommerce
 */
class PayplugWoocommerceRequest {

	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * PayplugWoocommerceRequest constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_payplug_settings', [] );
		if ( empty( $this->settings ) || ! isset( $this->settings['enabled'] ) || 'yes' !== $this->settings['enabled'] ) {
			return;
		}

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'set_session' ] );
		add_action( 'wc_ajax_payplug_create_order', [ $this, 'ajax_create_order' ] );
		add_action( 'wc_ajax_payplug_create_payment', [ $this, 'ajax_create_payment' ] );
		add_action( 'wc_ajax_applepay_update_payment', [ $this, 'applepay_update_payment' ] );
	}

	/**
	 * Sets the WC customer session if one is not set.
	 * This is needed so nonces can be verified by AJAX Request.
	 */
	public function set_session() {
		if ( ! is_product() || ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			return;
		}

		$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
		$wc_session    = new $session_class();

		if ( version_compare( WC_VERSION, '3.3', '>=' ) ) {
			$wc_session->init();
		}

		$wc_session->set_customer_session_cookie( true );
	}

	/**
	 * Create the woocommerce order in the BO
	 *
	 */
	public function ajax_create_payment() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'payplug' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		$gateway = new PayplugGateway();
		$cart = WC()->cart;
		$checkout = WC()->checkout();
		$posted_data = $checkout->get_posted_data();

		$order_id = $checkout->create_order($posted_data);
		$order = wc_get_order($order_id);
		$amount      = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total());
		$amount      = $gateway->validate_order_amount($amount);

		//TODO:: VALIDATE ORDER_ID AND ORDER
		//FIXME:: WRONG EMAIL
		//$payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
		$nonce_value    = wc_get_var( $_REQUEST['woocommerce-process-checkout-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // phpcs:ignore
		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
			//CHECK FOR VALID NONCE
			wp_send_json_error("WTFFFFFFFF");
		}

		$address_data = PayplugAddressData::from_order($order);

		$return_url = esc_url_raw($order->get_checkout_order_received_url());
		if (!(substr( $return_url, 0, 4 ) === "http")) {
			$return_url = get_site_url().$return_url;
		}

		$payment_data = [
			'amount'           => $amount,
			'currency'         => get_woocommerce_currency(),
			'allow_save_card'  => false,
			'billing'          => $address_data->get_billing(),
			'shipping'         => $address_data->get_shipping(),
			'initiator'        => 'PAYER',
			'integration'	   => 'INTEGRATED_PAYMENT',
			'hosted_payment'   => [
				'return_url' => $return_url,
			],
			'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
			'metadata'         => [
				'order_id'    => $order_id,
				'customer_id' => ((int) $order->get_customer_id() > 0) ? $order->get_customer_id() : 'guest',
				'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
			],
		];

		/** This filter is documented in src/Gateway/PayplugGateway */
		$payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
		//create payment
		$payment      = $gateway->api->payment_create($payment_data);
		wp_send_json_success( Array( "payment_id" => $payment->id, "return_url" => $return_url ) );
	}

	/**
	 * Create the woocommerce order in the BO
	 *
	 */
	public function ajax_create_order() {
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( __( 'Empty cart', 'payplug' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}

		WC()->checkout()->process_checkout();

		die( 0 );
	}

	/**
	 * Update Payplug API Payment for Apple Pay
	 */
	public function applepay_update_payment() {

		$options = get_option('woocommerce_payplug_settings', []);
		$order_id = $_POST['order_id'];
		$payment_id = $_POST['payment_id'];

		try{
			\Payplug\Payplug::init(array(
				'secretKey' => @$options['payplug_live_key'],
				'apiVersion' => "2019-08-06",
			));

			$apple_pay = array();
			$apple_pay['payment_token'] = $_POST['payment_token'];
			$payment = \Payplug\Payment::retrieve($payment_id);

			$data = array( 'apple_pay' => $apple_pay );
			$update = $payment->update($data);

			wp_send_json_success([ "result" => $update->is_paid ]);

		}catch (\Exception $e){
			wp_send_json_error($e->getMessage());
		}
	}

	/**
	 * Limit string length.
	 *
	 * @param string $value
	 * @param int $maxlength
	 *
	 * @return string
	 */
	public function limit_length($value, $maxlength = 100)
	{
		return (strlen($value) > $maxlength) ? substr($value, 0, $maxlength) : $value;
	}

}
