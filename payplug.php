<?php
/**
 * Plugin Name:     PayPlug pour WooCommerce (Officiel)
 * Plugin URI:      https://www.payplug.com/modules/woocommerce
 * Description:     The online payment solution combining simplicity and first-rate support to boost your sales.
 * Author:          PayPlug
 * Author URI:      https://www.payplug.com/
 * Text Domain:     payplug
 * Domain Path:     /languages
 * Version:         1.9.3
 * WC tested up to: 6.8.0
 * License:         GPLv3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 */


namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'PAYPLUG_GATEWAY_VERSION', '1.9.3' );
define( 'PAYPLUG_GATEWAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAYPLUG_GATEWAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PAYPLUG_GATEWAY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin bootstrap function.
 */
function init() {
	if ( file_exists( plugin_dir_path( __FILE__ ) . '/vendor/autoload.php' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';
	}
	PayplugWoocommerceHelper::load_plugin_textdomain( plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	PayplugWoocommerce::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

function wpdocs_translate_text($msgstr, $msgid, $domain)
{
	$pattern = '/^payplug_.+/';

	if (preg_match($pattern, $msgstr) === 1) {
		$path = WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/languages/payplug-en_US.mo';
		$mo = new \MO();
		$mo->import_from_file($path);
		return @$mo->entries[$msgstr]->translations[0];
	}

	return $msgstr;
}
add_filter('gettext_payplug', __NAMESPACE__ . '\\wpdocs_translate_text', 10, 3);
