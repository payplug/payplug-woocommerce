<?php
/**
 * Plugin Name:     PayPlug pour WooCommerce (Officiel)
 * Plugin URI:      https://www.payplug.com/modules/woocommerce
 * Description:     The online payment solution combining simplicity and first-rate support to boost your sales.
 * Author:          PayPlug
 * Author URI:      https://www.payplug.com/
 * Text Domain:     payplug
 * Domain Path:     /languages
 * Version:         2.11.0
 * WC tested up to: 9.4.3
 * Requires plugins: woocommerce
 * License:         GPLv3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 */


namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'PAYPLUG_GATEWAY_VERSION', '2.11.0' );
define( 'PAYPLUG_GATEWAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAYPLUG_GATEWAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PAYPLUG_GATEWAY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin bootstrap function.
 */

/**
 * $mo is the Mo object used to parse the English translations
 *
 * @var \MO
 */
global $mo;
$mo = new \MO();

function init() {
	if ( file_exists( plugin_dir_path( __FILE__ ) . '/vendor/autoload.php' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';
	}
	PayplugWoocommerceHelper::load_plugin_textdomain( plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	PayplugWoocommerce::get_instance();

	// parse the English translation file
	$path = WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/languages/payplug-en_US.mo';
	$GLOBALS["mo"]->import_from_file($path);
}

function create_lock_table(){
	init();
	\Payplug\PayplugWoocommerce\Model\Lock::create_lock_table();
}
// 1. Add minute to the base subscription periods
add_filter('woocommerce_subscription_periods', __NAMESPACE__ . '\\add_minute_subscription_period', 10);
function add_minute_subscription_period($periods) {
	$periods['minute'] = __('minute', 'woocommerce-subscriptions');
	return $periods;
}

// 2. Add support for minute in the WCS time functions
add_filter('wcs_time_functions', __NAMESPACE__ . '\\add_minute_time_functions', 10);
function add_minute_time_functions($time_functions) {
	$time_functions['minute'] = array(
		'sec' => 60,
		'strftime' => '%M',
		'sprintf' => __('%s minute', 'woocommerce-subscriptions'),
		'sprintf_plural' => __('%s minutes', 'woocommerce-subscriptions'),
	);
	return $time_functions;
}

// 3. Add minute to subscription period strings
add_filter('woocommerce_subscription_period_strings', __NAMESPACE__ . '\\add_minute_period_strings', 10);
function add_minute_period_strings($strings) {
	$strings['minute'] = array(
		'singular' => _x('minute', 'minute subscription period', 'woocommerce-subscriptions'),
		'plural'   => _x('minutes', 'minute subscription periods', 'woocommerce-subscriptions'),
	);
	return $strings;
}

// 4. Add minute to subscription lengths
add_filter('woocommerce_subscription_lengths', __NAMESPACE__ . '\\add_minute_subscription_lengths', 10, 2);
function add_minute_subscription_lengths($lengths, $period) {
	$locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
	if ('minute' === $period) {
		$lengths[$locale]['minute'] =  [
			'0'  => __('do not stop', 'woocommerce-subscriptions'),
			'1'  => __('1 minute', 'woocommerce-subscriptions'),
			'2'  => __('2 minutes', 'woocommerce-subscriptions'),
			'3'  => __('3 minutes', 'woocommerce-subscriptions'),
			'4'  => __('4 minutes', 'woocommerce-subscriptions'),
			'5'  => __('5 minutes', 'woocommerce-subscriptions'),
		];
		$lengths['minute'] =  [
			'0'  => __('do not stop', 'woocommerce-subscriptions'),
			'1'  => __('1 minute', 'woocommerce-subscriptions'),
			'2'  => __('2 minutes', 'woocommerce-subscriptions'),
			'3'  => __('3 minutes', 'woocommerce-subscriptions'),
			'4'  => __('4 minutes', 'woocommerce-subscriptions'),
			'5'  => __('5 minutes', 'woocommerce-subscriptions'),
		];
	}
	return $lengths;
}

// 5. Add minute to available intervals
add_filter('woocommerce_subscription_period_interval_strings', __NAMESPACE__ . '\\add_minute_subscription_intervals', 10);
function add_minute_subscription_intervals($intervals) {
	return array_merge($intervals, array('1' => __('every', 'woocommerce-subscriptions')));
}

// 6. Handle next payment calculation for minutes
add_filter('wcs_calculate_next_payment_date', __NAMESPACE__ . '\\calculate_next_minute_payment', 10, 3);
function calculate_next_minute_payment($next_payment, $subscription, $type) {
	if ($subscription && 'minute' === $subscription->get_billing_period()) {
		$from_timestamp = strtotime(current_time('mysql'));
		$interval = $subscription->get_billing_interval();
		$next_payment = gmdate('Y-m-d H:i:s', strtotime("+{$interval} minute", $from_timestamp));
	}
	return $next_payment;
}

// 7. Add support for subscription period validation
add_filter('woocommerce_subscription_periods_validation', __NAMESPACE__ . '\\validate_minute_period', 10, 2);
function validate_minute_period($valid, $period) {
	if ('minute' === $period) {
		$valid = true;
	}
	return $valid;
}

// 8. Add support for subscription length validation
add_filter('woocommerce_subscription_lengths_validation', __NAMESPACE__ . '\\validate_minute_length', 10, 3);
function validate_minute_length($valid, $length, $period) {
	if ('minute' === $period && $length <= 5) {
		$valid = true;
	}
	return $valid;
}

// 9. Add debug logging
add_action('woocommerce_scheduled_subscription_payment', __NAMESPACE__ . '\\log_minute_subscription_payment', 10, 2);
function log_minute_subscription_payment($subscription_id, $subscription) {
	if ('minute' === $subscription->get_billing_period()) {
		error_log(sprintf(
			'Processing minute-based subscription payment for ID: %d at %s',
			$subscription_id,
			current_time('mysql')
		));
	}
}

// 10. Add support for interval validation
add_filter('woocommerce_subscription_period_interval_validation', __NAMESPACE__ . '\\validate_minute_interval', 10, 3);
function validate_minute_interval($valid, $interval, $period) {
	if ('minute' === $period && $interval >= 1 && $interval <= 5) {
		$valid = true;
	}
	return $valid;
}

add_filter('woocommerce_subscriptions_is_duplicate_site', '__return_false');

// Make renewals happen every 5 minutes for testing
add_filter('wcs_debug_tool_force_renewal_time', function($timestamp) {
	return time() + 60;
});

add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\create_lock_table', 10, 2 );
add_action( 'activated_plugin', __NAMESPACE__ . '\\create_lock_table', 10, 2 );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_deactivation_hook( __FILE__,  __NAMESPACE__ .'\\PayplugWoocommerceHelper::plugin_deactivation' );

/**
 * A fail-safe in case a transltion does not exist shows the default translation (English)
 *
 * @param $msgstr string Translated text (Usually starts with "payplug_")
 * @param $msgid string Text to translate (irrelevant because it is equal to $msgstr in this case)
 * @param $domain string the domain is always = "payplug" (filter "gettext_payplug" is only for payplug translation domain)
 *
 * @return string
 */
function wpdocs_translate_text($msgstr, $msgid, $domain)
{
	$pattern = '/^payplug_.+/';

	if (preg_match($pattern, $msgstr) === 1) {
		if(isset($GLOBALS["mo"]->entries[$msgstr]))
			return $GLOBALS["mo"]->entries[$msgstr]->translations[0];
	}

	return $msgstr;
}
add_filter('gettext_payplug', __NAMESPACE__ . '\\wpdocs_translate_text', 10, 3);



