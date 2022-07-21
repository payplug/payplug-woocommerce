<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;

class ApplePay extends PayplugGateway
{

	protected $enable = false;
	protected $domain_name = "";

	public function __construct()
	{

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id = 'apple_pay';

		/** @var \WC_Payment_Gateway overwrite for apple pay settings */
		$this->method_title = __('payplug_apple_pay_title', 'payplug');
		$this->method_description = "";

		$this->title = __('payplug_apple_pay_title', 'payplug');
		$this->description = '<div id="apple-pay-button-wrapper"><apple-pay-button buttonstyle="black" type="pay" locale="'. get_locale() .'"></apple-pay-button></div>';
		$this->domain_name = strtr(get_site_url(), array("http://" => "", "https://" => ""));

		if (!$this->checkApplePay()) {
			$this->enabled = 'no';

		} else {
			add_action('wp_enqueue_scripts', [$this, 'add_apple_pay_css']);
			add_action('wp_enqueue_scripts', [$this, 'add_apple_pay_js']);
		}

	}

	/**
	 * Check Apple Pay Availability
	 *
	 */
	private function checkApplePay(){
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if (isset($account['payment_methods']['apple_pay']['enabled']) ) {

			if( !empty($account['apple_pay']) && $account['apple_pay'] === 'yes' ) {
				$applepay = false;
				if ($account['payment_methods']['apple_pay']['enabled']) {
					if (in_array(strtr(get_site_url(), array("http://" => "", "http://" => "")), $account['payment_methods']['apple_pay']['allowed_domain_names'])) {
						$applepay = true;
					}
				}

				return  $applepay && $this->checkDeviceComptability();
			}

		}

		return false;
	}

	/**
	 * Check User-Agent to make sure it is on Mac OS and in Safari Browser
	 *
	 */
	private function checkDeviceComptability(){
		$user_agent = $_SERVER['HTTP_USER_AGENT'];

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
				'chosen_payment_method'=> WC()->session->get( 'chosen_payment_method')
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
			'payplug' => sprintf('<img src="%s" alt="Apple Pay" class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/' . $available_img)),
		]);
		$icons_str = '';
		foreach ($icons as $icon) {
			$icons_str .= $icon;
		}
		return $icons_str;
	}

}
