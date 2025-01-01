<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Controller\IntegratedPayment;
use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugCreditCard extends PayplugGateway {

	public $oneclick = false;

	public function __construct() {
		parent::__construct();

		$this->id                 = 'payplug';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = _x('PayPlug', 'Gateway method title', 'payplug');
		$this->method_description = __('Enable PayPlug for your customers.', 'payplug');
		$this->new_method_label   = __('Pay with another credit card', 'payplug');
		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option('description');
		$this->oneclick       = (('yes' === $this->get_option('oneclick', 'no')) && (is_user_logged_in()));
		$this->payment_method = $this->get_option('payment_method');
		$this->supports           = array(
			'products',
			'refunds',
			'tokenization',
		);

		// Ensure the description is not empty to correctly display users's save cards
		if (empty($this->description) && 0 !== count($this->get_tokens()) && $this->oneclick_available()) {
			$this->description = ' ';
		}

		if ('test' === $this->mode) {
			$this->description .= " \n";
			$this->description .= __('You are in TEST MODE. In test mode you can use the card 4242424242424242 with any valid expiration date and CVC.', 'payplug');
			$this->description = trim($this->description);
		}

		//add fields of IP to the description
		if($this->payment_method === 'integrated'){
			$this->has_fields = true;
		}

		$this->enabled = $this->settings[$this->id];

		add_action('wp_enqueue_scripts', [$this, 'scripts']);


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
			( $this->payment_method == "integrated" && !PayplugWoocommerceHelper::is_checkout_block() ) ||
			($this->payment_method == "integrated" && is_wc_endpoint_url('order-pay') )
		) {
			$this->integrated_payments_scripts();
		}

		if (($this->payment_method == "popup" ) && ($this->id === "payplug" || $this->id === "american_express") && !PayplugWoocommerceHelper::is_checkout_block() ) {
			$this->popup_payments_scripts();

		}

	}


	/**
	 * Integrated payment form scripts.
	 *
	 * Register scripts and additionnal data needed for the
	 * embedded payment form.
	 */
	public function integrated_payments_scripts(){

		$translations = array(
			"cardholder" =>  __('payplug_integrated_payment_cardholder', 'payplug'),
			"your_card" =>  __('payplug_integrated_payment_your_card', 'payplug'),
			"card_number" =>  __('payplug_integrated_payment_card_number', 'payplug'),
			"expiration_date" =>  __('payplug_integrated_payment_expiration_date', 'payplug'),
			"cvv" =>  __('payplug_integrated_payment_cvv', 'payplug'),
			"one_click" =>  __('payplug_integrated_payment_oneClick', 'payplug'),
			'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
			'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
			'nonce'    =>  wp_create_nonce('woocommerce-process_checkout'),
			'mode' => PayplugWoocommerceHelper::check_mode(), // true for TEST, false for LIVE
			'check_payment_url' => \WC_AJAX::get_endpoint('payplug_check_payment')
		);

		/**x
		 * Integrated payments scripts
		 */
		wp_enqueue_style('payplugIP', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-integrated-payments.css', [], PAYPLUG_GATEWAY_VERSION);

		wp_register_script('payplug-domain', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-domain.js', [], 'v1.0');
		wp_enqueue_script('payplug-domain');

		wp_register_script('payplug-integrated-payments-api', 'https://cdn-qa.payplug.com/js/integrated-payment/v1@1/index.js', [], 'v1.1', true);
		wp_enqueue_script('payplug-integrated-payments-api');

		wp_register_script( 'jquery-bind-first', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/jquery.bind-first-0.2.3.min.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_script('jquery-bind-first');

		wp_register_script('payplug-integrated-payments', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-integrated-payments.js', ['jquery', 'jquery-bind-first', 'payplug-integrated-payments-api'], 'v1.1', true);
		wp_enqueue_script('payplug-integrated-payments');

		wp_localize_script( 'payplug-integrated-payments', 'payplug_integrated_payment_params', $translations);
	}

	/**
	 * popup payment form scripts.
	 *
	 * Register scripts and additionnal data needed for the
	 * embedded payment form.
	 */
	public function popup_payments_scripts(){
		//load popup features
		wp_register_script('payplug', 'https://api.payplug.com/js/1/form.latest.js', [], null, true);
		wp_register_script('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-checkout.js', [ 'jquery', 'payplug' ], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script('payplug-checkout', 'payplug_checkout_params', [
			'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
			'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
			'nonce'    => [
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

		if(($this->payment_method === 'integrated') ){
			echo IntegratedPayment::template_form($this->oneclick);
		}

		if ($this->oneclick_available()) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}
	}
}
