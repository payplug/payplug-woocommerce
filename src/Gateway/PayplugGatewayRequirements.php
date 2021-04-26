<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

// Exit if accessed directly
use const OPENSSL_VERSION_TEXT;
use const PHP_VERSION;
use function sprintf;
use function var_dump;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayplugGatewayRequirements {

	const PHP_MIN = '5.6';
	const OPENSSL_MIN = 268439567;
	const OPENSSL_MIN_TEXT = 'OpenSSL 1.0.1 14 Mar 2012';

	/**
	 * @var PayplugGateway
	 */
	private $gateway;


    /**
     * @var PayplugPermissions
     */
	private $permissions;
	
	/**
	 * Gateway settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * PayplugGatewayRequirements constructor.
	 *
	 * @param PayplugGateway $gateway
	 */
	public function __construct( PayplugGateway $gateway ) {
		$this->gateway = $gateway;
		$this->permissions = new PayplugPermissions($gateway);
		$this->settings = get_option( 'woocommerce_payplug_settings', [] );
	}

	/**
	 * Check if all gateway requirements are fulfilled.
	 *
	 * @return bool
	 */
	public function satisfy_requirements() {
		return $this->valid_php()
		       && $this->valid_curl()
		       && $this->valid_openssl()
		       && $this->valid_currency()
		       && $this->valid_account();
	}

	/**
	 * @return string
	 */
	public function curl_requirement() {
		return ( $this->valid_curl() )
			? '<p class="success">' . __( 'The PHP cURL extension is installed and activated on your server.', 'payplug' ) . '</p>'
			: '<p class="failed">' . __( 'The PHP cURL extension must be installed and activated on your server.', 'payplug' ) . '</p>';
	}

	/**
	 * @return string
	 */
	public function php_requirement() {
		return ( $this->valid_php() )
			? '<p class="success">' . __( 'The PHP version on your server is valid.', 'payplug' ) . '</p>'
			/* translators: %s: minimum required PHP version */
			: '<p class="failed">' . sprintf( __( 'The PHP version on your server is not supported. Your server must run PHP %s or greater.', 'payplug' ), self::PHP_MIN ) . '</p>';
	}

	/**
	 * @return string
	 */
	public function openssl_requirement() {
		return ( $this->valid_openssl() )
			? '<p class="success">' . __( 'OpenSSL is up to date.', 'payplug' ) . '</p>'
			/* translators: %s: minimum required OpenSSL version */
			: '<p class="failed">' . sprintf( __( 'OpenSSL is not up to date. Please update to OpenSSL %s or later.', 'payplug' ), self::OPENSSL_MIN . ' ( ' . self::OPENSSL_MIN_TEXT . ' )' ) . '</p>';
	}

	/**
	 * @return string
	 */
	public function currency_requirement() {
		return ( $this->valid_currency() )
			? '<p class="success">' . __( 'Your shop currency has been set up with Euro.', 'payplug' ) . '</p>'
			: '<p class="failed">' . __( 'Your shop currency must be set up with Euro.', 'payplug' ) . '</p>';
	}

	/**
	 * @return string
	 */
	public function account_requirement() {
		return ( $this->valid_account() )
			? '<p class="success">' . __( 'You are logged in with your PayPlug account.', 'payplug' ) . '</p>'
			: '<p class="failed">' . __( 'You must be logged in with your PayPlug account.', 'payplug' ) . '</p>';
	}

	/**
	 * @return string
	 */
	public function oney_requirement() {
		return ($this->valid_oney_check()) ? ($this->valid_oney() )
			? '<p class="success">' . __( 'Oney is active on your store.', 'payplug' ) . '</p>'
			: '<p class="failed-oney">' . __( 'Please fill in the GCSs relating to oney.', 'payplug' ) . '</p>' :
			'';
	}

	/**
	 * Check if CURL is available and support SSL
	 *
	 * @return bool
	 */
	public function valid_curl() {
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_exec' ) ) {
			return false;
		}

		// Also Check for SSL support
		$curl_version = curl_version();
		if ( ! ( CURL_VERSION_SSL & $curl_version['features'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if PHP version is equal or above the minimum supported.
	 *
	 * @return bool
	 */
	public function valid_php() {
		return version_compare( PHP_VERSION, self::PHP_MIN, '>=' );
	}

	/**
	 * Check if OPENSSL version is equal or above the minimum supported.
	 *
	 * @return bool
	 */
	public function valid_openssl() {
		return OPENSSL_VERSION_NUMBER >= self::OPENSSL_MIN;
	}

	/**
	 * Check if the shop currency is Euro.
	 *
	 * @return bool
	 */
	public function valid_currency() {
		return 'EUR' === get_woocommerce_currency();
	}

	/**
	 * Check if the user is logged in.
	 *
	 * @return bool
	 */
	public function valid_account() {
		return $this->gateway->user_logged_in();
	}
	

	/**
	 * Check if the user is in live mode and has activated oney
	 *
	 * @return bool
	 */
	public function valid_oney_check() {
		if (!isset($this->settings['mode']) || !isset($this->settings['oney'])) {
			return false;
		}
		return ("yes" === $this->settings['mode'] && "yes" === $this->settings['oney'] && $this->permissions->has_permissions(PayplugPermissions::USE_ONEY));
	}

	/**
	 * Check if CGV checkbox is check
	 *
	 * @return bool
	 */
	public function valid_oney() {
		if (!isset($this->settings['oneycgv'])) {
			return false;
		}
		return ("yes" === $this->settings['oneycgv']);
	}
}