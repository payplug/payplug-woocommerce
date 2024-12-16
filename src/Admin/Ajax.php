<?php

namespace Payplug\PayplugWoocommerce\Admin;

// Exit if accessed directly
use Payplug\Exception\HttpException;
use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Admin\Vue;
use Payplug\PayplugWoocommerce\Controller\ApplePay;
use Payplug\PayplugWoocommerce\Gateway\AmericanExpress;
use Payplug\PayplugWoocommerce\Gateway\Bancontact;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\Gateway\PayplugPermissions;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Ideal;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Mybank;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Satispay;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Exception\PayplugException;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PayPlug admin ajax handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Ajax {

	public function __construct() {
		$permission = ( current_user_can('editor') || current_user_can('administrator') );

		add_action( 'rest_api_init', function () use ($permission) {
			//Path to REST route and the callback function
			register_rest_route( 'payplug_api', '/save/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'payplug_save_data' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/init/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'payplug_init' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/login/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'payplug_login' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/logout/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'payplug_logout' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/refresh_keys/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'refresh_keys' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/check_requirements/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'payplug_check_requirements' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/bancontact_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_bancontact_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/applepay_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_applepay_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/american_express_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_american_express_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/oney_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_oney_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/one_click_permission/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_one_click_permission' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/satispay_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_satispay_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/mybank_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_mybank_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/ideal_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_ideal_permissions' ],
				'permission_callback' => function () use ($permission)  {return $permission ;},
				'show_in_index' => false
			) );
			register_rest_route( 'payplug_api', '/integrated_permissions/', array(
				'methods' => 'POST',
				'callback' => [ $this, 'api_check_integrated_payment' ],
				'permission_callback' => function () use ($permission) { return $permission ; }
			) );

		});
	}

	public function refresh_keys(WP_REST_Request $request) {
		$data = $request->get_params();
		$email    = sanitize_text_field( wp_unslash( $data['payplug_email'] ) );
		$password = base64_decode(wp_unslash($data['payplug_password']));

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request.', 'payplug' ),
				)
			);
		}

		if ( ! WC()->payment_gateways() ) {
			wp_send_json_error(
				array(
					'message' => __( 'An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug' ),
				)
			);
		}

		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $payment_gateways ) || ! isset( $payment_gateways['payplug'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug' ),
				)
			);
		}

		/* @var PayplugGateway $payplug_gateway */
		$payplug_gateway = $payment_gateways['payplug'];
		$keys            = $payplug_gateway->retrieve_user_api_keys( $email, $password );
		if ( is_wp_error( $keys ) ) {
			wp_send_json_error(
				array(
					'message' => $keys->get_error_message(),
				)
			);
		}

		$success = $this->update_api_keys( $keys, $payplug_gateway );

		if ( empty( $keys['live'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug' ),
					'still_inactive' => true
				)
			);
		}

		if ( ! $success ) {
			wp_send_json_error(
				array(
					'message' => __( 'Something went wrong.', 'payplug' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your API keys has successfully been updated.', 'payplug' )
			)
		);
	}

	public function api_check_one_click_permission(WP_REST_Request $request){
		wp_send_json_success(true);
	}

	public function api_check_bancontact_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, Bancontact::ENABLE_ON_TEST_MODE);

		if(isset($account['httpResponse']['payment_methods']['bancontact']['enabled']) && $account['httpResponse']['payment_methods']['bancontact']['enabled']){
			wp_send_json_success(true);
		}

		wp_send_json_error(array(
			"title" => __( 'payplug_enable_feature', 'payplug' ),
			"msg" => __( 'payplug_bancontact_access_error', 'payplug' ),
			"close" => __( 'payplug_ok', 'payplug' )
		));

	}

	public function api_check_applepay_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, ApplePay::ENABLE_ON_TEST_MODE);

		if ($account['httpResponse']['payment_methods']['apple_pay']['enabled']) {
			if (in_array(strtr(get_site_url(), array("http://" => "", "https://" => "")), $account['httpResponse']['payment_methods']['apple_pay']['allowed_domain_names'])) {
				wp_send_json_success(true);
			}

		}

		wp_send_json_error(array(
			"title" => __( 'payplug_enable_feature', 'payplug' ),
			"msg" => __( 'payplug_applepay_access_error', 'payplug' ),
			"close" => __( 'payplug_ok', 'payplug' )
		));

	}

	public function api_check_american_express_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, AmericanExpress::ENABLE_ON_TEST_MODE);

		if(isset($account['httpResponse']['payment_methods']['american_express']['enabled']) && $account['httpResponse']['payment_methods']['american_express']['enabled']){
			wp_send_json_success(true);
		}

		wp_send_json_error(array(
			"title" => __( 'payplug_enable_feature', 'payplug' ),
			"msg" => __( 'payplug_amex_access_error', 'payplug' ),
			"close" => __( 'payplug_ok', 'payplug' )
		));

	}

	public function api_check_oney_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, PayplugGatewayOney3x::ENABLE_ON_TEST_MODE);

		if(isset($account['httpResponse']['permissions']['can_use_oney']) && $account['httpResponse']['permissions']['can_use_oney']){
			wp_send_json_success(true);
		}

		$oney = isset($account['httpResponse']['permissions']['can_use_oney']) ? $account['httpResponse']['permissions']['can_use_oney']: false;

		if(!$oney){
			$anchor_text = __( 'payplug_oney_error_link', 'payplug' );
			$anchor_url = "https://portal.payplug.com/login";
			$anchor   = sprintf(  ' <a href="%s" target="_blank">%s</a>', $anchor_url, $anchor_text );
			$message = __( 'payplug_oney_error_description', 'payplug' ) . $anchor;
			wp_send_json_error(array(
				"title" => __( 'payplug_oney_error_title', 'payplug' ),
				"msg" => $message,
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

		wp_send_json_success(true);
	}

	public function api_check_satispay_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, Satispay::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['satispay']['enabled']) ? $account['httpResponse']['payment_methods']['satispay']['enabled']: false;
		if(!$enabled){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __( 'payplug_satispay_access_error', 'payplug' ),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

		wp_send_json_success($enabled);
	}

	public function api_check_mybank_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, Mybank::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['mybank']['enabled']) ? $account['httpResponse']['payment_methods']['mybank']['enabled']: false;
		if(!$enabled){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __( 'payplug_mybank_access_error', 'payplug' ),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

		wp_send_json_success($enabled);
	}

	public function api_check_ideal_permissions(WP_REST_Request $request) {
		$account = $this->generic_get_account($request, Ideal::ENABLE_ON_TEST_MODE);

		$enabled = isset($account['httpResponse']['payment_methods']['ideal']['enabled']) ? $account['httpResponse']['payment_methods']['ideal']['enabled']: false;
		if(!$enabled){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __( 'payplug_ideal_access_error', 'payplug' ),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

		wp_send_json_success($enabled);
	}

	private function generic_get_account($request, $enable_on_test_mode){

		$data = $request->get_params();

		if( isset($data['env']) && $data['env'] ) {
			if($enable_on_test_mode){
				wp_send_json_success(true);
			}else{
				$this->optionUnnavailableInTestMode();
			}
		}

		$this->accountIsNotValid();

		$live_key = PayplugWoocommerceHelper::get_live_key();
		if(empty($live_key)){
			return false;
		}

		try{
			$account = Authentication::getAccount(new Payplug($live_key));

		}  catch (PayplugException $e){
			PayplugGateway::log('Error while saving account : ' . $e->getMessage(), 'error');
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => $e->getMessage(),
				"close" => __( 'payplug_ok', 'payplug' )
			));
			return false;
		}

		return $account;

	}

	/**
	 * Update PayPlug api keys
	 *&

	 * @param array $keys
	 * @param PayplugGateway $payplug_gateway
	 *
	 * @return bool
	 */
	protected function update_api_keys( $keys, $payplug_gateway ) {
		if ( empty( $payplug_gateway->settings ) ) {
			$payplug_gateway->init_settings();
		}

		$payplug_gateway->settings['payplug_test_key'] = $keys['test'];
		$payplug_gateway->settings['payplug_live_key'] = $keys['live'];
		if ( ! empty( $keys['live'] ) ) {
			$payplug_gateway->settings['mode'] = 'yes';
		}

		return update_option(
			$payplug_gateway->get_option_key(),
			apply_filters(
				'woocommerce_settings_api_sanitized_fields_' . $payplug_gateway->id,
				$payplug_gateway->settings
			),
			'yes'
		);
	}

	/**
	 *
	 * Ajax paypal login
	 *
	 * @return JSON
	 */
	public function payplug_login() {

		$data = json_decode(file_get_contents('php://input'), true);
		$email = sanitize_email($data['payplug_email']);
		$password = base64_decode(wp_unslash($data['payplug_password']));
		$wp_nonce = !empty($data['_wpnonce']) ? $data['_wpnonce'] : null;

		delete_option( 'woocommerce_payplug_settings' );
		delete_site_option( 'woocommerce_payplug_settings' );

		try {
			$response = Authentication::getKeysByLogin($email, $password);
			if (empty($response) || !isset($response)) {
				http_response_code(401);
				return wp_send_json_error(array(
					'message' => __( 'payplug_error_wrong_credentials.', 'payplug' ),
				));
			}
			$payplug = new PayplugGateway();
			$form_fields = $payplug->get_form_fields();

			$api_keys = $payplug->retrieve_user_api_keys($email, $password);

			$merchant_id = isset($api_keys['test']) ? $payplug->retrieve_merchant_id($api_keys['test']) : '';

			foreach ($form_fields as $key => $field) {
				if (in_array($field['type'], ['title', 'login'])) {
					continue;
				}

				switch ($key) {
					case 'enabled':
						$val = 'yes';
						break;
					case 'mode':
						$val = 'no';
						break;
					case 'payplug_test_key':
						$val = !empty($api_keys['test']) ? esc_attr($api_keys['test']) : null;
						break;
					case 'payplug_live_key':
						$val = !empty($api_keys['live']) ? esc_attr($api_keys['live']) : null;
						break;
					case 'payplug_merchant_id':
						$val = esc_attr($merchant_id);
						break;
					case 'email':
						$val = esc_html($email);
						break;
					default:
						$val = $payplug->get_option($key);
				}

				$data[$key] = $val;
			}

			$payplug->set_post_data($data);
			update_option(
				$payplug->get_option_key(),
				apply_filters('woocommerce_settings_api_sanitized_fields_' . $payplug->id, $data)
			);

			$user = [
				"logged" => true,
				"email" => $email,
				"mode" => PayplugWoocommerceHelper::check_mode() ? 0 : 1
			];
			$wp = [
				"WP" => [
					"_wpnonce" => $wp_nonce,
				]
			];

			return wp_send_json_success( ["settings" => $user + $wp] + ( new Vue )->init() );
		} catch (HttpException $e) {

			http_response_code(401);
			$error = __("payplug_error_wrong_credentials", "payplug");
			return wp_send_json_error(array('message' => $error));

		}
	}

	/**
	 *
	 * Ajax payplug initialisation
	 *
	 * @return JSON
	 */
	public function payplug_init() {

		$wp_nonce = wp_create_nonce();

		$wp = [
			"logged" => PayplugWoocommerceHelper::user_logged_in(),
			"mode" => PayplugWoocommerceHelper::check_mode() ? 0 : 1,
			"WP" =>  [
				"_wpnonce" => $wp_nonce,
			]
		];

		return wp_send_json_success([
			"settings" => $wp
		] + ( new Vue )->init() );

	}

	/**
	 * @return bool|null
	 */
	public function payplug_logout() {

		$payplug = new PayplugGateway();

		if (PayplugWoocommerceHelper::payplug_logout($payplug)) {
			$wp = [
				"logged" => PayplugWoocommerceHelper::user_logged_in(),
				"mode" => PayplugWoocommerceHelper::check_mode() ? 0 : 1
			];

			http_response_code(200);
			return wp_send_json_success(array(
				"message" => __('Successfully logged out.', 'payplug'),
				"status" => ( new Vue )->payplug_section_status(),
				"settings" => $wp,
				"subscribe" => ( new Vue )->payplug_section_subscribe() // When Logging out the Status Block needs to be updated
			));
		} else {
			http_response_code(400);
			return wp_send_json_error(__('Already logged out.', 'payplug'));
		}

	}


	private function accountIsNotValid(){
		$live_key = PayplugWoocommerceHelper::get_live_key();
		if(empty($live_key)){
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __('Your account does not support LIVE mode at the moment, it must be validated first. If your account has already been validated, please log out and log in again.', 'payplug'),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}
	}

	private function optionUnnavailableInTestMode(){
		wp_send_json_error(array(
			"title" => __( 'payplug_enable_feature', 'payplug' ),
			"msg" => __( 'payplug_unavailable_testmode_description', 'payplug' )
		));
	}

	/**
	 *
	 * Save data from request
	 *
	 * @return null
	 */
	public function payplug_save_data( WP_REST_Request $request ) {

		$payplug = new PayplugGateway();

		if ($payplug->user_logged_in()) {

			$data = json_decode(file_get_contents('php://input'), true);
			$options = get_option('woocommerce_payplug_settings', []);


			$options['enabled'] = Validator::enabled($data['payplug_enable']);
			$options['mode'] = Validator::mode($data['payplug_sandbox']);

			$test_mode = $options['mode'] === 'yes' ? false : true;

			$options['title'] = trim(wp_strip_all_tags($data['standard_payment_title']));
			$options['description'] = trim(wp_strip_all_tags($data['standard_payment_description']));
			$options['payment_method'] = (Validator::payment_method($data['payplug_embeded'])) ? $data['payplug_embeded'] : $options['payplug_embeded'];
			$options['oneclick'] = Validator::oneclick($data['enable_one_click']);

			$options['oney'] = Validator::oney($data['enable_oney']);
			$options['bancontact'] = Validator::genericPaymentGateway($data['enable_bancontact'], "Bancontact", $test_mode);

			$options['apple_pay'] = Validator::genericPaymentGateway($data['enable_applepay'], "Apple Pay", $test_mode);
			$options['applepay_carriers'] = (!empty($data['applepay_carriers'])) ? $data['applepay_carriers'] : [];
			$options['applepay_checkout'] = Validator::genericPaymentGateway($data['enable_applepay_checkout'], "Apple Pay Checkout", $test_mode);
			$options['applepay_cart'] = Validator::genericPaymentGateway($data['enable_applepay_cart'], "Apple Pay Cart", $test_mode);

			Validator::applePayPaymentGatewayOptions($options['apple_pay'], $options['applepay_cart'], $options['applepay_checkout'], $options['applepay_carriers']);
			if (($options['apple_pay'] === 'yes') && ($options['applepay_checkout'] === 'no') && ($options['applepay_cart'] === 'no')) {
				$options['applepay_checkout'] = 'yes';
			}


			$options['american_express'] = Validator::genericPaymentGateway($data['enable_american_express'],"American Express", $test_mode);
			$options['satispay'] = Validator::genericPaymentGateway($data['enable_satispay'], "Satispay", $test_mode);
			$options['ideal'] = Validator::genericPaymentGateway($data['enable_ideal'], "iDEAL", $test_mode);
			$options['mybank'] = Validator::genericPaymentGateway($data['enable_mybank'], "Mybank", $test_mode);
			$options['oney_type'] = (Validator::oney_type($data['payplug_oney'])) ? $data['payplug_oney'] : 'with_fees';
			$thresholds = (Validator::oney_thresholds($data['oney_min_amounts'], $data['oney_max_amounts']));
			$options['oney_thresholds_min'] = $thresholds['min'];
			$options['oney_thresholds_max'] = $thresholds['max'];
			$options['oney_product_animation'] = Validator::oney_product_animation($data['enable_oney_product_animation']);
			$options['debug'] = Validator::debug($data['enable_debug']);

			//force save
			if( update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options), false ) ){

				//delete the transient, so it refresh the permissions on the init
				$options = get_option('woocommerce_payplug_settings', []);
				$transient_key = PayplugWoocommerceHelper::get_transient_key($options);
				delete_transient($transient_key);

				http_response_code(200);
				wp_send_json_success( array(
					"title" => null,
					"msg" => __( 'payplug_save_success_message', 'payplug' ),
					"close" => __( 'payplug_ok', 'payplug' ),
				));
			}else{
				http_response_code(200);
				wp_send_json_error([
					"title" => null,
					"close" => __( 'payplug_ok', 'payplug' ),
					"msg" => "These settings are already saved !"]);
			}

		} else {
			http_response_code(403);
			wp_send_json_error("You are not logged in !");
		}


	}

	public function payplug_check_requirements() {
		wp_send_json_success(array(
			"status" => ( new Vue )->payplug_section_status()
		));
	}

	public function api_check_integrated_payment(WP_REST_Request $request)
	{

		$account = $this->generic_get_account($request, true);

		if( ! isset($account['httpResponse']['permissions']['can_use_integrated_payments'])
		    || ! $account['httpResponse']['permissions']['can_use_integrated_payments'] ) {
			wp_send_json_error(array(
				"title" => __( 'payplug_enable_feature', 'payplug' ),
				"msg" => __( 'payplug_integrated_access_error', 'payplug' ),
				"close" => __( 'payplug_ok', 'payplug' )
			));
		}

		wp_send_json_success(true);
		return true;

	}
}
