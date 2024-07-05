<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;
use function is_cart;
use function is_checkout;

class ApplePay extends PayplugGateway
{

	protected $domain_name = "";

	protected $cart=false;

	protected $checkout = false;

	protected $carriers = [];

	public function __construct()
	{

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id = 'apple_pay';

		/** @var \WC_Payment_Gateway overwrite for apple pay settings */
		$this->method_title = __('payplug_apple_pay_title', 'payplug');
		$this->method_description = "";
		$this->has_fields = false;

		$this->title = __('payplug_apple_pay_title', 'payplug');
		$this->description = '<div id="apple-pay-button-wrapper"><apple-pay-button buttonstyle="black" type="pay" locale="'. get_locale() .'"></apple-pay-button></div>';
		$this->domain_name = $_SERVER['HTTP_HOST'];
		//TODO : to rollback when blocks apple pay finished
		$this->enabled = "yes";


		if( $this->checkApplePay() && is_admin()){
			$this->enabled = "yes";

		}else if( $this->checkApplePay() && $this->checkDeviceComptability() && $this->isSSL()  ){
			if (!is_admin() && is_checkout() && $this->get_button_checkout()) {
				$this->enabled = 'yes';
				$this->add_apple_pay_css();
				add_action('wp_enqueue_scripts', [$this, 'add_apple_pay_js']);
			}

			if (!is_admin() && is_cart() && $this->get_button_cart()) {
				$this->enabled = 'yes';
				$this->add_apple_pay_css();
				add_action('woocommerce_proceed_to_checkout', [$this, "add_apple_pay_cart_js"], 15);

			}
		}

	}

	/**
	 * @return bool|void
	 */
	public function process_admin_options() {
		$data = $this->get_post_data();
		if (isset($data['woocommerce_payplug_mode'])) {
			if ( $this->get_post_data()['woocommerce_payplug_mode'] === '0' ) {
				$options              = get_option( 'woocommerce_payplug_settings', [] );
				$options['apple_pay'] = 'no';
				update_option( 'woocommerce_payplug_settings', apply_filters( 'woocommerce_settings_api_sanitized_fields_payplug', $options ) );
			}
		}
		if (isset($data['woocommerce_payplug_apple_pay'])) {
			if (($data['woocommerce_payplug_apple_pay'] == 1) && (!$this->checkApplePay())) {
				add_action( 'admin_notices', [$this ,"display_notice"] );
			}
		}

	}

	/**
	 *
	 * Check Apple Pay Authorization
	 *
	 * @return bool
	 */

	private function checkApplePay(){
		//TODO : to rollback when blocks apple pay finished
		return true;
		$account = PayplugWoocommerceHelper::generic_get_account_data_from_options($this->id);
		$options = PayplugWoocommerceHelper::get_payplug_options();

		//it's disabled
		if(isset($options['apple_pay']) && $options['apple_pay'] === "no"){
			return false;
		}

		if ( is_cart() && !empty( WC()->cart ) ) {
			$order_amount = (float) WC()->cart->total;
			if ($order_amount < self::MIN_AMOUNT || $order_amount > self::MAX_AMOUNT) {
				return false;
			}
		}

		//support legacy applepay
		if( !isset($options['applepay_checkout']) && !isset($options['applepay_cart']) && isset($options['apple_pay']) && $options['apple_pay'] ==="yes"){
			$this->set_button_checkout(true);
		}

		if(isset($options['applepay_checkout']) && $options['applepay_checkout'] === "yes"){
			$this->set_button_checkout(true);
		}

		if(isset($options['applepay_cart']) && $options['applepay_cart'] === "yes"){
			$this->set_button_cart(true);
		}

		if(isset($options['applepay_carriers']) ){
			$this->set_carriers($options['applepay_carriers']);
		}

		//no auth
		if(!isset($account['payment_methods']['apple_pay']) || !isset($account['payment_methods']['apple_pay']['allowed_domain_names'])  ){
			return false;
		}

		//$account has permissions to use apple_pay
		$auth = isset($account['payment_methods']['apple_pay']['enabled']) && $account['payment_methods']['apple_pay']['enabled'];
		$auth_domains = in_array(strtr(get_site_url(), array("http://" => "", "https://" => "")), $account['payment_methods']['apple_pay']['allowed_domain_names']);
		$accepted_domain = in_array($this->domain_name, $account['payment_methods']['apple_pay']['allowed_domain_names']);

		$account_auth = $auth && $auth_domains && $accepted_domain;

		//lost auth
		if(!$account_auth){
			$options['apple_pay'] = "no";
			update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
			return false;
		}

		return true;
	}

