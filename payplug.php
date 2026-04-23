<?php
/**
 * Plugin Name:     PayPlug pour WooCommerce (Officiel)
 * Plugin URI:      https://www.payplug.com/modules/woocommerce
 * Description:     The online payment solution combining simplicity and first-rate support to boost your sales.
 * Author:          PayPlug
 * Author URI:      https://www.payplug.com/
 * Text Domain:     payplug
 * Domain Path:     /languages
 * Version:         2.17.2
 * WC tested up to: 10.6.1
 * Requires plugins: woocommerce
 * License:         GPLv3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('PAYPLUG_GATEWAY_VERSION', '2.17.2');
define('PAYPLUG_MAX_VERSION_FOR_UPGRADE', '2.16.1');
define('PAYPLUG_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAYPLUG_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PAYPLUG_GATEWAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

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

function init()
{
    if (file_exists(plugin_dir_path(__FILE__) . '/vendor/autoload.php')) {
        require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';
    }

    if (file_exists(plugin_dir_path(__FILE__) . DIRECTORY_SEPARATOR . 'payplug-config.php')) {
        require_once plugin_dir_path(__FILE__) . DIRECTORY_SEPARATOR . 'payplug-config.php';
    }

    PayplugWoocommerceHelper::load_plugin_textdomain(plugin_basename(dirname(__FILE__)) . '/languages');
    PayplugWoocommerce::get_instance();

    // parse the English translation file
    $path = WP_PLUGIN_DIR . '/' . plugin_basename(dirname(__FILE__)) . '/languages/payplug-en_US.mo';
    $GLOBALS['mo']->import_from_file($path);
}

function create_lock_table()
{
    init();
    \Payplug\PayplugWoocommerce\Model\Lock::create_lock_table();
}

add_action('upgrader_process_complete', __NAMESPACE__ . '\\create_lock_table', 10, 2);
add_action('activated_plugin', __NAMESPACE__ . '\\create_lock_table', 10, 2);
add_action('plugins_loaded', __NAMESPACE__ . '\\init');

/**
 * ============================================================================
 * MCP (Model Context Protocol) Integration for WooCommerce
 * ============================================================================
 */

// Register PayPlug ability category
add_action('wp_abilities_api_categories_init', function () {
    wp_register_ability_category(
        'payplug',
        array(
            'label'       => __('PayPlug Payments', 'payplug'),
            'description' => __('Abilities for PayPlug payment processing in WooCommerce.', 'payplug'),
        )
    );
});

// Register PayPlug abilities
add_action('wp_abilities_api_init', function () {
    wp_register_ability(
        'payplug/create-payment-link',
        array(
            'label'               => __('Create Payment Link', 'payplug'),
            'description'         => __('Creates a payment link to send to a customer for online payment in WooCommerce.', 'payplug'),
            'category'            => 'payplug',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'customer' => array(
                        'type'        => 'object',
                        'description' => __('Customer information object.', 'payplug'),
                        'properties'  => array(
                            'customer_id'                          => array('type' => 'integer', 'description' => __('WooCommerce customer ID.', 'payplug')),
                            'customer_address_title'               => array('type' => 'string', 'description' => __('Customer civility: "mr" or "mrs".', 'payplug')),
                            'customer_address_first_name'          => array('type' => 'string', 'description' => __('Customer first name.', 'payplug')),
                            'customer_address_last_name'           => array('type' => 'string', 'description' => __('Customer last name.', 'payplug')),
                            'customer_address_mobile_phone_number' => array('type' => 'string', 'description' => __('Phone number in E.164 format.', 'payplug')),
                            'customer_address_email'               => array('type' => 'string', 'description' => __('Customer email address.', 'payplug')),
                            'customer_address_address1'            => array('type' => 'string', 'description' => __('Primary street address.', 'payplug')),
                            'customer_address_address2'            => array('type' => 'string', 'description' => __('Secondary address line.', 'payplug')),
                            'customer_address_postcode'            => array('type' => 'string', 'description' => __('Postal/ZIP code.', 'payplug')),
                            'customer_address_city'                => array('type' => 'string', 'description' => __('City name.', 'payplug')),
                            'customer_address_country'             => array('type' => 'string', 'description' => __('Country ISO code (e.g., FR).', 'payplug')),
                            'customer_address_language'            => array('type' => 'string', 'description' => __('Language ISO code (e.g., fr).', 'payplug')),
                        ),
                        'required'    => array('customer_id', 'customer_address_email', 'customer_address_first_name', 'customer_address_last_name'),
                    ),
                    'cart'     => array(
                        'type'        => 'object',
                        'description' => __('Shopping cart with products.', 'payplug'),
                        'properties'  => array(
                            'products'        => array(
                                'type'        => 'array',
                                'description' => __('Array of products to add to cart.', 'payplug'),
                                'items'       => array(
                                    'type'       => 'object',
                                    'properties' => array(
                                        'product_id'   => array('type' => 'integer', 'description' => __('WooCommerce product ID.', 'payplug')),
                                        'qty'          => array('type' => 'integer', 'description' => __('Quantity.', 'payplug')),
                                        'variation_id' => array('type' => 'integer', 'description' => __('Variation ID for variable products.', 'payplug')),
                                    ),
                                    'required'   => array('product_id', 'qty'),
                                ),
                            ),
                            'shipping_method' => array('type' => 'string', 'description' => __('Shipping method ID.', 'payplug')),
                        ),
                        'required'    => array('products'),
                    ),
                ),
                'required'   => array('customer', 'cart'),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'result'      => array('type' => 'boolean'),
                    'code'        => array('type' => 'integer'),
                    'message'     => array('type' => 'string'),
                    'order_id'    => array('type' => 'integer'),
                    'payment_url' => array('type' => 'string'),
                ),
            ),
            'execute_callback'    => function ($input) {
                $mcp = new \Payplug\PayplugWoocommerce\Service\Mcp();
                return $mcp->createByLink($input['customer'], $input['cart']);
            },
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce');
            },
        )
    );
});

// Enable MCP integration for WooCommerce
add_filter('woocommerce_features', function ($features) {
    $features['mcp_integration'] = true;
    return $features;
});


// Include PayPlug abilities in WooCommerce MCP Server
add_filter('woocommerce_mcp_include_ability', function ($include, $ability_id) {
	if (str_starts_with($ability_id, 'payplug/')) {
		return true;
	}
	return $include;
}, 10, 2);

add_filter( 'woocommerce_mcp_ability_permissions', function( $permissions ) {
	// Autorise les utilisateurs ayant la capacité de gérer WooCommerce
	$permissions['payplug/create-payment-link'] = 'manage_woocommerce';
	return $permissions;
});
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\PayplugWoocommerceHelper::plugin_deactivation');

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
        if (isset($GLOBALS['mo']->entries[$msgstr])) {
            return $GLOBALS['mo']->entries[$msgstr]->translations[0];
        }
    }

    return $msgstr;
}
add_filter('gettext_payplug', __NAMESPACE__ . '\\wpdocs_translate_text', 10, 3);
