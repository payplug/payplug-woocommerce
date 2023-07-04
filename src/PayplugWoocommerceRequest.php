<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use WC_Payment_Tokens;
use WP_REST_Request;

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
		add_action( 'wc_ajax_applepay_update_payment', [ $this, 'applepay_update_payment' ] );
		add_action( 'wc_ajax_applepay_get_order_totals', [ $this, 'applepay_get_order_totals' ] );
		add_action( 'wc_ajax_payplug_order_review_url', [ $this, 'ajax_create_payment' ] );
		add_action( 'wc_ajax_payplug_check_payment', [$this, 'check_payment']);

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

		$payment_method = $_POST['payment_method'];

		//TODO:: check if integrated or embedded is activated, if not go to ajax_create_order as well
		if($payment_method === 'payplug'){
			$settings = get_option('woocommerce_payplug_settings', []);
			$method = $settings['payment_method'];

			if($method !== "integrated" && $method !== "popup" ){
				$this->ajax_create_order();
			}

		}else{
			$this->ajax_create_order();
		}

		$https_referer = $_POST['_wp_http_referer'];
		$path = parse_url($https_referer);
		wp_parse_str($path['query'], $output);
		$order_id = $output['order-pay'];

		$this->process_order_payment($order_id, $payment_method);

	}

	/**
	 * wordpress class-wc-checkout.php
	 * We don't need all the other verifications, at this point customer already had the checkout and it's all valid, the order already exists
	 * We can+t use WP method because it's protected
	 * Process an order that does require payment.
	 *
	 * @since 3.0.0
	 * @param int    $order_id       Order ID.
	 * @param string $payment_method Payment method.
	 */
	protected function process_order_payment( $order_id, $payment_method ) {
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $available_gateways[ $payment_method ] ) ) {
			return;
		}

		// Store Order ID in session so it can be re-used after payment failure.
		WC()->session->set( 'order_awaiting_payment', $order_id );

		// Process Payment.
		$result = $available_gateways[ $payment_method ]->process_payment( $order_id );

		// Redirect to success/confirmation/payment page.
		if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
			$result['order_id'] = $order_id;

			$result = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );

			if ( ! wp_doing_ajax() ) {
				// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				wp_redirect( $result['redirect'] );
				exit;
			}

			wp_send_json( $result );
		}
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

	public function applepay_get_order_totals()
	{
		try {
			wp_send_json_success(WC()->cart->total);

		} catch (\Exception $e) {
			PayplugGateway::log($e->getMessage());
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

	public function check_payment() {

		global $wpdb;

		$payment_id = $_POST['payment_id'];

		if (PayplugWoocommerceHelper::check_mode()) {
			$key = PayplugWoocommerceHelper::get_live_key();
		} else {
			$key = PayplugWoocommerceHelper::get_test_key();
		}

		try {
			\Payplug\Payplug::init(array(
				'secretKey' => $key,
				'apiVersion' => "2019-08-06",
			));

			$payment =\Payplug\Payment::retrieve($payment_id);

		} catch ( \Exception $e ) {

			$order_id = $wpdb->get_var(
				$wpdb->prepare(
					"
				SELECT post_id
				FROM $wpdb->postmeta
				WHERE meta_key = '_transaction_id'
				AND meta_value = %s
				",
					$payment_id
				)
			);

			PayplugGateway::log(
				sprintf(
					'Order #%s : An error occurred while retrieving the payment data with the message : %s',
					$order_id,
					$e->getMessage()
				)
			);

			return wp_send_json_error( $e->getMessage());
		}

		if ((isset($payment->failure)) && (!empty($payment->failure)) || ($payment->is_paid === false && is_null($payment->paid_at))) {
			return wp_send_json_error(
				[
					'code' => $payment->failure->code,
					'message' => !empty($payment->failure->message) ? $payment->failure->message :__("payplug_integrated_payment_error", "payplug")
				]
			);
		}
		return wp_send_json_success($payment);

	}

}
