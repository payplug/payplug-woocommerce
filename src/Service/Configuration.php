<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Traits\Configuration;

class API
{
	use Configuration;

	protected $api;

	public function __construct() {
	}

	public function create_client_id_and_secret(){}
	public function generate_jwt_one_shot(){}
	public function generate_jwt(){}
	public function get_account(){
		if (!$this->api) {
			$this->initialize();
		die(var_dump(__LINE__));
		}
		die(var_dump(__LINE__));
		$account = $this->do_request_with_fallback( '\Payplug\Authentication::getAccount', [$this->api]);
		die(var_dump($account));
		return $account;
	}
	public function get_keys_by_login($email = '', $password = ''){
		$keys = $this->do_request_with_fallback( '\Payplug\Authentication::getKeysByLogin', [$email, $password]);
		return [
			'result' => $keys['result'],
			'response' => isset($keys['response']['httpResponse']) && !empty($keys['response']['httpResponse'])
				? $keys['response']['httpResponse']
				: null,
		];
	}
	public function get_permissions(){}
	public function get_register_url(){}
	public function initiate_oauth(){}
	public function validate_jWT(){}

	protected function initialize()
	{
		$bearer_token = $this->get_bearer_token();
		$this->api = new Payplug($bearer_token);
		die(var_dump(get_class($this->api)));
	}

	protected function get_mode()
	{
		$options = $this->get_options();
		return $options['mode'];
	}

	protected function get_bearer_token()
	{
		$options = $this->get_options();
		$mode = $this->get_mode();
		$key = $options['payplug_' . $mode . '_key'];

		$jwt = isset($options['client_data']) && isset($options['client_data']['jwt']) ? $options['client_data']['jwt'] : [];
		if(!empty($jwt) && !empty($jwt[$mode])) {
			// todo: Validate token usage
			$key = $jwt[$mode]['token'];
		}

		return $key;

//		$jwt = isset($options['client_data']) && isset($options['client_data']['jwt']) ? $options['client_data']['jwt'] : [];
//
//		if(!empty($jwt) && !empty($jwt[$mode])) {
//			$client_data = isset($options['client_data']) ? $options['client_data'] : [];
//			$this->api = new PayplugApi($this);
//			$validate_jwt = $this->api->validate_jwt(
//				array_key_exists($mode, $client_data) ? $client_data[$mode] : [],
//				$jwt[$mode]
//			);
//
//			if ($validate_jwt['token']) {
//				$key = $validate_jwt['token'];
//
//				if ($validate_jwt['need_update']) {
//					if (!isset($options['client_data'])) {
//						$options['client_data'] = [];
//					}
//					if (!isset($options['client_data']['jwt'])) {
//						$options['client_data']['jwt'] = [];
//					}
//					$options['client_data']['jwt'][$mode] = $validate_jwt['token'];
//					update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options), false );
//				}
//			}
//		}
//
//		return isset($key['access_token']) ? $key['access_token'] : $key;
	}
	protected function do_request_with_fallback( $callback, $params = [] ) {
		try {
			$response = [
				'result' => true,
				'response' => $this->do_request( $callback, $params ),
			];
		} catch ( \Exception $e ) {
			$response = [
				'result' => false,
				'response' => null,
				'code' => $e->getCode(),
			];
		}

		return $response;
	}
	protected function do_request( $callback, $params = [] ) {

		if ( ! is_array( $params ) ) {
			$params = [ $params ];
		}

		return call_user_func_array( $callback, $params );
	}
}
