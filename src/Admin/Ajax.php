<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;

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

	public function __construct() {
		add_action( 'wp_ajax_' . self::REFRESH_KEY_ACTION, [ $this, 'handle_refresh_keys' ] );
		add_action( 'wp_ajax_' . self::CHECK_LIVE_PERMISSIONS, [ $this, 'check_live_permissions' ] );
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
		$account = Authentication::getAccount(new Payplug($_POST['livekey']));
        $permissions = $account['httpResponse']['permissions'];
		wp_send_json_success($permissions);
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
