<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\Exception\PayplugException;

class PayplugPermissions {

	const OPTION_NAME = 'payplug_permission';
	const LIVE_MODE = 'use_live_mode';
	const SAVE_CARD = 'can_save_cards';
	const USE_ONEY = 'can_use_oney';


	/**
	 * The current mode for the gateway.
	 *
	 * @var string
	 */
	private $gateway_mode;

    /**
     * The current key for account permissions.
     *
     * @var string
     */
    private $current_key;
    
	/**
	 * @var array
	 */
	private $permissions;

	/**
	 * PayplugPermissions constructor.
	 *
	 * @param PayplugGateway $gateway
	 */
	public function __construct( PayplugGateway $gateway) {
		$this->gateway_mode = $gateway->get_current_mode();
		if (!isset($gateway->settings['payplug_live_key']) || !isset($gateway->settings['payplug_test_key'])) {
			$this->current_key = '';
		} else {
			$this->current_key = "live" === $this->gateway_mode ?
				$gateway->settings['payplug_live_key'] : $gateway->settings['payplug_test_key'];
		}
		$this->load_permissions();
	}

	/**
	 * Get all permissions.
	 *
	 * @return array
	 */
	public function get_permissions() {
		return $this->permissions;
	}

	/**
	 * Check if user has specific permission.
	 *
	 * @param string $user_can
	 *
	 * @return bool
	 */
	public function has_permissions( $user_can ) {
		if ( empty( $user_can ) ) {
			return false;
		}

		return isset( $this->permissions[ $user_can ] ) && true === $this->permissions[ $user_can ];
	}

	/**
	 * Delete permissions for the current mode.
	 *
	 * @return bool
	 */
	public function clear_permissions() {
		return delete_transient( $this->get_key() );
	}

	/**
	 * Load permissions for the current mode.
	 */
	protected function load_permissions() {
		$payplug_permissions = get_transient( $this->get_key() );
		if ( ! empty( $payplug_permissions ) ) {
			$this->permissions = $payplug_permissions;

			return true;
		}

		try {
			$response          = Authentication::getPermissions(new Payplug($this->current_key));
			$this->permissions = ! empty( $response ) ? $response : [];
			set_transient( $this->get_key(), $this->permissions, DAY_IN_SECONDS );

			return true;
		} catch ( PayplugException $e ) {
			$this->permissions = [];
		}

		return false;
	}

	/**
	 * Build the key to retrieve the permissions.
	 *
	 * @return string
	 */
	protected function get_key() {
		return self::OPTION_NAME . '_' . $this->gateway_mode;
	}
}
