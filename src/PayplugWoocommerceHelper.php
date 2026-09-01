<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Payplug\Authentication;
use Payplug\Exception\ForbiddenException;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use Payplug\Resource\APIResource;
use WC_Blocks_Utils;
use WC_Subscriptions;

/**
 * Helper class.
 */
class PayplugWoocommerceHelper
{
    use ServiceGetter;

    /**
     * Check if current WooCommerce version is below 3.0.0
     *
     * @return bool
     */
    public static function is_pre_30()
    {
        $wc = function_exists('WC') ? WC() : $GLOBALS['woocommerce'];

        return version_compare($wc->version, '3.0.0', '<');
    }

    /**
     * Get a URL to the PayPlug gateway settings page.
     *
     * @return string
     */
    public static function get_setting_link()
    {
        $use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;
        $section_slug = $use_id_as_section ? 'payplug' : strtolower('PayplugGateway');

        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
    }

    /**
     * Get all country code supported by PayPlug.
     *
     * Those are ISO 3166-1 alpha-2. You can find more information on https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
     *
     * @return array
     */
    public static function get_supported_countries()
    {
        return [
            'AD',
            'AO',
            'AX',
            'BG',
            'BO',
            'BY',
            'CH',
            'CR',
            'DE',
            'EE',
            'FI',
            'GB',
            'GL',
            'GT',
            'HR',
            'IN',
            'JM',
            'KM',
            'KZ',
            'LS',
            'MD',
            'MM',
            'MT',
            'NA',
            'NO',
            'PE',
            'PN',
            'RE',
            'SC',
            'SK',
            'ST',
            'TF',
            'TN',
            'UA',
            'VC',
            'WS',
            'AE',
            'AQ',
            'AZ',
            'BH',
            'BQ',
            'BZ',
            'CI',
            'CU',
            'DJ',
            'EG',
            'FJ',
            'GD',
            'GM',
            'GU',
            'HT',
            'IO',
            'JO',
            'KN',
            'LA',
            'LT',
            'ME',
            'MN',
            'MU',
            'NC',
            'NP',
            'PF',
            'PR',
            'RO',
            'SD',
            'SL',
            'SV',
            'TG',
            'TO',
            'UG',
            'VE',
            'YE',
            'AM',
            'AW',
            'BF',
            'BN',
            'BW',
            'CG',
            'CO',
            'AF',
            'CZ',
            'EC',
            'EU',
            'GA',
            'GI',
            'GS',
            'HN',
            'IM',
            'JE',
            'KI',
            'KY',
            'LR',
            'MC',
            'ML',
            'MS',
            'MZ',
            'NL',
            'PA',
            'PM',
            'QA',
            'SB',
            'SJ',
            'SS',
            'TD',
            'TM',
            'TZ',
            'VA',
            'WF',
            'AR',
            'BA',
            'BI',
            'BR',
            'CA',
            'CK',
            'CV',
            'DK',
            'EH',
            'FK',
            'GE',
            'GN',
            'GW',
            'HU',
            'IQ',
            'JP',
            'KP',
            'LB',
            'LU',
            'MF',
            'MO',
            'MV',
            'NE',
            'NR',
            'PG',
            'PS',
            'RS',
            'SE',
            'SM',
            'SX',
            'TH',
            'TR',
            'UM',
            'VG',
            'YT',
            'AL',
            'AU',
            'BE',
            'BM',
            'BV',
            'CF',
            'CN',
            'CY',
            'DZ',
            'ET',
            'FR',
            'GH',
            'GR',
            'HM',
            'IL',
            'IT',
            'KH',
            'KW',
            'LK',
            'MA',
            'MK',
            'MR',
            'MY',
            'NI',
            'OM',
            'PL',
            'PY',
            'SA',
            'SI',
            'SR',
            'TC',
            'TL',
            'TW',
            'UZ',
            'VU',
            'ZW',
            'AI',
            'AT',
            'BD',
            'BL',
            'BT',
            'CD',
            'CM',
            'CX',
            'DO',
            'ES',
            'FO',
            'GG',
            'GQ',
            'HK',
            'IE',
            'IS',
            'KG',
            'KS',
            'LI',
            'LY',
            'MH',
            'MQ',
            'MX',
            'NG',
            'NZ',
            'PK',
            'PW',
            'RW',
            'SH',
            'SO',
            'SZ',
            'TK',
            'TV',
            'UY',
            'VN',
            'ZM',
            'AG',
            'AS',
            'BB',
            'BJ',
            'BS',
            'CC',
            'CL',
            'CW',
            'DM',
            'ER',
            'FM',
            'GF',
            'GP',
            'GY',
            'ID',
            'IR',
            'KE',
            'KR',
            'LC',
            'LV',
            'MG',
            'MP',
            'MW',
            'NF',
            'NU',
            'PH',
            'PT',
            'RU',
            'SG',
            'SN',
            'SY',
            'TJ',
            'TT',
            'US',
            'VI',
            'ZA',
        ];
    }

