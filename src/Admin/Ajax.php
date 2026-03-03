<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
use Payplug\Exception\HttpException;
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Controller\ApplePay;
use Payplug\PayplugWoocommerce\Gateway\AmericanExpress;
use Payplug\PayplugWoocommerce\Gateway\Bancontact;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Ideal;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Mybank;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Satispay;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Wero;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Bizum;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Exception\PayplugException;
use Payplug\PayplugWoocommerce\Traits\GatewayGetter;
use WP_REST_Request;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * PayPlug admin ajax handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Ajax
{
	use GatewayGetter;

	public function __construct()
	{
		$permission = (current_user_can('editor') || current_user_can('administrator'));

		add_action('rest_api_init', function () use ($permission) {
			//Path to REST route and the callback function
			register_rest_route('payplug_api', '/save/', array(
				'methods' => 'POST',
				'callback' => [$this, 'save_action'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/init/', array(
				'methods' => 'POST',
				'callback' => [$this, 'payplug_init'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/login/', array(
				'methods' => 'POST',
				'callback' => [$this, 'login_action'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/logout/', array(
				'methods' => 'POST',
				'callback' => [$this, 'payplug_logout'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/refresh_keys/', array(
				'methods' => 'POST',
				'callback' => [$this, 'refresh_keys'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/check_requirements/', array(
				'methods' => 'POST',
				'callback' => [$this, 'payplug_check_requirements'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/bancontact_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_bancontact_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/applepay_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_applepay_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/american_express_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_american_express_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/oney_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_oney_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/one_click_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_one_click_permission'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/satispay_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_satispay_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/mybank_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_mybank_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/ideal_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_ideal_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/integrated_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_integrated_payment'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				}
			));
			register_rest_route('payplug_api', '/wero_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_wero_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
			register_rest_route('payplug_api', '/bizum_permissions/', array(
				'methods' => 'POST',
				'callback' => [$this, 'api_check_bizum_permissions'],
				'permission_callback' => function () use ($permission) {
					return $permission;
				},
				'show_in_index' => false
			));
		});
	}

	public function refresh_keys(WP_REST_Request $request)
	{
		$data = $request->get_params();
		$email = sanitize_text_field(wp_unslash($data['payplug_email']));
		$password = base64_decode(wp_unslash($data['payplug_password']));

		if (empty($email) || empty($password)) {
			wp_send_json_error(
				array(
					'message' => __('Invalid request.', 'payplug'),
				)
			);
		}

		if (!WC()->payment_gateways()) {
			wp_send_json_error(
				array(
					'message' => __('An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug'),
				)
			);
		}

		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		if (empty($payment_gateways) || !isset($payment_gateways['payplug'])) {
			wp_send_json_error(
				array(
					'message' => __('An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug'),
				)
			);
		}

		/* @var PayplugGateway $payplug_gateway */
		$payplug_gateway = $payment_gateways['payplug'];
		$keys = $payplug_gateway->retrieve_user_api_keys($email, $password);
		if (is_wp_error($keys)) {
			wp_send_json_error(
				array(
					'message' => $keys->get_error_message(),
				)
			);
		}

		$success = $this->update_api_keys($keys, $payplug_gateway);

		if (empty($keys['live'])) {
			wp_send_json_error(
				array(
					'message' => __('Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug'),
					'still_inactive' => true
				)
			);
		}

		if (!$success) {
			wp_send_json_error(
				array(
					'message' => __('Something went wrong.', 'payplug'),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __('Your API keys has successfully been updated.', 'payplug')
			)
		);
	}

	public function api_check_one_click_permission(WP_REST_Request $request)
	{
		wp_send_json_success(true);
	}

	public function api_check_bancontact_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, Bancontact::ENABLE_ON_TEST_MODE);

		if (isset($account['httpResponse']['payment_methods']['bancontact']['enabled']) && $account['httpResponse']['payment_methods']['bancontact']['enabled']) {
			wp_send_json_success(true);
		}

		wp_send_json_error(array(
			'title' => __('payplug_enable_feature', 'payplug'),
			'msg' => __('payplug_bancontact_access_error', 'payplug'),
			'close' => __('payplug_ok', 'payplug')
		));

	}

	public function api_check_applepay_permissions(WP_REST_Request $request)
	{
		wp_send_json_success(true);

		$account = $this->generic_get_account($request, ApplePay::ENABLE_ON_TEST_MODE);

		if ($account['httpResponse']['payment_methods']['apple_pay']['enabled']) {
			if (in_array($_SERVER['HTTP_HOST'], $account['httpResponse']['payment_methods']['apple_pay']['allowed_domain_names'])) {
				wp_send_json_success(true);
			}

		}

		wp_send_json_error(array(
			'title' => __('payplug_enable_feature', 'payplug'),
			'msg' => __('payplug_applepay_access_error', 'payplug'),
			'close' => __('payplug_ok', 'payplug')
		));
	}

	public function api_check_american_express_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, AmericanExpress::ENABLE_ON_TEST_MODE);

		if (isset($account['httpResponse']['payment_methods']['american_express']['enabled']) && $account['httpResponse']['payment_methods']['american_express']['enabled']) {
			wp_send_json_success(true);
		}

		wp_send_json_error(array(
			'title' => __('payplug_enable_feature', 'payplug'),
			'msg' => __('payplug_amex_access_error', 'payplug'),
			'close' => __('payplug_ok', 'payplug')
		));

	}

	public function api_check_oney_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, PayplugGatewayOney3x::ENABLE_ON_TEST_MODE);

		if (isset($account['httpResponse']['permissions']['can_use_oney']) && $account['httpResponse']['permissions']['can_use_oney']) {
			wp_send_json_success(true);
		}

		$oney = isset($account['httpResponse']['permissions']['can_use_oney']) ? $account['httpResponse']['permissions']['can_use_oney'] : false;

		if (!$oney) {
			$anchor_text = __('payplug_oney_error_link', 'payplug');
			$anchor_url = 'https://portal.payplug.com/login';
			$anchor = sprintf(' <a href="%s" target="_blank">%s</a>', $anchor_url, $anchor_text);
			$message = __('payplug_oney_error_description', 'payplug') . $anchor;
			wp_send_json_error(array(
				'title' => __('payplug_oney_error_title', 'payplug'),
				'msg' => $message,
				'close' => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success(true);
	}

	public function api_check_satispay_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, Satispay::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['satispay']['enabled']) ? $account['httpResponse']['payment_methods']['satispay']['enabled'] : false;
		if (!$enabled) {
			wp_send_json_error(array(
				'title' => __('payplug_enable_feature', 'payplug'),
				'msg' => __('payplug_satispay_access_error', 'payplug'),
				'close' => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success($enabled);
	}

	public function api_check_mybank_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, Mybank::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['mybank']['enabled']) ? $account['httpResponse']['payment_methods']['mybank']['enabled'] : false;
		if (!$enabled) {
			wp_send_json_error(array(
				'title' => __('payplug_enable_feature', 'payplug'),
				'msg' => __('payplug_mybank_access_error', 'payplug'),
				'close' => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success($enabled);
	}

	public function api_check_ideal_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, Ideal::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['ideal']['enabled']) ? $account['httpResponse']['payment_methods']['ideal']['enabled'] : false;
		if (!$enabled) {
			wp_send_json_error(array(
				'title' => __('payplug_enable_feature', 'payplug'),
				'msg' => __('payplug_ideal_access_error', 'payplug'),
				'close' => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success($enabled);
	}

	public function api_check_wero_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, Wero::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['wero']['enabled']) ? $account['httpResponse']['payment_methods']['wero']['enabled'] : false;
		if (!$enabled) {
			wp_send_json_error(array(
				"title" => __('payplug_enable_feature', 'payplug'),
				"msg" => __('payplug_wero_access_error', 'payplug'),
				"close" => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success($enabled);
	}

	public function api_check_bizum_permissions(WP_REST_Request $request)
	{
		$account = $this->generic_get_account($request, Bizum::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['bizum']['enabled']) ? $account['httpResponse']['payment_methods']['bizum']['enabled'] : false;
		if (!$enabled) {
			wp_send_json_error(array(
				"title" => __('payplug_enable_feature', 'payplug'),
				"msg" => __('payplug_bizum_access_error', 'payplug'),
				"close" => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success($enabled);
	}

	private function generic_get_account($request, $enable_on_test_mode)
	{

		$data = $request->get_params();

		if (isset($data['env']) && $data['env']) {
			if ($enable_on_test_mode) {
				wp_send_json_success(true);
			} else {
				$this->optionUnnavailableInTestMode();
			}
		}

		$this->accountIsNotValid();

		$live_key = PayplugWoocommerceHelper::get_live_key();

		if (empty($live_key)) {
			return false;
		}

		try {
			$account = Authentication::getAccount(new Payplug($live_key));

		} catch (PayplugException $e) {
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			return false;
		}

		return $account;

	}

	/**
	 * Update PayPlug api keys
	 *&
	 * @param array $keys
	 * @param PayplugGateway $payplug_gateway
	 *
	 * @return bool
	 */
	protected function update_api_keys($keys, $payplug_gateway)
	{
		if (empty($payplug_gateway->settings)) {
			$payplug_gateway->init_settings();
		}

		$payplug_gateway->settings['payplug_test_key'] = $keys['test'];
		$payplug_gateway->settings['payplug_live_key'] = $keys['live'];
		if (!empty($keys['live'])) {
			$payplug_gateway->settings['mode'] = true;
		}

		return update_option(
			$payplug_gateway->get_option_key(),
			apply_filters(
				'woocommerce_settings_api_sanitized_fields_' . $payplug_gateway->id,
				$payplug_gateway->settings
			),
			'yes'
		);
	}

	/**
	 * Response Json Error WP Format for wrong credentials
	 * @return void
	 */
	private function login_wrong_credentials_error()
	{
		wp_send_json_error(array('message' => __("payplug_error_wrong_credentials", "payplug")));
	}

	/**
	 *
	 * Ajax payplug initialisation
	 *
	 * @return JSON
	 */
	public function payplug_init()
	{
		$wp_nonce = wp_create_nonce();

		$wp = [
			'logged' => $this->get_gateway('account')->is_logged(),
			'mode' => PayplugWoocommerceHelper::check_mode() ? 0 : 1,
			'WP' => [
				'_wpnonce' => $wp_nonce,
			]
		];

		return wp_send_json_success([
				'settings' => $wp
			] + (new Vue)->init());
	}

	/**
	 * @return bool|null
	 */
	public function payplug_logout()
	{

		PayplugWoocommerceHelper::payplug_logout();
		$wp = [
			'logged' => $this->get_gateway('account')->is_logged(),
			'mode' => PayplugWoocommerceHelper::check_mode() ? 0 : 1
		];

		http_response_code(200);
		wp_send_json_success(array(
			'message' => __('Successfully logged out.', 'payplug'),
			'status' => (new Vue)->payplug_section_status(),
			'settings' => $wp,
			'subscribe' => (new Vue)->payplug_section_subscribe() // When Logging out the Status Block needs to be updated
		));
	}


	private function accountIsNotValid()
	{
		$live_key = PayplugWoocommerceHelper::get_live_key();
		if (empty($live_key)) {
			wp_send_json_error(array(
				'title' => __('payplug_enable_feature', 'payplug'),
				'msg' => __('Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug'),
				'close' => __('payplug_ok', 'payplug')
			));
		}
	}

	private function optionUnnavailableInTestMode()
	{
		wp_send_json_error(array(
			'title' => __('payplug_enable_feature', 'payplug'),
			'msg' => __('payplug_unavailable_testmode_description', 'payplug')
		));
	}

	public function payplug_check_requirements()
	{
		wp_send_json_success(array(
			'status' => (new Vue)->payplug_section_status()
		));
	}

	public function api_check_integrated_payment(WP_REST_Request $request)
	{

		$account = $this->generic_get_account($request, true);

		if (!isset($account['httpResponse']['permissions']['can_use_integrated_payments'])
			|| !$account['httpResponse']['permissions']['can_use_integrated_payments']) {
			wp_send_json_error(array(
				'title' => __('payplug_enable_feature', 'payplug'),
				'msg' => __('payplug_integrated_access_error', 'payplug'),
				'close' => __('payplug_ok', 'payplug')
			));
		}

		wp_send_json_success(true);
		return true;

	}

	public function login_action()
	{
		$data = json_decode(file_get_contents('php://input'), true);
		$email = sanitize_email($data['payplug_email']);
		$password = base64_decode(wp_unslash($data['payplug_password']));
		$wp_nonce = !empty($data['_wpnonce']) ? $data['_wpnonce'] : null;

		// use AccountGateway
		try {
			$account_gateway = $this->get_gateway('account');
			$register = $account_gateway->register((string)$email, (string)$password);
			if (empty($register)) {
				$this->login_wrong_credentials_error();
			}

			$user = [
				'logged' => true,
				'email' => $email,
				'mode' => (bool)$register['mode'] ? 0 : 1,
			];
			$wp = [
				'WP' => [
					'_wpnonce' => $wp_nonce,
				]
			];
			$vue = (new Vue)->init();

			wp_send_json_success(['settings' => $user + $wp] + $vue);
		} catch (HttpException $e) {
			$this->login_wrong_credentials_error();
		}
	}

	public function save_action(WP_REST_Request $request)
	{
		if (!$this->get_gateway('account')->is_logged()) {
			http_response_code(403);
			wp_send_json_error('You are not logged in !');
		}

		$payplug = new PayplugGateway();
		$options = $payplug->settings;

		$data = json_decode(file_get_contents('php://input'), true);

		// global configuration
		$options['enabled'] = (bool)$data['payplug_enable'];
		$options['debug'] = (bool)$data['enable_debug'];
		$options['mode'] = (bool)$data['payplug_sandbox'] ? false : true;

		// payment configuration
		$payment_methods = [
			'oney',
			'apple_pay',
			'payplug',
			'american_express',
			'bancontact',
			'satispay',
			'mybank',
			'ideal',
			'wero',
			'bizum',
		];
		foreach ($payment_methods as $name) {
			if (!isset($options['payment_methods']['configuration'][$name])) {
				continue;
			}
			$options['payment_methods']['configuration'][$name]['active'] = (bool)$data['enable_' . $name];
		}

		// standard payment
		$options['payment_methods']['configuration']['payplug']['active'] = (bool)$data['enable_standard'];
		$options['payment_methods']['configuration']['payplug']['title'] = (string)$data['standard_payment_title'];
		$options['payment_methods']['configuration']['payplug']['description'] = (string)$data['standard_payment_description'];
		$options['payment_methods']['configuration']['payplug']['embedded_mode'] = (string)$data['payplug_embeded'];
		$options['payment_methods']['configuration']['payplug']['save_card'] = (bool)$data['enable_one_click'];

		// applepay payment
		$applepay_active = (bool)$data['enable_applepay'];
		$applepay_carriers = $data['applepay_carriers'];
		$applepay_cart = (bool)$data['enable_applepay_cart'];
		$applepay_checkout = (bool)$data['enable_applepay_checkout'];
		$applepay_product = (bool)$data['enable_applepay_product'];
		// if product or cart options are checked but no carriers selected, return error
		$applepay_active = $applepay_active && Validator::applePayPaymentGatewayOptions($applepay_active, $applepay_cart, $applepay_product, $applepay_checkout, $applepay_carriers);
		$options['payment_methods']['configuration']['apple_pay']['active'] = $applepay_active;
		$options['payment_methods']['configuration']['apple_pay']['carriers'] = json_encode($applepay_carriers);
		$options['payment_methods']['configuration']['apple_pay']['display'] = json_encode([
			'cart' => $applepay_cart,
			'checkout' => $applepay_checkout,
			'product' => $applepay_product,
		]);

		// oney payment
		$options['payment_methods']['configuration']['oney']['active'] = (bool)$data['enable_oney'];
		$options['payment_methods']['configuration']['oney']['cta_product'] = (bool)$data['enable_oney_product_animation'];
		$options['payment_methods']['configuration']['oney']['custom_amounts'] = json_encode([
			'min' => (int)$data['oney_min_amounts'],
			'max' => (int)$data['oney_max_amounts'],
		]);
		$options['payment_methods']['configuration']['oney']['with_fees'] = 'with_fees' == (string)$data['payplug_oney'];

		//
		$update = $payplug->get_service('configuration')->update_options($options);

		if ($update) {
			//delete the transient, so it refresh the permissions on the init
			$transient_key = PayplugWoocommerceHelper::get_transient_key($options);
			delete_transient($transient_key);

			http_response_code(200);
			wp_send_json_success(array(
				'title' => null,
				'msg' => __('payplug_save_success_message', 'payplug'),
				'close' => __('payplug_ok', 'payplug'),
			));
		} else {
			http_response_code(200);
			wp_send_json_error([
				'title' => null,
				'close' => __('payplug_ok', 'payplug'),
				'msg' => 'These settings are already saved !']);
		}
	}
}
