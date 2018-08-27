<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
use const OPENSSL_VERSION_TEXT;
use const PHP_VERSION;
use function sprintf;
use function var_dump;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayplugGatewayRequirements {

	const PHP_MIN = '5.6';
	const OPENSSL_MIN = 268439567;

	/**
	 * @var PayplugGateway
	 */
	private $gateway;

	/**
	 * PayplugGatewayRequirements constructor.
	 *
	 * @param PayplugGateway $gateway
	 */
	public function __construct( PayplugGateway $gateway ) {
		$this->gateway = $gateway;
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
			: '<p class="failed">' . __( sprintf( 'Your PHP version %s is not supported. Your server must run PHP 5.6 or greater.', PHP_VERSION ), 'payplug' ) . '</p>';
	}

	/**
	 * @return string
	 */
	public function openssl_requirement() {
		return ( $this->valid_openssl() )
			? '<p class="success">' . __( 'OpenSSL is up to date.', 'payplug' ) . '</p>'
			: '<p class="failed">' . __( sprintf( 'Your OpenSSL version %s is not supported. OpenSSL 1.0.1 or later.', OPENSSL_VERSION_TEXT ), 'payplug' ) . '</p>';
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
}