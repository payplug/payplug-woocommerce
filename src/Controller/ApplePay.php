<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;
use function is_cart;
use function is_product;

/**
 * ApplePay controller for handling Apple Pay payment logic in WooCommerce.
 */
class ApplePay extends PayplugGateway
{

	public $domain_name = "";

	protected $cart = false;

	protected $checkout = false;

	protected $carriers = [];

	const ENABLE_ON_TEST_MODE = false;

	public $image = "apple-pay-checkout.svg";

	protected $product = false;

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
		$this->enabled = "no";


		if( $this->checkApplePay() && is_admin()){
			$this->enabled = "yes";

		}else if( $this->checkApplePay() && $this->isSSL()  ){

			if (!is_admin() && $this->get_button_checkout()) {
				$this->enabled = 'yes';
			}


			if( !is_admin() ){

				if (!PayplugWoocommerceHelper::is_checkout_block() && $this->get_button_checkout()) {
					$this->add_apple_pay_css();
					add_action('wp_enqueue_scripts', [$this, 'add_apple_pay_js']);
				}

				if ( $this->get_button_cart() && !PayplugWoocommerceHelper::is_cart_block() && !PayplugWoocommerceHelper::is_subscription() ) {
					$this->enabled = 'yes';
					$this->add_apple_pay_css();
					add_action('woocommerce_proceed_to_checkout', [$this, "add_apple_pay_cart_js"], 15);
				}

				if ($this->get_button_product() && !PayplugWoocommerceHelper::is_product_block()) {
					$this->enabled ='yes';
					$this->add_apple_pay_css();
					add_action('woocommerce_after_add_to_cart_button', [$this, "add_apple_pay_product_js"], 15);
				}
			}
		}

	}

	/**
     * Processes admin options for Apple Pay settings.
     *
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
     * Checks if Apple Pay is authorized and available.
     *
     * @return bool
     */
	public function checkApplePay(){
		$options = $this->settings;


		//check if module is enabled
		if(!empty($options['enabled']) && 'no' === $options['enabled']){
			return false;
		}

		//it's disabled
		if(isset($options['apple_pay']) && $options['apple_pay'] === "no"){
			return false;
		}

		//Amount validations
		if ( is_cart() && !empty( WC()->cart ) ) {
			$order_amount = (float) WC()->cart->total;
			if ($order_amount < self::MIN_AMOUNT || $order_amount > self::MAX_AMOUNT) {
				return false;
			}
		}

		//support legacy applepay
		if( !isset($options['applepay_checkout']) && !isset($options['applepay_cart']) && !isset($options['applepay_product']) && isset($options['apple_pay']) && $options['apple_pay'] ==="yes"){
			$this->set_button_checkout(true);
		}

		if(isset($options['applepay_checkout']) && $options['applepay_checkout'] === "yes"){
			$this->set_button_checkout(true);
		}

		if(isset($options['applepay_cart']) && $options['applepay_cart'] === "yes"){
			$this->set_button_cart(true);
		}

		if(isset($options['applepay_product']) && $options['applepay_product'] === "yes"){
			$this->set_button_product(true);
		}

		if(isset($options['applepay_carriers']) ){
			$this->set_carriers($options['applepay_carriers']);
		}

		$account = PayplugWoocommerceHelper::generic_get_account_data_from_options($this->id);
		//no auth
		if(!isset($account['payment_methods']['apple_pay']) || !isset($account['payment_methods']['apple_pay']['allowed_domain_names'])  ){
			return false;
		}

		//$account has permissions to use apple_pay
		$auth = isset($account['payment_methods']['apple_pay']['enabled']) && $account['payment_methods']['apple_pay']['enabled'];
		$domain = parse_url(get_site_url());
		$auth_domains = in_array($domain["host"], $account['payment_methods']['apple_pay']['allowed_domain_names']);

		//lost auth
		if(!($auth && $auth_domains)){
			return false;
		}

		return true;
	}

	/**
     * Outputs the payment fields on the checkout page.
     *
     * @return void
     */
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

	/**
     * Enqueues Apple Pay scripts for the cart page.
     *
     * @return void
     */
	public function add_apple_pay_cart_js(){
		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js', array(), false, true );
		wp_enqueue_script('payplug-apple-pay-cart', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay-cart.js', ['jquery', 'apple-pay-sdk'], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script( 'payplug-apple-pay-cart', 'apple_pay_params',
			array(
				'ajax_url_applepay_get_shippings' => \WC_AJAX::get_endpoint('applepay_get_shippings'),
				'ajax_url_place_order_with_dummy_data' => \WC_AJAX::get_endpoint('place_order_with_dummy_data'),
				'ajax_url_update_applepay_order' => \WC_AJAX::get_endpoint('update_applepay_order'),
				'ajax_url_update_applepay_payment' => \WC_AJAX::get_endpoint('update_applepay_payment'),
				'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
				'ajax_url_applepay_cancel_order' => \WC_AJAX::get_endpoint('applepay_cancel_order'),
				'is_cart' => is_cart(),
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
     * Enqueues Apple Pay scripts for the product page.
     *
     * @return void
     */
	public function add_apple_pay_product_js(){
		global $product;
		// Only dispay ApplePay on product page for simple and variable products
		if ($product->get_type() != "simple" && $product->get_type() != "variable") {
			return;
		}

		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js', array(), false, true );
		wp_enqueue_script('payplug-apple-pay-product', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay-product.js', ['jquery', 'apple-pay-sdk'], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script( 'payplug-apple-pay-product', 'apple_pay_params',
			array(
				'ajax_url_applepay_get_shippings' => \WC_AJAX::get_endpoint('applepay_get_shippings'),
				'ajax_url_place_order_with_dummy_data' => \WC_AJAX::get_endpoint('place_order_with_dummy_data'),
				'ajax_url_update_applepay_order' => \WC_AJAX::get_endpoint('update_applepay_order'),
				'ajax_url_update_applepay_payment' => \WC_AJAX::get_endpoint('update_applepay_payment'),
				'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
				'ajax_url_applepay_cancel_order' => \WC_AJAX::get_endpoint('applepay_cancel_order'),
				'ajax_url_applepay_empty_cart' => \WC_AJAX::get_endpoint('applepay_empty_cart'),
				'ajax_url_applepay_add_to_cart' => \WC_AJAX::get_endpoint('applepay_add_to_cart'),
				'is_product' => is_product(),
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

		if( empty($chosen_method) ){
			$chosen_method = !empty(WC()->session->chosen_shipping_methods[0]) ? WC()->session->chosen_shipping_methods[0] : null;
		}

		if(!$this->is_shipping_required() || is_product()){
			return true;
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
	 *
	 * check if the shipping is required or not
	 * @return bool
	 */
	public function is_shipping_required(){
		$cart = WC()->cart->get_cart();

		$required = true;
		foreach ($cart as $cart_item){
			if(!empty($cart_item['product_id']) ){
				$product = wc_get_product( $cart_item['product_id'] );

				//not required if it enters here
				if(!$product->is_virtual() && !$product->is_downloadable()){
					return true;
				}

				$required = false;
			}
		}

		return $required;

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
     * Checks if the current connection is using SSL.
     *
     * @return bool
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
     * Enqueues Apple Pay CSS styles.
     *
     * @return void
     */
	public function add_apple_pay_css() {
		wp_enqueue_style('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-apple-pay.css', [], PAYPLUG_GATEWAY_VERSION);
	}

	/**
     * Enqueues Apple Pay JavaScript for the checkout page.
     *
     * @return void
     */
	public function add_apple_pay_js() {
		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js', array(), false, true );
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
				"is_checkout" => is_checkout(),
				'apple_pay_domain' => $this->domain_name
			)
		);
	}

	/**
     * Gets the Apple Pay payment icon HTML.
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
	 * Processes a payment if it was already generated by an intent.
     * @param $order
	 * @return array|null
	 * @throws \Exception
	 */
	private function process_standard_intent_payment($order){

		if ( !is_wc_endpoint_url('order-pay') &&
			PayplugWoocommerceHelper::is_checkout_block() &&
			!empty($order->get_transaction_id()) ) {

			$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();

			try {
				$payment = $this->api->payment_retrieve($order->get_transaction_id());
				if (ob_get_length() > 0) {
					ob_clean();
				}

				$return_url = esc_url_raw($order->get_checkout_order_received_url());

				wp_send_json_success(
					array(
						'payment_id' => $payment->id,
						'result' => 'success',
						'redirect' => !empty($payment->hosted_payment->payment_url) ? $payment->hosted_payment->payment_url : $return_url,
						'cancel' => !empty($payment->hosted_payment->cancel_url) ? $payment->hosted_payment->cancel_url : null
					)
				);

				return array("stt" => "OK");

			} catch (HttpException $e) {
				PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			} catch (\Exception $e) {
				PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			}
		}

		return null;
	}


	/**
     * Processes a standard Apple Pay payment.
     *
	 * @param \WC_Order $order
	 * @param int $amount
	 * @param int $customer_id
     * @param string $workflow
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function process_standard_payment($order, $amount, $customer_id, $workflow = 'checkout')
	{

		$intent = $this->process_standard_intent_payment($order);
		if( !empty($intent) ){
			return $intent;
		}

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

			if (PayplugWoocommerceHelper::is_checkout_block() && is_checkout()) {
				$payment_data['metadata']['woocommerce_block'] = "CHECKOUT";

			} elseif (PayplugWoocommerceHelper::is_cart_block() && is_cart()) {
				$payment_data['metadata']['woocommerce_block'] = "CART";
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

	/**
     * Sets the checkout button status.
     *
     * @param bool $status
     * @return void
     */
	private function set_button_checkout($status){
		$this->checkout = $status;
	}

	/**
     * Sets the cart button status.
     *
     * @param bool $status
     * @return void
     */
	private function set_button_cart($status){
		$this->cart = $status;
	}

	/**
     * Sets the product button status.
     *
     * @param bool $status
     * @return void
     */
	private function set_button_product($status){
		$this->product = $status;
	}

	/**
     * Gets the checkout button status.
     *
     * @return bool
     */
	private function get_button_checkout(){
		return $this->checkout;
	}

	/**
     * Gets the cart button status.
     *
     * @return bool
     */
	public function get_button_cart(){
		return $this->cart;
	}

	/**
     * Gets the product button status.
     *
     * @return bool
     */
	public function get_button_product(){
		return $this->product;
	}

	/**
     * Gets the list of allowed carriers for Apple Pay.
     *
     * @return array
     */
	public function get_carriers(){
		return $this->carriers;
	}

	/**
     * Sets the list of allowed carriers for Apple Pay.
     *
     * @param array $carriers
     * @return void
     */
	private function set_carriers($carriers){
		$this->carriers = $carriers;
	}

}
