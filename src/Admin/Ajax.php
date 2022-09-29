<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
use Payplug\Exception\HttpException;
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Exception\PayplugException;
use Payplug\PayplugWoocommerce\Admin\Vue;

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
	const PAYPLUG_LOGIN = 'payplug_login';

	public function __construct() {
		add_action( 'wp_ajax_' . self::REFRESH_KEY_ACTION, [ $this, 'handle_refresh_keys' ] );
		add_action( 'wp_ajax_' . self::CHECK_LIVE_PERMISSIONS, [ $this, 'check_live_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_BANCONTACT_PERMISSIONS, [ $this, 'check_bancontact_permissions' ] );
		add_action( 'wp_ajax_' . self::CHECK_APPLEPAY_PERMISSIONS, [ $this, 'check_applepay_permissions' ] );
		add_action( 'wp_ajax_' . self::PAYPLUG_LOGIN, [ $this, 'payplug_login' ] );
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

		try {
			$response = Authentication::getPermissionsByLogin($email, $password);
			if (empty($response) || !isset($response)) {
				var_dump($response);
				return wp_send_json_error($response);
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
				"mode" => 0
			];

			return wp_send_json_success( ["settings" => $user + $response] + ( new Vue )->init() );
		} catch (HttpException $e) {
			return wp_send_json_error($e->getErrorObject());
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
			"WP" => [
				"_wpnonce" => $wp_nonce,
				"_loginaction" => $wp_loginaction
			]
		];

		return wp_send_json_success([
			"settings" => $wp
		] + ( new Vue )->init() );

	}

}
