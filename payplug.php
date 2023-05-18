<?php
/**
 * Plugin Name:     PayPlug pour WooCommerce (Officiel)
 * Plugin URI:      https://www.payplug.com/modules/woocommerce
 * Description:     The online payment solution combining simplicity and first-rate support to boost your sales.
 * Author:          PayPlug
 * Author URI:      https://www.payplug.com/
 * Text Domain:     payplug
 * Domain Path:     /languages
 * Version:         2.4.0
 * WC tested up to: 7.5.1
 * License:         GPLv3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 */


namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'PAYPLUG_GATEWAY_VERSION', '2.4.0' );
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
	error_log("\n\n \$create_lock_table = ".print_r('create_lock_table',true),3,"./file.log");

	init();
	\Payplug\PayplugWoocommerce\Model\Lock::create_lock_table();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
register_activation_hook( __FILE__,  __NAMESPACE__ .'\\create_lock_table' );
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



function create_lock_table_action($upgrader, $options)
{
	$payplug_id=["type" => 'plugin', "action" => 'install']; // to be improved with id of our plugin well setted

	// only run on our plugin update
	if ($options == $payplug_id) {
		error_log("\n\n create_lock_table = " . print_r($options, true), 3, "./file.log");
		create_lock_table();
	}
}

add_action( 'upgrader_process_complete',  __NAMESPACE__.'\\create_lock_table_action',10,2);