    /**
     * Ensure country code is supported by PayPlug.
     *
     * @param string $country The ISO 3166-1 alpha-2 code for the country
     *
     * @return bool
     *
     * @author Clément Boirie
     */
    public static function is_country_supported($country)
    {
        $country = trim($country);
        if (empty($country)) {
            return false;
        }

        return in_array(strtoupper($country), self::get_supported_countries());
    }

    /**
     * Get default country.
     *
     * @return string
     */
    public static function get_default_country()
    {
        $country = \WC()->countries->get_base_country();

        return (self::is_country_supported($country)) ? strtoupper($country) : 'FR';
    }

    /**
     * Get minimum amount allowed by PayPlug.
     *
     * This amount is in cents.
     *
     * @return int
     */
    public static function get_minimum_amount()
    {
        return 99;
    }

    /**
     * Get maximum amount allowed by PayPlug.
     *
     * This amount is in cents.
     *
     * @return int
     */
    public static function get_maximum_amount()
    {
        return 2000000;
    }

    /**
     * Convert amount in cents.
     *
     * @param float $amount
     *
     * @return int
     */
    public static function get_payplug_amount($amount)
    {
        if (is_null($amount)) {
            return $amount;
        }

        return absint(wc_format_decimal(((float) $amount * 100), wc_get_price_decimals()));
    }

    /**
     * Extract useful metadata from PayPlug response.
     *
     * @param APIResource $resource
     *
     * @return array
     */
    public static function extract_transaction_metadata($resource)
    {
        // For non-card payment methods (e.g. Oney), the API returns "card": null.
        // Accessing null->property throws \Error in PHP 8+, which is not caught by catch(\Exception).
        $card = (isset($resource->card) && $resource->card !== null) ? $resource->card : null;

        return [
            'transaction_id' => sanitize_text_field($resource->id),
            'paid' => (bool) $resource->is_paid,
            'refunded' => (bool) $resource->is_refunded,
            'amount' => sanitize_text_field($resource->amount),
            'amount_refunded' => sanitize_text_field($resource->amount_refunded),
            '3ds' => (bool) $resource->is_3ds,
            'live' => (bool) $resource->is_live,
            'paid_at' => isset($resource->hosted_payment->paid_at) ? sanitize_text_field($resource->hosted_payment->paid_at) : sanitize_text_field($resource->created_at),
            'card_last4' => $card !== null ? sanitize_text_field($card->last4) : '',
            'card_exp_month' => $card !== null ? sanitize_text_field($card->exp_month) : '',
            'card_exp_year' => $card !== null ? sanitize_text_field($card->exp_year) : '',
            'card_brand' => $card !== null ? sanitize_text_field($card->brand) : '',
            'card_country' => $card !== null ? sanitize_text_field($card->country) : '',
        ];
    }

    /**
     * @param \WC_Order $order
     *
     * @return array|bool
     *
     * @author Clément Boirie
     */
    public static function get_transaction_metadata($order)
    {
        if (self::is_pre_30()) {
            return get_post_meta($order->id, '_payplug_metadata', true);
        } else {
            return $order->get_meta('_payplug_metadata', true);
        }
    }

    /**
     * Save transaction metadata extracted from PayPlug response.
     *
     * @param \WC_Order $order
     * @param array $metadata
     *
     * @return void
     */
    public static function save_transaction_metadata($order, $metadata): void
    {
        if (self::is_pre_30()) {
            update_post_meta($order->id, '_payplug_metadata', $metadata);
        } else {
            $order->add_meta_data('_payplug_metadata', $metadata, true);
            $order->save_meta_data();
        }
    }

