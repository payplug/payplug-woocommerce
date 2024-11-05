<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugCreditCard extends PayplugGenericBlock
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "payplug";

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {

		$data = parent::get_payment_method_data();
		$data['icon'] =  [
			"src" => ('it_IT' === get_locale()) ?
				PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_PostePay.svg' :
				PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg',
			'icon_alt' => "Visa & Mastercard",
		];
		$data["IP"] = false;
		$data["popup"] = false;
		$data['payment_method'] = $this->get_name();
		$data['supports'] = $this->get_supported_features();
		$data['showSaveOption'] = !empty($this->gateway->oneclick) ? $this->gateway->oneclick : false;

		$options = get_option('woocommerce_payplug_settings', []);
		if( $options['payment_method'] === "integrated" ){
			$data["payplug_integrated_payment_cardHolder_error"] = __('payplug_integrated_payment_cardHolder_error', 'payplug');
			$data["payplug_integrated_payment_empty"] = __('payplug_integrated_payment_empty', 'payplug');
			$data["payplug_integrated_payment_your_card"] = __('payplug_integrated_payment_your_card', 'payplug');
			$data["payplug_integrated_payment_pan_error"] = __('payplug_integrated_payment_pan_error', 'payplug');
			$data["payplug_integrated_payment_exp_error"] = __('payplug_integrated_payment_exp_error', 'payplug');
			$data["payplug_integrated_payment_cvv_error"] = __('payplug_integrated_payment_cvv_error', 'payplug');
			$data["payplug_integrated_payment_error"] = __('payplug_integrated_payment_error', 'payplug');
			$data["payplug_integrated_payment_transaction_secure"] = __('payplug_integrated_payment_transaction_secure', 'payplug');
			$data['logo'] = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/logo-payplug.png';
			$data['lock'] = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/lock.svg';
			$data['payplug_integrated_payment_privacy_policy_url'] = __("payplug_integrated_payment_privacy_policy_url", "payplug");
			$data['payplug_integrated_payment_privacy_policy'] = __('payplug_integrated_payment_privacy_policy', 'payplug');
			$data["payplug_integrated_payment_cardholder"] = __('payplug_integrated_payment_cardholder', 'payplug');
			$data["payplug_integrated_payment_card_number"] = __('payplug_integrated_payment_card_number', 'payplug');
			$data["payplug_integrated_payment_expiration_date"] = __('payplug_integrated_payment_expiration_date', 'payplug');
			$data["payplug_integrated_payment_cvv"] = __('payplug_integrated_payment_cvv', 'payplug');
			$data["payplug_invalid_form"] = __('payplug_required_fields_error', 'payplug');
			$data['payplug_create_intent_payment'] = \WC_AJAX::get_endpoint('payplug_create_intent');
			$data['payplug_integrated_payment_check_payment_url'] = \WC_AJAX::get_endpoint('payplug_check_payment');
			$data['payplug_integrated_payment_nonce_field'] = wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
			$data['wp_nonce'] = wp_create_nonce( "woocommerce-process_checkout" );
			$data['mode'] = PayplugWoocommerceHelper::check_mode();
			$data["IP"] = true;

		}else if( $options['payment_method'] === "popup" ){
			$data["popup"] = true;
			$data['payplug_create_intent_payment'] = \WC_AJAX::get_endpoint('payplug_create_intent');
			$data['payplug_create_order'] = \WC_AJAX::get_endpoint('payplug_create_order');
			$data['wp_nonce'] = wp_create_nonce( "woocommerce-process_checkout" );

		}

		return $data;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {

		$options = get_option('woocommerce_payplug_settings', []);
		if( $options['payment_method'] === "integrated" && !is_wc_endpoint_url('order-pay')){
			$this->ip_scripts();
		}

		if ( $options['payment_method'] == "popup" ) {
			$this->popup_scripts();
		}

		return parent::get_payment_method_script_handles();
	}

	private function ip_scripts(){
		wp_enqueue_style('payplugIP', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-integrated-payments.css', [], PAYPLUG_GATEWAY_VERSION);
		wp_register_script(
			'payplug-integrated-payments-api',
			'https://cdn.payplug.com/js/integrated-payment/v1@1/index.js',
			array(),
			'v1.1',
			true
		);

		wp_register_script('payplug-domain', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-domain.js', [], 'v1.0');
		wp_enqueue_script('payplug-domain');

		wp_enqueue_script('payplug-integrated-payments-api');
	}

	private function popup_scripts(){
		wp_register_script('payplug-popup', 'https://api.payplug.com/js/1/form.latest.js', [], null, true);
		if (isset($_GET['pay_for_order'])) {
			wp_register_script('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-checkout.js', [
				'jquery',
				'payplug-popup'
			], PAYPLUG_GATEWAY_VERSION, true);
		}

		wp_localize_script('payplug-checkout', 'payplug_checkout_params', [
			'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
			'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
			'nonce'    => [
				'checkout' => wp_create_nonce('woocommerce-process_checkout'),
			],
			'is_embedded' => 'redirect' !== $this->gateway->payment_method
		]);

		wp_enqueue_script('payplug-popup');
		wp_enqueue_script('payplug-checkout');
	}
}
