<?php
/**
 * Plugin Name:     Payplug
 * Plugin URI:      https://www.payplug.com/modules/woocomerce
 * Description:     The online payment solution created for SMEs
 * Author:          PayPlug
 * Author URI:      https://www.payplug.com/
 * Text Domain:     payplug
 * Domain Path:     /languages
 * Version:         0.1.0
 * License:         GPLv3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAYPLUG_GATEWAY_VERSION', '0.1.0' );
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

	load_plugin_textdomain( 'payplug', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	PayplugWoocommerce::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
