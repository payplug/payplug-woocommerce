<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\HttpException;
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Exception\PayplugException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayPlug admin ajax handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Ajax {

    /**
     * @var PayplugPermissions
     */
    private $permissions;

	const REFRESH_KEY_ACTION = 'payplug_refresh_keys';
	const CHECK_LIVE_PERMISSIONS = 'check_live_permissions';
	const CHECK_BANCONTACT_PERMISSIONS = 'check_bancontact_permissions';
	const CHECK_APPLEPAY_PERMISSIONS = 'check_applepay_permissions';

	public function __construct() {
		add_action( 'wp_ajax_' . self::REFRESH_KEY_ACTION, [ $this, 'handle_refresh_keys' ] );
		add_action( 'wp_ajax_' . self::CHECK_LIVE_PERMISSIONS, [ $this, 'check_live_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_BANCONTACT_PERMISSIONS, [ $this, 'check_bancontact_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_APPLEPAY_PERMISSIONS, [ $this, 'check_applepay_permissions' ] );
		$authenticated = true;//current_user_can('administrator');
		add_action( 'rest_api_init', function () use($authenticated) {
			register_rest_route( 'payplug', '/data', array(
				'methods' => 'GET',
				'callback' => array($this, "get_data"),
				'permission_callback' => function() use($authenticated) {return $authenticated;}
			) );
		} );
		add_action( 'rest_api_init', function () use($authenticated) {
			register_rest_route( 'payplug', '/logout', array(
				'methods' => 'GET',
				'callback' => array($this, "logout"),
				'permission_callback' => function() use($authenticated) {return $authenticated;}
			) );
		} );
		add_action( 'rest_api_init', function () use($authenticated) {
			register_rest_route( 'payplug', '/login', array(
				'methods' => 'POST',
				'callback' => array($this, "login"),
				'permission_callback' => function() use($authenticated) {return $authenticated;}
			) );
		} );
	}

	public function get_data() {
		$options = get_option('woocommerce_payplug_settings', []);
		$options["user_logged_in"] = $this->user_logged_in($options);
		unset($options["payplug_test_key"]);
		unset($options["payplug_live_key"]);
		unset($options["payplug_merchant_id"]);

		$translations = [
			"login" => [
				"payplug_login_title" => __("payplug_login_title", "payplug"),
				"payplug_login_description" => __("payplug_login_description", "payplug"),
				"payplug_email_address" => __("payplug_email_address", "payplug"),
				"payplug_password" => __("payplug_password", "payplug"),
				"payplug_connect_button" => __("payplug_connect_button", "payplug"),
				"payplug_credentials_error" => __("payplug_credentials_error", "payplug"),
				"payplug_ok" => __("payplug_ok", "payplug"),
				"payplug_not_registered_yet_button" => __("payplug_not_registered_yet_button", "payplug"),
				"payplug_forget_password" => __("payplug_forget_password", "payplug"),
				"payplug_forget_password_link" => __("payplug_forget_password_link", "payplug"),
			],
			"register" => [
				"payplug_register_title" => __("payplug_register_title", "payplug"),
				"payplug_register_description" => __("payplug_register_description", "payplug"),
				"payplug_register_note" => __("payplug_register_note", "payplug"),
				"payplug_create_account_button" => __("payplug_create_account_button", "payplug"),
				"payplug_create_account_button_link" => __("payplug_create_account_button_link", "payplug"),
				"payplug_already_have_account_button" => __("payplug_already_have_account_button", "payplug"),
			],
			"general" => [
				"payplug_general_title" => __("payplug_general_title", "payplug"),
				"payplug_general_description" => __("payplug_general_description", "payplug"),
				"payplug_logout" => __("payplug_logout", "payplug"),
				"payplug_portal_link" => __("payplug_portal_link", "payplug"),
				"payplug_mode" => __("payplug_mode", "payplug"),
				"payplug_test_mode_note" => __("payplug_test_mode_note", "payplug"),
				"payplug_live_mode_note" => __("payplug_live_mode_note", "payplug"),
				"payplug_learn_more" => __("payplug_learn_more", "payplug"),
				"payplug_learn_more_link" => __("payplug_learn_more_link", "payplug"),
				"payplug_test" => __("payplug_test", "payplug"),
				"payplug_live" => __("payplug_live", "payplug"),
			],
			"status" => [
				"payplug_status_title" => __("payplug_status_title", "payplug"),
				"payplug_status_description" => __("payplug_status_description", "payplug"),
			]
		];

		return ["data" => $options, "translations" => $translations];
	}

	public function logout() {
		$data                        = get_option("woocommerce_payplug_settings");
		$data['payplug_test_key']    = '';
		$data['payplug_live_key']    = '';
		$data['payplug_merchant_id'] = '';
		$data['enabled']             = 'no';
		$data['mode']                = 'no';
		$data['oneclick']            = 'no';
		update_option(
			"woocommerce_payplug_settings",
			apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $data)
		);
		wp_send_json(array("success" => true, "message" => __('Successfully logged out.', 'payplug')));
	}

	public function login() {
		$email    = $_POST['email'];
		$password = wp_unslash($_POST['password']);

		$response = $this->retrieve_user_api_keys($email, $password);

		// try to use the api keys to retrieve the merchant id
		$merchant_id = isset($response['test']) ? $this->retrieve_merchant_id($response['test']) : '';

		$data["enabled"] = "yes";
		$data["mode"] = "no";
		$data["payplug_test_key"] = !empty($response['test']) ? esc_attr($response['test']) : null;
		$data["payplug_live_key"] = !empty($response['live']) ? esc_attr($response['live']) : null;
		$data["payplug_merchant_id"] = esc_attr($merchant_id);
		$data["email"] = esc_html($email);

		update_option(
			"woocommerce_payplug_settings",
			apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $data)
		);
		wp_send_json(array("success" => true, "message" => __('Successfully logged in.', 'payplug')));
	}


	/**
	 * Get user's keys.
	 *
	 * @param string $email
	 * @param string $password
	 *
	 * @return array|\WP_Error
	 */
	public function retrieve_user_api_keys($email, $password)
	{
		if (empty($email) || empty($password)) {
			wp_send_json(array("success" => false, "message" => __('Please fill all login fields', 'payplug')));
		}
		try {
			$response = Authentication::getKeysByLogin($email, $password);
			if (empty($response) || !isset($response['httpResponse'])) {
				wp_send_json(array("success" => false, "message" => __('Invalid credentials.', 'payplug')));
			}
			return $response['httpResponse']['secret_keys'];
		} catch (HttpException $e) {
			wp_send_json(array("success" => false, "message" => __('Invalid credentials.', 'payplug')));
		}
	}

	/**
	 * Check if user is logged in and we have an API key for TEST mode.
	 *
	 * @return bool
	 */
	public function user_logged_in($options)
	{
		return !empty($options['payplug_test_key']);
	}

	/**
	 * Get user merchant id.
	 *
	 * This method might be called during the login process before the global PayPlug
	 * configuration is set. In that case you can pass a valid token to make the request.
	 *
	 * @param string|null $key
	 *
	 * @return string
	 */
	public function retrieve_merchant_id($key = null)
	{
		try {
			$response    = !is_null($key) ? Authentication::getAccount(new Payplug($key)) : Authentication::getAccount();
			PayplugWoocommerceHelper::set_transient_data($response);
			$merchant_id = isset($response['httpResponse']['id']) ? $response['httpResponse']['id'] : '';
		} catch (ConfigurationException $e) {
			PayplugGateway::log(sprintf('Missing API key for PayPlug client : %s', wc_print_r($e->getMessage(), true)), 'error');

			$merchant_id = '';
		} catch (HttpException $e) {
			PayplugGateway::log(sprintf('Account request error from PayPlug API : %s', wc_print_r($e->getErrorObject(), true)), 'error');

			$merchant_id = '';
		} catch (\Exception $e) {
			PayplugGateway::log(sprintf('Account request error : %s', wc_clean($e->getMessage())), 'error');

			$merchant_id = '';
		}

		return $merchant_id;
	}

	public function handle_refresh_keys() {

		if ( empty( $_POST['_wpnonce'] ) || empty( $_POST['email'] ) || empty( $_POST['password'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request.', 'payplug' ),
				)
			);
		}

		$action = sprintf( '%s_%s', wp_unslash( $_POST['email'] ), self::REFRESH_KEY_ACTION );
		if ( ! check_ajax_referer( $action ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid nonce.', 'payplug' ),
				)
			);
		}

		$email    = sanitize_text_field( wp_unslash( $_POST['email'] ) );
		$password = sanitize_text_field( wp_unslash( $_POST['password'] ) );

		if ( ! WC()->payment_gateways() ) {
			wp_send_json_error(
				array(
					'message' => __( 'An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug' ),
				)
			);
		}

		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $payment_gateways ) || ! isset( $payment_gateways['payplug'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug' ),
				)
			);
		}

		/* @var PayplugGateway $payplug_gateway */
		$payplug_gateway = $payment_gateways['payplug'];
		$keys            = $payplug_gateway->retrieve_user_api_keys( $email, $password );
		if ( is_wp_error( $keys ) ) {
			wp_send_json_error(
				array(
					'message' => $keys->get_error_message(),
				)
			);
		}

		$success = $this->update_api_keys( $keys, $payplug_gateway );

		if ( empty( $keys['live'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug' ),
				)
			);
		}

		if ( ! $success ) {
			wp_send_json_error(
				array(
					'message' => __( 'Something went wrong.', 'payplug' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your API keys has successfully been updated.', 'payplug' )
			)
		);
	}

    public function check_live_permissions() {
		try{
			$account = Authentication::getAccount(new Payplug($_POST['livekey']));
		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(["error" => $e->getMessage()]);
			return false;
		}
		PayplugWoocommerceHelper::set_transient_data($account);
        $permissions = $account['httpResponse']['permissions'];
		wp_send_json_success($permissions);
	}

	public function check_bancontact_permissions() {
		try{
			$account = Authentication::getAccount(new Payplug($_POST['livekey']));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(["error" => $e->getMessage()]);
			return false;
		}

		PayplugWoocommerceHelper::set_transient_data($account);
		$bancontact = isset($account['httpResponse']['payment_methods']['bancontact']['enabled']) ? $account['httpResponse']['payment_methods']['bancontact']['enabled']: false;
		wp_send_json_success($bancontact);
	}

	public function check_applepay_permissions() {
		try{
			$account = Authentication::getAccount(new Payplug($_POST['livekey']));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(["error" => $e->getMessage()]);
			return false;
		}

		PayplugWoocommerceHelper::set_transient_data($account);
		$applepay = false;

		if ($account['httpResponse']['payment_methods']['apple_pay']['enabled']) {
			if (in_array(strtr(get_site_url(), array("http://" => "", "https://" => "")), $account['httpResponse']['payment_methods']['apple_pay']['allowed_domain_names'])) {
				$applepay = true;
			}

		}
		wp_send_json_success($applepay);
	}

	/**
	 * Update PayPlug api keys
	 *&

	 * @param array $keys
	 * @param PayplugGateway $payplug_gateway
	 *
	 * @return bool
	 */
	protected function update_api_keys( $keys, $payplug_gateway ) {
		if ( empty( $payplug_gateway->settings ) ) {
			$payplug_gateway->init_settings();
		}

		$payplug_gateway->settings['payplug_test_key'] = $keys['test'];
		$payplug_gateway->settings['payplug_live_key'] = $keys['live'];
		if ( ! empty( $keys['live'] ) ) {
			$payplug_gateway->settings['mode'] = 'yes';
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

}
