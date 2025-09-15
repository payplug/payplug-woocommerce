<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Model\HostedFields;
use Payplug\PayplugWoocommerce\Controller\IntegratedPayment;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugCreditCard extends PayplugGateway
{

	public $oneclick = false;

	public function __construct()
	{
		parent::__construct();

		$this->id = 'payplug';
		$this->icon = '';
		$this->has_fields = false;
		$this->method_title = _x('PayPlug', 'Gateway method title', 'payplug');
		$this->method_description = __('Enable PayPlug for your customers.', 'payplug');
		$this->new_method_label = __('Pay with another credit card', 'payplug');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->oneclick = (('yes' === $this->get_option('oneclick', 'no')) && (is_user_logged_in()));
		$this->payment_method = $this->get_option('payment_method');
		$this->supports = array(
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',

		);

		// Ensure the description is not empty to correctly display users's save cards
		if (empty($this->description) && $this->oneclick_available()) {
			$this->description = ' ';
		}

		if ('test' === $this->mode) {
			$this->description .= " \n";
			$this->description .= __('You are in TEST MODE. In test mode you can use the card 4242424242424242 with any valid expiration date and CVC.', 'payplug');
			$this->description = trim($this->description);
		}

		//add fields of IP to the description
		if ($this->payment_method === 'integrated') {
			add_action('woocommerce_after_order_notes', [$this, 'add_hftoken_field'], 10, 2);
			$this->has_fields = true;
		}

		$this->handle_cc_enabled();

		add_action('wp_enqueue_scripts', [$this, 'scripts']);
		if (PayplugWoocommerceHelper::is_subscriptions_enabled()) {
			add_action('woocommerce_scheduled_subscription_payment_' . $this->id,
				array($this, 'scheduled_subscription_payment'), 10, 2);
		}
	}

	/**
	 * Add the field to the checkout
	 */
	function add_hftoken_field($checkout)
	{

		echo '<div id="user_link_hidden_checkout_field">
	            <input type="hidden" class="input-hidden" name="hftoken" id="hftoken" >
	    	</div>';
	}

	/**
	 * if the plugin is disabled the gateways should be disabled
	 * @return mixed|string
	 */
	private function handle_cc_enabled()
	{

		if (!empty($this->settings["enabled"]) && $this->settings["enabled"] === "yes") {
			$this->enabled = !empty($this->settings[$this->id]) ? $this->settings[$this->id] : $this->settings["enabled"];
		} else {
			$this->enabled = "no";
		}

		return $this->enabled;
	}

	/**
	 * Get payment icons.
	 *
	 * @return string
	 */
	public function get_icon()
	{

		$src = ('it_IT' === get_locale())
			? PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_PostePay.svg'
			: PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg';

		$icons = apply_filters('payplug_payment_icons', [
			'payplug' => sprintf('<img src="%s" alt="Visa & Mastercard" class="payplug-payment-icon" />', esc_url($src)),
		]);

		$icons_str = '';
		foreach ($icons as $icon) {
			$icons_str .= $icon;
		}

		return $icons_str;
	}

	/**
	 * Embedded payment form scripts.
	 *
	 * Register scripts and additionnal data needed for the
	 * embedded payment form.
	 */
	public function scripts()
	{
		if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page() && !isset($_GET['change_payment_method'])) {
			return;
		}

		// If PayPlug is not enabled bail.
		if ('no' === $this->enabled) {
			return;
		}

		// If keys are not set bail.
		if (empty($this->get_api_key($this->mode))) {
			PayplugGateway::log('Keys are not set correctly.');
			return;
		}

		// Register checkout styles.
		wp_register_style('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-checkout.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_style('payplug-checkout');

		if (
			($this->payment_method == "integrated" && !PayplugWoocommerceHelper::is_checkout_block()) ||
			($this->payment_method == "integrated" && is_wc_endpoint_url('order-pay'))
		) {
			$this->integrated_payments_scripts();
		}

		if (($this->payment_method == "popup") && ($this->id === "payplug" || $this->id === "american_express") && !PayplugWoocommerceHelper::is_checkout_block()) {
			$this->popup_payments_scripts();

		}

	}

	/**
	 * Integrated payment form scripts.
	 *
	 * Register scripts and additionnal data needed for the
	 * embedded payment form.
	 */
	public function integrated_payments_scripts()
	{
		$translations = array(
			"cardholder" => __('payplug_integrated_payment_cardholder', 'payplug'),
			"your_card" => __('payplug_integrated_payment_your_card', 'payplug'),
			"card_number" => __('payplug_integrated_payment_card_number', 'payplug'),
			"expiration_date" => __('payplug_integrated_payment_expiration_date', 'payplug'),
			"cvv" => __('payplug_integrated_payment_cvv', 'payplug'),
		);

		/**x
		 * Integrated payments scripts
		 */
		wp_enqueue_style('payplugIP', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-integrated-payments.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_enqueue_style('payplug-hosted-fields-style', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-hosted-fields-payments.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_register_script('payplug-hosted-fields-payments-api', HF_API, [], 'v2.1.0', true);
		wp_enqueue_script('payplug-hosted-fields-payments-api');
		wp_register_script('payplug-hosted-fields-payments', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-hostedfields.js', ['jquery', 'jquery-bind-first', 'payplug-hosted-fields-payments-api'], 'v1.0', true);

		$hosted_fields_mid = $this->get_hosted_fields_mid();
		$hosted_fields_params = array(
			'USE_HOSTED_FIELDS' => is_array($hosted_fields_mid) && !empty($hosted_fields_mid),
			'HOSTED_FIELD_MID' => $hosted_fields_mid,
		);
		wp_localize_script('payplug-hosted-fields-payments', 'hosted_fields_params', $hosted_fields_params);
		wp_enqueue_script('payplug-hosted-fields-payments');
		wp_localize_script('payplug-hosted-fields-payments', 'payplug_integrated_payment_params', $translations);
		wp_register_script('jquery-bind-first', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/jquery.bind-first-0.2.3.min.js', array('jquery'), '1.0.0', true);
		wp_enqueue_script('jquery-bind-first');
	}

	/**
	 * popup payment form scripts.
	 *
	 * Register scripts and additionnal data needed for the
	 * embedded payment form.
	 */
	public function popup_payments_scripts()
	{
		//load popup features
		wp_register_script('payplug', 'https://api.payplug.com/js/1/form.latest.js', [], null, true);
		wp_register_script('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-checkout.js', ['jquery', 'payplug'], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script('payplug-checkout', 'payplug_checkout_params', [
			'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
			'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
			'nonce' => [
				'checkout' => wp_create_nonce('woocommerce-process_checkout'),
			],
			'is_embedded' => 'redirect' !== $this->payment_method
		]);

		wp_enqueue_script('payplug-checkout');
	}

	/**
	 * extra payment fields
	 */
	public function payment_fields()
	{
		$description = $this->get_description();

		if (!empty($description)) {
			echo wpautop(wptexturize($description));
		}

		if (($this->payment_method === 'integrated')) {
			echo HostedFields::template_form();
		}

		if ($this->oneclick_available()) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}
	}

	/**
	 * Process the subscription scheduled payment
	 */
	public function scheduled_subscription_payment($amount, $order)
	{

		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		$subscription = wcs_get_subscription($order->get_meta('_subscription_renewal'));
		$payplug_parent_meta = $subscription->get_parent()->get_meta("_payplug_metadata");

		if (!$payplug_parent_meta) {
			PayplugGateway::log('Could not find the intial payment data belong to the current user and the current subscription.', 'error');
			throw new \Exception(__('Invalid payment method.', 'payplug'));
		}

		$parent_order = $subscription->get_parent();
		$parent_tokens = $parent_order->get_payment_tokens();

		if (!empty($parent_tokens)) {
			$token = $parent_tokens[0];
		} else {
			$token = $this->api->payment_retrieve($payplug_parent_meta['transaction_id'])->card->id;
		}

		if (!$token) {
			PayplugGateway::log('Could not find the payment token or the payment doesn\'t belong to the current user.', 'error');
			throw new \Exception(__('Invalid payment method.', 'payplug'));
		}

		$amount = (int)PayplugWoocommerceHelper::get_payplug_amount($amount);

		try {
			$address_data = PayplugAddressData::from_order($order);
			$return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(substr($return_url, 0, 4) === "http")) {
				$return_url = get_site_url() . $return_url;
			}

			$payment_data = [
				'amount' => $amount,
				'currency' => get_woocommerce_currency(),
				'payment_method' => $token,
				'allow_save_card' => false,
				'billing' => $address_data->get_billing(),
				'shipping' => $address_data->get_shipping(),
				'initiator' => 'MERCHANT',
				'hosted_payment' => [
					'return_url' => $return_url,
					'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
				],
				'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
				'metadata' => [
					'order_id' => $order->get_id(),
					'customer_id' => ((int)get_current_user_id() > 0) ? get_current_user_id() : 'guest',
					'domain' => $this->limit_length(esc_url_raw(home_url()), 500),
					'woocommerce_block' => \WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout'),
					'subscription' => 'renewal'
				],
			];

			PayplugGateway::log(sprintf('Processing payment for order #%s', $order_id));
			PayplugGateway::log(sprintf('Processing payment for subscription #%s', $order->get_meta('_subscription_renewal')));

			/** This filter is documented in src/Gateway/PayplugGateway */
			$payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
			$payment = $this->api->payment_create($payment_data);

			// Save transaction id for the order
			PayplugWoocommerceHelper::is_pre_30()
				? update_post_meta($order_id, '_transaction_id', $payment->id)
				: $order->set_transaction_id($payment->id);

			if (is_callable([$order, 'save'])) {
				$order->save();
			}

			/** This action is documented in src/Gateway/PayplugGateway */
			\do_action('payplug_gateway_payment_created', $order_id, $payment);


			$metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
			PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

			$this->response->process_payment($payment,true);

			if (($payment->__get('is_paid'))) {
				$redirect = $order->get_checkout_order_received_url();
			} else if (isset($payment->__get('hosted_payment')->payment_url)) {
				$redirect = $payment->__get('hosted_payment')->payment_url;
			} else {
				$redirect = $return_url;
			}

			return [
				'payment_id' => $payment->id,
				'result' => 'success',
				'is_paid' => $payment->__get('is_paid'), // Use for path redirect before DSP2
				'redirect' => $redirect
			];
		} catch (HttpException $e) {
			PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
			throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
		} catch (\Exception $e) {
			PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
			throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
		}
	}
}
