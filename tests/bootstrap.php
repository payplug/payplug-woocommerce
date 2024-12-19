<?php
/**
 * PHPUnit bootstrap file
 */

// Define test environment constants
define('WP_TESTS_DOMAIN', 'woocommerce.local');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');

// Define WordPress paths
$_tests_dir = '/Users/dghabri/Documents/wordpress/tmp/wordpress-tests-lib';
$_core_dir = '/Users/dghabri/Documents/wordpress';  // Navigate up to WordPress root

// Ensure trailing slashes
$_tests_dir = rtrim($_tests_dir, '/') . '/';
$_core_dir = rtrim($_core_dir, '/') . '/';

// Set system path constants
define('WP_TESTS_DIR', $_tests_dir);
define('WP_CORE_DIR', $_core_dir);

// Load Composer autoloader
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Load test functions
require_once '/Users/dghabri/Documents/wordpress/tmp/wordpress-tests-lib/includes/functions.php';

tests_add_filter('muplugins_loaded', function() {
	// Load your plugin - adjust the path based on your plugin's main file name
	require dirname(dirname(__FILE__)) . '/payplug.php';
});

// Start up the WP testing environment
require $_tests_dir . 'includes/bootstrap.php';

// Load WooCommerce
tests_add_filter('muplugins_loaded', function() {
	// Load WooCommerce
	require_once WP_CORE_DIR . '/wp-content/plugins/woocommerce/woocommerce.php';

	// Load your plugin - adjust the path based on your plugin's main file name
	require dirname(dirname(__FILE__)) . '/your-plugin-main.php';
});

activate_plugin('woocommerce/woocommerce.php');

// Activate your plugin
$plugin_basename = basename(dirname(dirname(__FILE__))) . '/your-plugin-main.php';
activate_plugin($plugin_basename);
