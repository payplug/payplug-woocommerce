<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\Exception\ForbiddenException;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\APIResource;
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;

/**
 * Helper class.
 *
 * @package Payplug\PayplugWoocommerce
 */
class PayplugWoocommerceHelper {

	/**
	 * Check if current WooCommerce version is below 3.0.0
	 *
	 * @return bool
	 */
	public static function is_pre_30() {
		$wc = function_exists( 'WC' ) ? WC() : $GLOBALS['woocommerce'];

		return version_compare( $wc->version, '3.0.0', '<' );
	}

	/**
	 * Get a URL to the PayPlug gateway settings page.
	 *
	 * @return string
	 */
	public static function get_setting_link() {
		$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;
		$section_slug      = $use_id_as_section ? 'payplug' : strtolower( 'PayplugGateway' );

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
	}

	/**
	 * Get all country code supported by PayPlug.
	 *
	 * Those are ISO 3166-1 alpha-2. You can find more information on https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
	 *
	 * @return array
	 */
	public static function get_supported_countries() {
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
			'ZA'
		];
	}

	/**
	 * Ensure country code is supported by PayPlug.
	 *
	 * @param string $country The ISO 3166-1 alpha-2 code for the country
	 *
	 * @return bool
	 * @author Clément Boirie
	 */
	public static function is_country_supported( $country ) {
		$country = trim( $country );
		if ( empty( $country ) ) {
			return false;
		}

		return in_array( strtoupper( $country ), self::get_supported_countries() );
	}

	/**
	 * Get default country.
	 *
	 * @return string
	 */
	public static function get_default_country() {
		$country = \WC()->countries->get_base_country();

		return ( self::is_country_supported( $country ) ) ? strtoupper( $country ) : 'FR';
	}

	/**
	 * Get minimum amount allowed by PayPlug.
	 *
	 * This amount is in cents.
	 *
	 * @return int
	 */
	public static function get_minimum_amount() {
		return 99;
	}

	/**
	 * Get maximum amount allowed by PayPlug.
	 *
	 * This amount is in cents.
	 *
	 * @return int
	 */
	public static function get_maximum_amount() {
		return 2000000;
	}

	/**
	 * Convert amount in cents.
	 *
	 * @param float $amount
	 *
	 * @return int
	 */
	public static function get_payplug_amount( $amount ) {
		if ( is_null( $amount ) ) {
			return $amount;
		}

		return absint( wc_format_decimal( ( (float) $amount * 100 ), wc_get_price_decimals() ) );
	}

	/**
	 * Extract useful metadata from PayPlug response.
	 *
	 * @param APIResource $resource
	 *
	 * @return array
	 */
	public static function extract_transaction_metadata( $resource ) {
		return [
			'transaction_id'  => sanitize_text_field( $resource->id ),
			'paid'            => (bool) $resource->is_paid,
			'refunded'        => (bool) $resource->is_refunded,
			'amount'          => sanitize_text_field( $resource->amount ),
			'amount_refunded' => sanitize_text_field( $resource->amount_refunded ),
			'3ds'             => (bool) $resource->is_3ds,
			'live'            => (bool) $resource->is_live,
			'paid_at'         => isset( $resource->hosted_payment->paid_at ) ? sanitize_text_field( $resource->hosted_payment->paid_at ) : sanitize_text_field( $resource->created_at ),
			'card_last4'      => sanitize_text_field( $resource->card->last4 ),
			'card_exp_month'  => sanitize_text_field( $resource->card->exp_month ),
			'card_exp_year'   => sanitize_text_field( $resource->card->exp_year ),
			'card_brand'      => sanitize_text_field( $resource->card->brand ),
			'card_country'    => sanitize_text_field( $resource->card->country ),
		];
	}

	/**
	 * @param \WC_Order $order
	 *
	 * @return array|bool
	 * @author Clément Boirie
	 */
	public static function get_transaction_metadata( $order ) {

		if ( PayplugWoocommerceHelper::is_pre_30() ) {
			return get_post_meta( $order->id, '_payplug_metadata', true );
		} else {
			return $order->get_meta( '_payplug_metadata', true );
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
	public static function save_transaction_metadata( $order, $metadata ) {

		if ( PayplugWoocommerceHelper::is_pre_30() ) {
			update_post_meta( $order->id, '_payplug_metadata', $metadata );
		} else {
			$order->add_meta_data( '_payplug_metadata', $metadata, true );

			if ( is_callable( [ $order, 'save' ] ) ) {
				$order->save();
			}
		}
	}

	/**
	 * Set flag ipn ( in progress / over ) on order
	 *
	 * @param \WC_Order $order
	 * @param array $metadata
	 * @param boolean $flag
	 *
	 * @return void
	 */
	public static function set_flag_ipn_order ( $order, $metadata, $flag) {
		$metadata['transaction_in_progress'] = $flag;
		PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);
	}

	/**
	 * Get transient key from payplug option
	 *
	 * @return string
	 */
	public static function get_transient_key($options)
	{
		$transient_key = PayplugGateway::OPTION_NAME . (array_key_exists('mode', $options) && $options['mode'] === 'yes' ? "_live" : "_test");
		return $transient_key;
	}


	/**
	 * Get transient live key from payplug option
	 *
	 * @return string
	 */
	public static function get_live_transient_key()
	{
		return PayplugGateway::OPTION_NAME .  "_live";
	}

	/**
	 * Set transient data for payplug account
	 *
	 * @return string
	 */
	public static function set_transient_data($data, $options = null)
	{
		$options = $options ? $options : get_option('woocommerce_payplug_settings', []);
		$transient_key = PayplugWoocommerceHelper::get_transient_key($options);
		set_transient($transient_key, isset($data['httpResponse']) ? $data['httpResponse'] : []);
	}

	/**
	 * Get current option from payplug settings
	 *
	 * @return array
	 */
	public static function get_account_data_from_options()
	{
		$options = get_option('woocommerce_payplug_settings', []);
		$transient_key = self::get_transient_key($options);
		$account = get_transient($transient_key);

		if(empty($account) || !is_array($account) ){
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
	public static function generic_get_account_data_from_options($gateway_id){
		$options = get_option('woocommerce_payplug_settings', []);
		$transient_key = self::get_transient_key($options);
		$account = get_transient($transient_key);

		//if transient is empty, it goes and get the permissions for the customer to populate it
		if(empty($account) || !is_array($account) ){
			self::set_account_data_from_options();
			$account = get_transient($transient_key);
		}

		if( isset($options[$gateway_id]) && $options[$gateway_id] == "yes"){
			$account['permissions'][$gateway_id] = true;
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
		$options          = get_option('woocommerce_payplug_settings', []);
		$payplug_test_key = !empty($options['payplug_test_key']) ? $options['payplug_test_key'] : '';
		$payplug_live_key = !empty($options['payplug_live_key']) ? $options['payplug_live_key'] : '';

		if (empty($payplug_test_key) && empty($payplug_live_key)) {
			return array();
		}

		if( $options['mode'] === 'yes' && empty($payplug_live_key) ){
			return array();
		}

		if( $options['mode'] != 'yes' && empty($payplug_test_key)){
			return array();
		}

		try {
			$parameters_account = Authentication::getAccount(new Payplug($options['mode'] === 'yes' ? $payplug_live_key : $payplug_test_key));
			self::set_transient_data($parameters_account, $options);
		} catch (\Payplug\Exception\UnauthorizedException $e) {
		} catch (\Payplug\Exception\ConfigurationNotSetException $e) {
		} catch( \Payplug\Exception\ForbiddenException $e){
		} catch (\Payplug\Exception\ForbiddenException $e){return array();}

	}

	/**
	 * Get min and max for oney payment
	 *
	 * @return array
	 */
	public static function get_min_max_oney() {
		$account = self::get_account_data_from_options();
		if (!$account) {
			return array();
		}
		return [
			'min' => floatval($account['configuration']['oney']['min_amounts']['EUR'])/100,
			'max' => floatval($account['configuration']['oney']['max_amounts']['EUR'])/100
		];
	}

	/**
	 * Check if oney is available with current settings
	 *
	 * @return boolean
	 */
	public static function is_oney_available() {
		$account = self::get_account_data_from_options();
		if (!$account) {
			return false;
		}

		$options = self::get_payplug_options();
		$oney_active = (isset($options['oney']) && !empty($options['oney'])) ? $options['oney'] : '';

		return ($account && $account['permissions'][PayplugPermissions::USE_ONEY] == "1" && $oney_active === "yes");
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
		if(  $account && $account['permissions'][PayplugPermissions::USE_ONEY] == true && $account["country"] == self::getISOCountryCode() ){
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	public static function check_order_max_amount($order_total){
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
	public static function load_plugin_textdomain( $plugin_rel_path ) {

		$domain = 'payplug';

		$locale = apply_filters( 'plugin_locale', is_admin() ? get_user_locale() : get_locale(), $domain );

		$mofile = $domain . '-' . $locale . '.mo';

		$path = WP_PLUGIN_DIR . '/' . trim( $plugin_rel_path, '/' );

		return load_textdomain( $domain, $path . '/' . $mofile );
	}

	/**
	 * Check and update value for oney simulation
	 *
	 * @return void
	 */
	public static function oney_simulation_values ($keys_array, &$array) {
		foreach($keys_array as $key) {
			if (array_key_exists($key, $array)) {
				$array[$key]['down_payment_amount'] = floatval($array[$key]['down_payment_amount']) / 100;
				foreach ($array[$key]['installments'] as $k => $value) {
					$array[$key]['installments'][$k]['amount'] = floatval($value['amount']) / 100;
				}
			}
		}
	}

	public static function getISOCountryCode()
	{
		preg_match( '([a-z-]+)', get_locale(), $country );
		return strtoupper($country[0]);
	}

	/**
	 * Get country of the payplug merchant account and save it to the database
	 *
	 * @return string payplug merchant account country
	 */
	public static function get_payplug_merchant_country()
	{
		$data = get_option('woocommerce_payplug_settings');

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
	 * @return string
	 * @throws \Payplug\Exception\ConfigurationException
	 */
	public static function UpdateCountryOption($options){

		try {

			//fail safe for non activated account
			if ((isset($options['mode'])) && $options['payplug_test_key']){
				$key = $options['mode'] === "yes" && !empty($options['payplug_live_key']) ? $options['payplug_live_key'] : $options['payplug_test_key'];
			}

			if( empty($options['payplug_live_key'] )){
				$options['mode'] = "no";
			}

			if (isset($key) && !empty($key)){

				//$response = Authentication::getAccount(new Payplug($key));
				$response = self::get_account_data_from_options();
			}

			if (isset($response['httpResponse']['country'])) {
				$options['payplug_merchant_country'] = $response['httpResponse']['country'];
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
			}else{

				//default value for merchant country
				$options['payplug_merchant_country'] = 'FR';
			}

		} catch (ForbiddenException $e) {
			PayplugGateway::log('Error while getting account : ' . $e->getMessage(), 'error');
			\WC_Admin_Settings::add_error($e->getMessage());
			$options['payplug_merchant_country'] = 'FR';
		}

		return $options['payplug_merchant_country'];

	}

	public static function get_live_key()
	{
		return get_option('woocommerce_payplug_settings', [])['payplug_live_key'];
	}

	public static function get_test_key()
	{
		return get_option('woocommerce_payplug_settings', [])['payplug_test_key'];
	}

	/**
	 *
	 * Checks fi the user is logged in
	 *
	 * @return bool
	 */
	public static function user_logged_in()
	{
		return !empty(get_option( 'woocommerce_payplug_settings', [] )['payplug_test_key']);
	}

	public static function check_mode(){

		if(empty(get_option( 'woocommerce_payplug_settings', [] ))){
			return false;
		}

		return get_option( 'woocommerce_payplug_settings', [] )['mode'] === "yes" ? true : false;
	}

	public static function payplug_logout($gateway) {

		if ($gateway->user_logged_in()) {
			$data                        = get_option($gateway->get_option_key());
			$data['payplug_test_key']    = '';
			$data['payplug_live_key']    = '';
			$data['payplug_merchant_id'] = '';
			$data['enabled']             = 'no';
			$data['mode']                = 'no';
			$data['oneclick']            = 'no';
			update_option(
				$gateway->get_option_key(),
				apply_filters('woocommerce_settings_api_sanitized_fields_' . $gateway->id, $data)
			);
			if("payplug" === $gateway->id) {
				return true;
			}
		} else {
			return false;
		}
	}

	public static function plugin_deactivation(){
		$option_name = 'woocommerce_payplug_settings';
		delete_option( $option_name );
		// for site options in Multisite
		delete_site_option( $option_name );
		\Payplug\PayplugWoocommerce\Model\Lock::delete_lock_table();
	}

	public static function available_shipping_methods($carriers = []) {
		$shippings = WC()->shipping()->get_shipping_methods();
		$shippings_methods = [];
		foreach ($shippings as $shipping) {
			array_push($shippings_methods,[
				"id_carrier" => $shipping->id,
				"name" => $shipping->method_title,
				"checked" => in_array($shipping->id, $carriers)
			]);
		}
		return $shippings_methods;
	}

	public static function get_payplug_options() {
		return get_option('woocommerce_payplug_settings', []);
	}

	public static function get_applepay_options() {
		$options = self::get_payplug_options();
		$applepay = [
			"enabled" => (!empty($options['apple_pay'])) ? $options['apple_pay'] : false,
			"checkout" => (!empty($options['applepay_checkout'])) ? $options['applepay_checkout'] : false,
			"cart" => (!empty($options['applepay_cart'])) ? $options['applepay_cart'] : false,
			"carriers" => (!empty($options['applepay_carriers'])) ? $options['applepay_carriers'] : [],

		];

		return $applepay;
	}

	public static function is_checkout_block() {
		return WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' );
	}

	public static function is_cart_block() {
		return WC_Blocks_Utils::has_block_in_page( wc_get_page_id('cart'), 'woocommerce/cart' );
	}
}