    /**
     * Set flag ipn ( in progress / over ) on order
     *
     * @param \WC_Order $order
     * @param array $metadata
     * @param bool $flag
     *
     * @return void
     */
    public static function set_flag_ipn_order($order, $metadata, $flag): void
    {
        $metadata['transaction_in_progress'] = $flag;
        self::save_transaction_metadata($order, $metadata);
    }

    /**
     * Get transient key from payplug option
     *
     * @return string
     */
    public static function get_transient_key($options)
    {
        $transient_key = PayplugGateway::OPTION_NAME . (array_key_exists('mode', $options) && (bool) $options['mode'] ? '_live' : '_test');

        return $transient_key;
    }

    /**
     * Get transient live key from payplug option
     *
     * @return string
     */
    public static function get_live_transient_key()
    {
        return PayplugGateway::OPTION_NAME . '_live';
    }

    /**
     * Set transient data for payplug account
     *
     * @return string
     */
    public static function set_transient_data($data, $options = null)
    {
        $options = $options ? $options : self::get_payplug_options();
        $transient_key = self::get_transient_key($options);
        set_transient($transient_key, isset($data['httpResponse']) ? $data['httpResponse'] : []);
    }

    /**
     * Get current option from payplug settings
     *
     * @return array
     */
    public static function get_account_data_from_options()
    {
        $options = self::get_payplug_options();
        $transient_key = self::get_transient_key($options);
        $account = get_transient($transient_key);

        if (empty($account) || !is_array($account)) {
            self::set_account_data_from_options();
            $account = get_transient($transient_key);
        }

        return $account;
    }

    /**
     * Get current option from payplug settings
     *this should replace get_account_data_from_options
     *
     * @return array
     */
    public static function generic_get_account_data_from_options($gateway_id)
    {
        $options = self::get_payplug_options();
        $transient_key = self::get_transient_key($options);
        $account = get_transient($transient_key);

        //if transient is empty, it goes and get the permissions for the customer to populate it
        if (empty($account) || !is_array($account)) {
            self::set_account_data_from_options();
            $account = get_transient($transient_key);
        }

        if (!empty($account)) {
            $helper = new self();
            $configuration = $helper->get_service('configuration');
            $account['permissions']['payplug'] = $configuration->get_option('payment_methods.configuration.payplug.active');
        }

        return $account;
    }

    /**
     * Set current option from payplug settings and api call
     *
     * @return void
     */
    public static function set_account_data_from_options()
    {
        $helper = new self();
        $configuration = $helper->get_service('configuration');
        $options = $configuration->get_options();

        if (empty($options) || !isset($options['api_key']) || !isset($options['jwt'])) {
            return [];
        }

        // get_bearer_token() refreshes the JWT if it's close to expiry (OAuth2 accounts).
        $mode = (bool) $options['mode'] ? 'live' : 'test';
        $key = (string) $helper->get_api()->get_bearer_token($mode);

        if (empty($key)) {
            return [];
        }

        try {
            $parameters_account = Authentication::getAccount(new Payplug($key));
            self::set_transient_data($parameters_account, $options);
        } catch (\Payplug\Exception\UnauthorizedException $e) {
            self::exception_handler_400_logout($e->getCode(), __('payplug_enable_feature', 'payplug'), sprintf('Account request error from PayPlug API : %s <br><b> ' . __('Successfully logged out.', 'payplug') . '</b>', wc_print_r($e->getMessage(), true)));
        } catch (\Payplug\Exception\ConfigurationNotSetException $e) {
        } catch (\Payplug\Exception\ForbiddenException $e) {
        } catch (\Payplug\Exception\ForbiddenException $e) {
            return [];
        }
    }

    /**
     * Check if oney is available with current settings
     *
     * @return bool
     */
    public static function is_oney_available()
    {
        $account = self::get_account_data_from_options();
        if (!$account) {
            return false;
        }

        $options = self::get_payplug_options();
        $oney_active = (bool) $options['payment_methods']['configuration']['oney']['active'];

        return $account && $account['permissions'][PayplugPermissions::USE_ONEY] == '1' && $oney_active;
    }

