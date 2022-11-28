<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
use Payplug\Exception\HttpException;
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Admin\Vue;
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
	const CHECK_AMERICAN_EXPRESS_PERMISSIONS = 'check_american_express_permissions';
	const PAYPLUG_LOGIN = 'payplug_login';
	const PAYPLUG_INIT = 'payplug_init';
	const PAYPLUG_SAVE_DATA = 'payplug_save_data';
	const PAYPLUG_LOGOUT = 'payplug_logout';
	const PAYPLUG_CHECK_REQUIREMENTS = 'payplug_check_requirements';
	const API_CHECK_BANCONTACT_PERMISSIONS = 'api_check_bancontact_permissions';
	const API_CHECK_APPLEPAY_PERMISSIONS = 'api_check_applepay_permissions';
	const API_CHECK_AMERICAN_EXPRESS_PERMISSIONS = 'api_check_american_express_permissions';

	public function __construct() {
		add_action( 'wp_ajax_' . self::REFRESH_KEY_ACTION, [ $this, 'handle_refresh_keys' ] );
		add_action( 'wp_ajax_' . self::CHECK_LIVE_PERMISSIONS, [ $this, 'check_live_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_BANCONTACT_PERMISSIONS, [ $this, 'check_bancontact_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_APPLEPAY_PERMISSIONS, [ $this, 'check_applepay_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_AMERICAN_EXPRESS_PERMISSIONS, [ $this, 'check_american_express_permissions' ] );
		add_action( 'wp_ajax_' . self::PAYPLUG_LOGIN, [ $this, 'payplug_login' ] );
		add_action( 'wp_ajax_' . self::PAYPLUG_INIT, [ $this, 'payplug_init' ] );
		add_action( 'wp_ajax_' . self::PAYPLUG_LOGOUT, [ $this, 'payplug_logout' ] );
		add_action( 'wp_ajax_' . self::PAYPLUG_SAVE_DATA, [ $this, 'payplug_save_data' ] );
		add_action( 'wp_ajax_' . self::PAYPLUG_CHECK_REQUIREMENTS, [ $this, 'payplug_check_requirements' ] );
		add_action( 'wp_ajax_' . self::API_CHECK_BANCONTACT_PERMISSIONS, [ $this, 'api_check_bancontact_permissions' ] );
		add_action( 'wp_ajax_' . self::API_CHECK_APPLEPAY_PERMISSIONS, [ $this, 'api_check_applepay_permissions' ] );
		add_action( 'wp_ajax_' . self::API_CHECK_AMERICAN_EXPRESS_PERMISSIONS, [ $this, 'api_check_american_express_permissions' ] );
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
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));
		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(["error" => $e->getMessage()]);
			return false;
		}
		PayplugWoocommerceHelper::set_transient_data($account);
		$permissions = $account['httpResponse']['permissions'];
		wp_send_json_success($permissions);
	}

	public function api_check_bancontact_permissions() {

		if($_POST['env']) {
			$this->optionUnnavailableInTestMode();
		}

		$this->accountIsNotValid();

		try{
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => $e->getMessage(),
				"close" => __( 'payplug_ok', 'payplug' )
			));
			return false;
		}

		PayplugWoocommerceHelper::set_transient_data($account);

		if(isset($account['httpResponse']['payment_methods']['bancontact']['enabled']) && $account['httpResponse']['payment_methods']['bancontact']['enabled']){
			wp_send_json_success(true);
		}

		wp_send_json_error(array(
			"title" => __( 'payplug_enable_feature', 'payplug' ),
			"msg" => __( 'payplug_bancontact_access_error', 'payplug' ),
			"close" => __( 'payplug_ok', 'payplug' )
		));

	}

	public function api_check_applepay_permissions() {

		if($_POST['env']) {
			$this->optionUnnavailableInTestMode();
		}

		$this->accountIsNotValid();

		try{
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => $e->getMessage(),
				"close" => __( 'payplug_ok', 'payplug' )
			));
			return false;
		}

		PayplugWoocommerceHelper::set_transient_data($account);
		$applepay = false;

		if ($account['httpResponse']['payment_methods']['apple_pay']['enabled']) {
			if (in_array(strtr(get_site_url(), array("http://" => "", "https://" => "")), $account['httpResponse']['payment_methods']['apple_pay']['allowed_domain_names'])) {
				wp_send_json_success(true);
			}

		}


		if(!$applepay){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __( 'payplug_applepay_access_error', 'payplug' ),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

	}

	public function api_check_american_express_permissions() {

		if($_POST['env']) {
			$this->optionUnnavailableInTestMode();
		}

		$this->accountIsNotValid();

		try{
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => $e->getMessage(),
				"close" => __( 'payplug_ok', 'payplug' )
			));
			return false;
		}

		PayplugWoocommerceHelper::set_transient_data($account);

		if(isset($account['httpResponse']['payment_methods']['american_express']['enabled']) && $account['httpResponse']['payment_methods']['american_express']['enabled']){
			wp_send_json_success(true);
		}

		$amex = isset($account['httpResponse']['payment_methods']['american_express']['enabled']) ? $account['httpResponse']['payment_methods']['american_express']['enabled']: false;

		if(!$amex){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __( 'payplug_amex_access_error', 'payplug' ),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

		wp_send_json_success($amex);
	}

	public function check_bancontact_permissions() {
		try{
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));

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
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));

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

	public function check_american_express_permissions() {
		try{
			$account = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(["error" => $e->getMessage()]);
			return false;
		}

		PayplugWoocommerceHelper::set_transient_data($account);
		$amex = isset($account['httpResponse']['payment_methods']['american_express']['enabled']) ? $account['httpResponse']['payment_methods']['american_express']['enabled']: false;
		wp_send_json_success($amex);
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


	/**
	 *
	 * Ajax paypal login
	 *
	 * @return JSON
	 */

	public function payplug_login() {

		$email = sanitize_email($_POST['payplug_email']);
		$password = wp_unslash($_POST['payplug_password']);
		$wp_nonce = $_POST['_wpnonce'];
		$wp_loginaction = $_POST['_loginaction'];

		try {
			$response = Authentication::getKeysByLogin($email, $password);
			if (empty($response) || !isset($response)) {
				http_response_code(401);
				return wp_send_json_error(array(
					'message' => __( 'payplug_error_wrong_credentials.', 'payplug' ),
				));
			}
			$payplug = new PayplugGateway();
			$form_fields = $payplug->get_form_fields();

			$api_keys = $payplug->retrieve_user_api_keys($email, $password);

			foreach ($form_fields as $key => $field) {
				if (in_array($field['type'], ['title', 'login'])) {
					continue;
				}

				switch ($key) {
					case 'enabled':
						$val = 'yes';
						break;
					case 'mode':
						$val = 'no';
						break;
					case 'payplug_test_key':
						$val = !empty($api_keys['test']) ? esc_attr($api_keys['test']) : null;
						break;
					case 'payplug_live_key':
						$val = !empty($api_keys['live']) ? esc_attr($api_keys['live']) : null;
						break;
					case 'email':
						$val = esc_html($email);
						break;
					default:
						$val = $payplug->get_option($key);
				}

				$data[$key] = $val;
			}

			$payplug->set_post_data($data);
			update_option(
				$payplug->get_option_key(),
				apply_filters('woocommerce_settings_api_sanitized_fields_' . $payplug->id, $data)
			);

			$user = [
				"logged" => true,
				"email" => $email,
				"mode" => PayplugWoocommerceHelper::check_mode()
			];
			$wp = [
				"WP" => [
					"_wpnonce" => $wp_nonce,
					"_loginaction" => $wp_loginaction
				]
			];

			return wp_send_json_success( ["settings" => $user + $response + $wp] + ( new Vue )->init() );
		} catch (HttpException $e) {

			//TODO:: error handler, Authentication::getPermissionsByLogin comes here
			http_response_code(401);
			$error = __("payplug_error_wrong_credentials", "payplug");
			return wp_send_json_error(array('message' => $error));

		}
	}


	/**
	 *
	 * Ajax payplug initialisation
	 *
	 * @return JSON
	 */

	public function payplug_init() {

		$wp_nonce = wp_create_nonce();
		$wp_loginaction = $_POST['_loginaction'];

		$wp = [
			"logged" => PayplugWoocommerceHelper::user_logged_in(),
			"mode" => PayplugWoocommerceHelper::check_mode(),
			"WP" =>  [
				"_wpnonce" => $wp_nonce,
				"_loginaction" => $wp_loginaction
			]
		];

		return wp_send_json_success([
			"settings" => $wp
		] + ( new Vue )->init() );

	}

	/**
	 * @return bool|null
	 */

	public function payplug_logout() {

		$payplug = new PayplugGateway();

		if (PayplugWoocommerceHelper::payplug_logout($payplug)) {
			http_response_code(200);
			return wp_send_json_success(__('Successfully logged out.', 'payplug'));
		} else {
			http_response_code(400);
			return wp_send_json_error(__('Already logged out.', 'payplug'));
		}

	}


	private function accountIsNotValid(){
		$like_key = PayplugWoocommerceHelper::get_live_key();
		if(empty($like_key)){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __('Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug'),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}
	}

	private function optionUnnavailableInTestMode(){
		wp_send_json_error(array(
			"title" => __( 'payplug_enable_feature', 'payplug' ),
			"msg" => __( 'payplug_unavailable_testmode_description', 'payplug' )
		));
	}

	/**
	 *
	 * Save data from request
	 *
	 * @return null
	 */

	public function payplug_save_data() {

		$payplug = new PayplugGateway();

		if ($payplug->user_logged_in()) {

			$data = $payplug->get_post_data();
			$options = get_option('woocommerce_payplug_settings', []);

			$options['enabled'] = Validator::enabled($data['enabled']);
			$options['mode'] = Validator::mode($data['mode']);
			$options['payment_method'] = (Validator::payment_method($data['payment_method'])) ? $data['payment_method'] : $options['payment_method'];
			$options['debug'] = Validator::debug($data['debug']);
			$options['oneclick'] = Validator::oneclick($data['oneclick']);
			$options['bancontact'] = Validator::bancontact($data['bancontact']);
			$options['apple_pay'] = Validator::apple_pay($data['apple_pay']);
			$options['american_express'] = Validator::american_express($data['american_express']);
			$options['oney'] = Validator::oney($data['oney']);
			$options['oney_type'] = (Validator::oney_type($data['oney_type'])) ? $data['oney_type'] : $options['oney_type'];
			$options['oney_thresholds_min'] = (Validator::oney_thresholds($data['oney_thresholds_min'], $data['oney_thresholds_max'])) ? $data['oney_thresholds_min'] : $options['oney_thresholds_min'];
			$options['oney_thresholds_max'] = (Validator::oney_thresholds($data['oney_thresholds_max'], $data['oney_thresholds_max'])) ? $data['oney_thresholds_max'] : $options['oney_thresholds_max'];


			update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );

			http_response_code(200);

			return wp_send_json_success();
		} else {
			http_response_code(403);
			return wp_send_json("You are not logged in !");
		}


	}

	public function payplug_check_requirements() {
		wp_send_json_success(array(
			"status" => ( new Vue )->payplug_section_status()
		));
	}

}
