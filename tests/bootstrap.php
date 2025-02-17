<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package payplug
 */

//Please update here with your absolute project paths
$_tests_dir = '/opt/homebrew/var/www/local.woocommerce.com/tmp/wordpress-tests-lib';
$_core_dir = '/opt/homebrew/var/www/local.woocommerce.com';  // Navigate up to WordPress root

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Set system path constants
define('WP_TESTS_DIR', $_tests_dir);
define('WP_CORE_DIR', $_core_dir);

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Load Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( dirname( __FILE__ ) ) . '/payplug.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

activate_plugin('woocommerce/woocommerce.php');

// Activate your plugin
$plugin_basename = basename(dirname(dirname(__FILE__))) . '/payplug.php';
activate_plugin($plugin_basename);