    /**
     * Hide popup for if country_code != payplug country
     * https://payplug-prod.atlassian.net/browse/WOOC-249
     *
     * @return bool
     */
    public static function show_oney_popup()
    {
        $account = self::get_account_data_from_options();
        if ($account && $account['permissions'][PayplugPermissions::USE_ONEY] == true && $account['country'] == self::getISOCountryCode()) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function check_order_max_amount($order_total)
    {
        if ($order_total < PayplugGatewayOney3x::MIN_AMOUNT || $order_total > PayplugGatewayOney3x::MAX_AMOUNT) {
            return false;
        }

        return true;
    }

    /**
     * Load translations from plugin languages folder.
     *
     * @param string $plugin_rel_path
     *
     * @return bool
     */
    public static function load_plugin_textdomain($plugin_rel_path)
    {
        $domain = 'payplug';

        $locale = apply_filters('plugin_locale', is_admin() ? get_user_locale() : get_locale(), $domain);

        $mofile = $domain . '-' . $locale . '.mo';

        $path = WP_PLUGIN_DIR . '/' . trim($plugin_rel_path, '/');

        return load_textdomain($domain, $path . '/' . $mofile);
    }

    public static function getISOCountryCode()
    {
        preg_match('([a-z-]+)', get_locale(), $country);

        return strtoupper($country[0]);
    }

    /**
     * Get country of the payplug merchant account and save it to the database
     *
     * @return string payplug merchant account country
     */
    public static function get_payplug_merchant_country()
    {
        $data = self::get_payplug_options();

        //in order to reduce the getAccount calls
        if (isset($data['payplug_live_key'])) {
            if (!isset($data['payplug_merchant_country'])) {
                return self::UpdateCountryOption($data);
            }

            return $data['payplug_merchant_country'];
        }

        $country = wc_get_base_location();

        return $country['country'];
    }

    /**
     * Update payplug options and return the country
     *
     * @param $options
     *
     * @throws \Payplug\Exception\ConfigurationException
     *
     * @return string
     */
    public static function UpdateCountryOption($options)
    {
        if (!isset($options['api_key'])) {
            $options['company_iso'] = 'FR';

            return $options['company_iso'];
        }

        try {
            $api_key = json_decode($options['api_key'], true);

            //fail safe for non activated account
            if ((isset($options['mode'])) && $api_key['test']) {
                $key = $options['mode'] && !empty($api_key['live']) ? $api_key['live'] : $api_key['test'];
            }

            if (empty($api_key['live'])) {
                $options['mode'] = false;
            }

            if (isset($key) && !empty($key)) {
                $response = self::get_account_data_from_options();
            }

            if (isset($response['httpResponse']['country'])) {
                $options['company_iso'] = $response['httpResponse']['country'];
                update_option('woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options));
            } else {

                //default value for merchant country
                $options['company_iso'] = 'FR';
            }
        } catch (ForbiddenException $e) {
            PayplugGateway::log('Error while getting account : ' . $e->getMessage(), 'error');
            \WC_Admin_Settings::add_error($e->getMessage());
            $options['company_iso'] = 'FR';
        }

        return $options['company_iso'];
    }

    public static function get_live_key()
    {
        // get_bearer_token() refreshes the JWT if it's close to expiry (OAuth2 accounts).
        return (new self())->get_api()->get_bearer_token('live');
    }

    public static function get_test_key()
    {
        return (new self())->get_api()->get_bearer_token('test');
    }

    public static function check_mode()
    {
        return self::get_payplug_options()['mode'];
    }

    /**
     * Domain the Integrated Payment SDK should submit card-tokenization requests to. Depends
     * on whether the connected merchant account is QA or production, which is a build-time
     * distinction (see SECURE_DOMAIN in payplug-config.php), not something this can derive
     * from the Test/Live mode toggle at runtime.
     *
     * @return string
     */
    public static function get_secure_domain(): string
    {
        return SECURE_DOMAIN;
    }

    /**
     * URL of the official Oney widget loader script. Depends on whether the connected
     * merchant account is QA or production, which is a build-time distinction (see
     * ONEY_LOADER_URL in payplug-config.php), not something this can derive from the
     * Test/Live mode toggle at runtime.
     *
     * @return string
     */
    public static function get_oney_loader_url(): string
    {
        return ONEY_LOADER_URL;
    }

    public static function payplug_logout(): void
    {
        $helper = new self();
        $helper->get_service('configuration')->clean_option();
        set_transient(self::get_transient_key(self::get_payplug_options()), null);
    }

    public static function plugin_deactivation(): void
    {
        $option_name = 'woocommerce_payplug_settings';
        delete_option($option_name);
        // for site options in Multisite
        delete_site_option($option_name);
        \Payplug\PayplugWoocommerce\Model\Lock::delete_lock_table();
    }

    public static function available_shipping_methods($carriers = [])
    {
        // Build enabled shipping methods based on active Zone
        $enabled_method_types = [];

        if (class_exists('\\WC_Shipping_Zones')) {
            // Regular zones
            $zones = \WC_Shipping_Zones::get_zones();
            foreach ($zones as $zone) {
                $methods = isset($zone['shipping_methods']) ? $zone['shipping_methods'] : [];
                foreach ($methods as $m) {
                    if (!empty($m->enabled)) {
                        $enabled_method_types[$m->id] = true;
                    }
                }
            }

            // Locations not covered by zones (zone 0)
            $defaultZone = new \WC_Shipping_Zone(0);
            foreach ($defaultZone->get_shipping_methods(true) as $m) {
                if (!empty($m->enabled)) {
                    $enabled_method_types[$m->id] = true;
                }
            }
        }

        $shippings = WC()->shipping()->get_shipping_methods();
        $shippings_methods = [];

        foreach ($shippings as $shipping) {
            // Only keep shipping method types that are currently enabled in at least one zone
            if (!isset($enabled_method_types[$shipping->id])) {
                continue;
            }
            $shippings_methods[] = [
                'id_carrier' => $shipping->id,
                'name' => $shipping->method_title,
                'checked' => in_array($shipping->id, $carriers, true),
            ];
        }

        return $shippings_methods;
    }

    public static function get_payplug_options()
    {
        $helper = new self();

        return $helper->get_service('configuration')->get_options();
    }

    public static function get_applepay_options()
    {
        $options = self::get_payplug_options();
        $applepay_configuration = $options['payment_methods']['configuration']['apple_pay'];
        $applepay_display = json_decode($applepay_configuration['display'], true);
        $applepay = [
            'enabled' => (bool) $applepay_configuration['active'],
            'checkout' => (bool) $applepay_display['checkout'],
            'cart' => (bool) $applepay_display['cart'],
            'carriers' => json_decode($applepay_configuration['carriers'], true),
        ];

        return $applepay;
    }

    public static function is_checkout_block()
    {
        return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
    }

    public static function is_cart_block()
    {
        return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('cart'), 'woocommerce/cart');
    }

    public static function is_product_block()
    {
        return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('product'), 'woocommerce/product');
    }