	public function payment_fields()
	{
		$description = $this->get_description();

		if (!empty($description)) {
			echo wpautop(wptexturize($description));
		}
	}

	/**
	 * extend the woocommmerce get description to include personalized html
	 *
	 * @return mixed|string|null
	 */
	public function get_description()
	{
		return apply_filters('woocommerce_gateway_description', $this->description, $this->id);
	}
	public function add_apple_pay_cart_js(){
		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
		wp_enqueue_script('payplug-apple-pay-cart', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay-cart.js', ['jquery', 'apple-pay-sdk'], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script( 'payplug-apple-pay-cart', 'apple_pay_params',
			array(
				'ajax_url_applepay_get_shippings' => \WC_AJAX::get_endpoint('applepay_get_shippings'),
				'ajax_url_place_order_with_dummy_data' => \WC_AJAX::get_endpoint('place_order_with_dummy_data'),
				'ajax_url_update_applepay_order' => \WC_AJAX::get_endpoint('update_applepay_order'),
				'ajax_url_update_applepay_payment' => \WC_AJAX::get_endpoint('update_applepay_payment'),
				'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
				'ajax_url_applepay_cancel_order' => \WC_AJAX::get_endpoint('applepay_cancel_order'),

				'countryCode' => WC()->customer->get_billing_country(),
				'currencyCode' => get_woocommerce_currency(),
				'apple_pay_domain' => $_SERVER['HTTP_HOST']
			)
		);

		if($this->checkButtonVisibility()){
			echo $this->get_description();
		}
	}

	/**
	 * Check if the shipping address is a carrier
	 *
	 * @return bool
	 */
	private function checkButtonVisibility(){
		$apple_carriers = $this->get_carriers();
		$allowed = false;
		$post = $this->get_post_data();
		$chosen_method = isset($post["shipping_method"][0]) ? $post["shipping_method"][0] : null;
		if(!$chosen_method){
			$chosen_method = WC()->session->chosen_shipping_methods[0];
		}

		foreach ( WC()->shipping()->get_packages() as $i => $package ) {
			$available_rates = !empty($package['rates']) ? $package['rates'] : [];
			if(!empty($available_rates)){
				foreach($available_rates as $method){
					if(in_array($method->get_method_id(), $apple_carriers) ){
						if($chosen_method === $method->get_method_id() . ":" . $method->get_instance_id()){
							$allowed = true;
						}
					}
				}
			}
		}

		return $allowed;
	}


	/**
	 * Display unauthorized error
	 *
	 * @return void
	 */

