<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
use Payplug\PayplugWoocommerce\Controller\WC_Payplug_Intent_Controller as PayplugIntent;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use WC_Payment_Tokens;
use WP_REST_Request;
use Automattic\WooCommerce\Utilities\OrderUtil;

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
	 * @var PayplugGateway
	 */
	protected $gateway;

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
		add_action( 'wc_ajax_payplug_create_intent', [$this, 'create_payment_intent']);

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

		if(is_null($order_id)){
			preg_match("/(?<=order-pay\/)\d*/", $path['path'], $matches);
			$order_id = $matches[0];
		}

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

			$order_id = $this->getOrderFromPaymentId($payment_id);
			$order       = wc_get_order($order_id);

			PayplugGateway::log(
				sprintf(
					'Order #%s : An error occurred while retrieving the payment data with the message : %s',
					$order_id,
					$e->getMessage()
				)
			);

			return wp_send_json_error( $e->getMessage());
		}

		$order_id = $this->getOrderFromPaymentId($payment_id);
		$order       = wc_get_order($order_id);
		$return_url = esc_url_raw($order->get_checkout_order_received_url());

		if ((isset($payment->failure)) && (!empty($payment->failure)) || ($payment->is_paid === false && is_null($payment->paid_at))) {

			$order->update_status( 'failed', __( 'Order cancelled by customer.', 'woocommerce' ) );

			wp_send_json_error(
				[
					'code' => isset($payment->failure->code) ? $payment->failure->code : 500,
					'message' => !empty($payment->failure->message) ? $payment->failure->message :__("payplug_integrated_payment_error", "payplug"),
					'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw())
				]
			);
		}


		wp_send_json_success(array(
			'payment_id' => $payment->id,
			'result'   => 'success',
			'redirect' => !empty($payment->hosted_payment->payment_url) ? $payment->hosted_payment->payment_url : $return_url,
			'cancel'   => !empty($payment->hosted_payment->cancel_url) ? $payment->hosted_payment->cancel_url : null
		));

	}

	/**
	 * @param $payment_id
	 * @return int|string|null
	 */
	private function getOrderFromPaymentId( $payment_id){
		global $wpdb;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders = wc_get_orders(
				array(
					'field_query' => array(
						array(
							'key'        => 'transaction_id',
							'comparison' => $payment_id
						),
					),
				)
			);
			$order = $orders[0];
			$order_id = $order->get_id();

		}else{

			$sql = "SELECT post_id
							FROM $wpdb->postmeta
								WHERE meta_key = '_transaction_id' AND meta_value = %s";
			$order_id = $wpdb->get_var(
				$wpdb->prepare(
					$sql,
					$payment_id
				)
			);
		}

		return $order_id;
	}

	public function create_payment_intent(){

		$order_id = $_POST["order_id"];
		$this->gateway = $this->get_payplug_gateway($_POST['gateway']);
		$order       = wc_get_order($order_id);
		$customer_id = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
		$return_url = esc_url_raw($order->get_checkout_order_received_url());
		$address_data = PayplugAddressData::from_order($order);
		$amount      = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total());
		$amount      = $this->gateway->validate_order_amount($amount);

		$payment_data = [
			'amount'           => $amount,
			'currency'         => get_woocommerce_currency(),
			'allow_save_card'  => $this->gateway->oneclick_available() && (int) $customer_id > 0,
			'billing'          => $address_data->get_billing(),
			'shipping'         => $address_data->get_shipping(),
			'hosted_payment'   => [
				'return_url' => $return_url,
			],
			'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
			'metadata'         => [
				'order_id'    => $order_id,
				'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
				'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
			],
		];

		if($this->gateway->id === "apple_pay"){
			unset($payment_data["allow_save_card"]);

			$payment_data["payment_method"] = $this->gateway->id;
			$payment_data["payment_context"] = array(
				'apple_pay' => array(
					'domain_name' => $this->gateway->domain_name,
					'application_data' => base64_encode(json_encode(array(
						'apple_pay_domain' => $this->gateway->domain_name,
					)))
				)
			);
			$payment_data["hosted_payment"]["cancel_url"] = esc_url_raw($order->get_cancel_order_url_raw());
			$payment_data["metadata"]["applepay_workflow"] = "checkout";
		}

		if($this->gateway->payment_method === 'integrated' && $this->gateway->id === "payplug") {
			$payment_data['initiator'] = 'PAYER';
			$payment_data['integration'] = 'INTEGRATED_PAYMENT';
			unset($payment_data['hosted_payment']['cancel_url']);
		}

		/**
		 * Filter the payment data before it's used
		 *
		 * @param array $payment_data
		 * @param int $order_id
		 * @param array $customer_details
		 * @param PayplugAddressData $address_data
		 */
		$payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);

		/**
		 *
		 */
		$payment = $this->gateway->api->payment_create($payment_data);

		// Save transaction id on the order
		PayplugWoocommerceHelper::is_pre_30() ? update_post_meta($order_id, '_transaction_id', $payment->id)  : $order->set_transaction_id($payment->id);

		if (is_callable([$order, 'save']) && $this->gateway->payment_method === "popup") {
			$order->save();
		}

		$metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
		PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

		wp_send_json_success(array(
			'payment_id' => $payment->id,
			'merchant_session' => isset($payment->payment_method["merchant_session"]) ? $payment->payment_method["merchant_session"]: null,
			'redirect' => !empty($payment->hosted_payment->payment_url) ? $payment->hosted_payment->payment_url : $return_url,
			'cancel'   => esc_url_raw($order->get_cancel_order_url_raw())
		));
	}


	/**
	 * Returns an instantiated gateway.
	 * @return PayplugGateway
	 */
	protected function get_payplug_gateway($id) {
		if ( ! isset( $this->gateway ) ) {
			$gateways      = WC()->payment_gateways()->payment_gateways();
			foreach($gateways as $gateway){
				if($gateway->id === $id){
					return $gateway;
				}
			}
		}
	}


}