    /**
     * Checks if subscriptions are enabled on the site.
     *
     * @return bool Whether subscriptions is enabled or not.
     *
     * @since 5.6.0
     */
    public static function is_subscriptions_enabled()
    {
        return class_exists('WC_Subscriptions') && class_exists('WC_Subscription') && version_compare(WC_Subscriptions::$version, '2.2.0', '>=');
    }

    /**
     * Cart has any subscriptions
     *
     * @return bool
     */
    public static function is_subscription()
    {
        if (is_null(WC()->cart)) {
            wc_load_cart();
            WC()->cart->get_cart_from_session();
        }

        if (empty(WC()->cart) || empty(WC()->cart->get_cart())) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $prod) {
            if (empty($prod['product_id'])) {
                return false;
            }

            $pid = $prod['product_id'];
            $product = wc_get_product($pid);

            if ($product->is_type('subscription')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handling 400 errors from API (get_account, get_permissions) and return JSON rest response
     *
     * @param $title
     * @param $msg
     *
     * @return false
     */
    public static function exception_handler_400_logout($error_code, $title, $msg)
    {
        if (!empty($error_code) && ($error_code === 401 || $error_code === 403)) {
            self::payplug_logout();
            wp_send_json_error([
                'title' => $title,
                'msg' => $msg,
                'close' => __('payplug_ok', 'payplug'),
            ]);
        }

        return false;
    }
}