	public static function display_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo __( 'payplug_apple_pay_unauthorized_error', 'payplug' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Check User-Agent to make sure it is on Mac OS
	 *
	 * @return bool
	 */
	public function checkDeviceComptability(){
		$user_agent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER['HTTP_USER_AGENT'] : '';

		// Check if the Browser is Safari
		if (stripos( $user_agent, 'Chrome') !== false) {
			return false;

		} elseif (stripos( $user_agent, 'Safari') !== false) {
			// Check if the OS is Mac
			if(!preg_match('/macintosh|mac os x/i', $user_agent)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if SSL
	 *
	 */
	public function isSSL()
	{
		if( !empty( $_SERVER['HTTPS'] ) ) {
			if ( 'on' == strtolower($_SERVER['HTTPS']) )
				return true;
		} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}

		if( !empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' )
			return true;

		return false;
	}

	/**
	 * Add CSS
	 *
	 */
	public function add_apple_pay_css() {
		wp_enqueue_style('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-apple-pay.css', [], PAYPLUG_GATEWAY_VERSION);
	}

	/**
	 * Add JS
	 *
	 */
	public function add_apple_pay_js() {
		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
			wp_enqueue_script('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay.js',
				[
					'jquery',
					'apple-pay-sdk'
				], PAYPLUG_GATEWAY_VERSION, true);
			wp_localize_script( 'payplug-apple-pay', 'apple_pay_params',
				array(
					'ajax_url_payplug_create_order' => \WC_AJAX::get_endpoint('payplug_create_order'),
					'ajax_url_applepay_update_payment' => \WC_AJAX::get_endpoint('applepay_update_payment'),
					'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
					'countryCode' => WC()->customer->get_billing_country(),
					'currencyCode' => get_woocommerce_currency(),
					'total'  => WC()->cart->total,
					'apple_pay_domain' => $this->domain_name
				)
			);
	}

	/**
	 * Get payment icons.
	 *
	 * @return string
	 */
	public function get_icon()
	{
		$available_img = 'apple-pay-checkout.svg';
		$icons = apply_filters('payplug_payment_icons', [
			'payplug' => sprintf('<img src="%s" alt="Apple Pay" class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $available_img)),
		]);
		$icons_str = '';
		foreach ($icons as $icon) {
			$icons_str .= $icon;
		}
		return $icons_str;
	}


	/**
	 * @param \WC_Order $order
	 * @param int $amount
	 * @param int $customer_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function process_standard_payment($order, $amount, $customer_id, $workflow = 'checkout')
	{
		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		try {
			$address_data = PayplugAddressData::from_order($order);

			$return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(substr( $return_url, 0, 4 ) === "http")) {
				$return_url = get_site_url().$return_url;
			}

			// delivery_type must be removed in Apple Pay
			$billing = $address_data->get_billing();
			unset($billing['delivery_type']);
			$shipping = $address_data->get_shipping();
			unset($shipping['delivery_type']);

			$payment_data = [
				'amount'           => $amount,
				'currency'         => get_woocommerce_currency(),
				'payment_method' => $this->id,
				'payment_context' => array(
					'apple_pay' => array(
						'domain_name' => $this->domain_name,
						'application_data' => base64_encode(json_encode(array(
							'apple_pay_domain' => $this->domain_name,
						)))
					)
				),
				'billing'          => $billing,
				'shipping'         => $shipping,
				'hosted_payment'   => [
					'return_url' => $return_url,
					'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
				],
				'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
				'metadata'         => [
					'order_id'    => $order_id,
					'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
					'domain'      => $this->domain_name,
					'applepay_workflow' => $workflow
				]
			];

			/**
			 * Filter the payment data before it's used
			 *
			 * @param array $payment_data
			 * @param int $order_id
			 * @param array $customer_details
			 * @param PayplugAddressData $address_data
			 */
			$payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
			$payment      = $this->api->payment_create($payment_data);

			// Save transaction id for the order
			PayplugWoocommerceHelper::is_pre_30()
				? update_post_meta($order_id, '_transaction_id', $payment->id)
				: $order->set_transaction_id($payment->id);

			if (is_callable([$order, 'save'])) {
				$order->save();
			}

			/**
			 * Fires once a payment has been created.
			 *
			 * @param int $order_id Order ID
			 * @param PaymentResource $payment Payment resource
			 */
			\do_action('payplug_gateway_payment_created', $order_id, $payment);

			$metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
			PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

			PayplugGateway::log(sprintf('Payment creation complete for order #%s', $order_id));

			return [
				'result'   => 'success',
				'merchant_session' => $payment->payment_method["merchant_session"],
				'payment_id' => $payment->id,
				'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
				'return_url' => $return_url,
			];

		} catch (HttpException $e) {
			PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
			if($workflow === "cart"){
				wp_send_json_error(["code" => $e->getCode(), "msg" => __('Payment processing failed. Please retry.', 'payplug'), "order_id" => $order_id ]);
			}
			throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
		} catch (\Exception $e) {
			PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
			if($workflow === "cart"){
				wp_send_json_error(["code" => $e->getCode(), "msg" => __('Payment processing failed. Please retry.', 'payplug'), "order_id" => $order_id ]);
			}
			throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
		}

	}

	private function set_button_checkout($status){
		$this->checkout = $status;
	}

	private function set_button_cart($status){
		$this->cart = $status;
	}

	private function get_button_checkout(){
		return $this->checkout;
	}

	private function get_button_cart(){
		return $this->cart;
	}

	private function get_carriers(){
		return $this->carriers;
	}

	private function set_carriers($carriers){
		$this->carriers = $carriers;
	}


}
