<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

class Api
{
	use ServiceGetter;

	protected $api;

	public function __construct() {
	}

	/**
	 * @description get merchant account permission
	 * @return array
	 */
	public function get_account($mode = ''){
		if (!$this->api) {
			$this->initialize($mode);
		}
		$account = $this->do_request_with_fallback( '\Payplug\Authentication::getAccount', [$this->api]);
		return [
			'result' => $account['result'],
			'response' => isset($account['response']['httpResponse']) && !empty($account['response']['httpResponse'])
				? $account['response']['httpResponse']
				: null,
		];
	}

	/**
	 * @description get the api key for a given email and password
	 * @param $email
	 * @param $password
	 * @return array
	 */
	public function get_keys_by_login($email = '', $password = ''){
		$keys = $this->do_request_with_fallback( '\Payplug\Authentication::getKeysByLogin', [$email, $password]);
		return [
			'result' => $keys['result'],
			'response' => isset($keys['response']['httpResponse']) && !empty($keys['response']['httpResponse'])
				? $keys['response']['httpResponse']
				: null,
		];
	}

	/**
	 * @description Initialize the api with current bearer token
	 * @param $mode
	 * @return void
	 * @throws \Payplug\Exception\ConfigurationException
	 */
	protected function initialize($mode = '')
	{
		$bearer_token = $this->get_bearer_token($mode);
		$this->api = new Payplug($bearer_token);
	}

	/**
	 * @description get current mode configured (live|test)
	 * @return mixed
	 */
	protected function get_mode()
	{
		$configuration = $this->get_service('configuration');
		$options = $configuration->get_options();
		return $options['mode'];
	}

	/**
	 * @description get current bearer token for api
	 * @param $mode
	 * @return string
	 */
	protected function get_bearer_token($mode = '')
	{
		$configuration = $this->get_service('configuration');
		$options = $configuration->get_options();

		$api_keys = json_decode($options['api_key'], true);
		$mode = $mode
			? $mode
			: ($options['mode'] ? 'live' : 'test');
		$bearer_token = isset($api_keys[$mode]) ? $api_keys[$mode] : '';

		$jwt = isset($options['jwt']) ? json_decode($options['jwt'], true) : [];
		$oauth_client_data = isset($options['oauth_client_data']) ? json_decode($options['oauth_client_data'], true) : [];

		if(!empty($jwt) && !empty($jwt[$mode]) && !empty($oauth_client_data) && !empty($oauth_client_data[$mode])) {
			$validate_jwt = $this->validate_jwt($oauth_client_data[$mode], $jwt['$mode']);

			if (!$validate_jwt['result'] || empty($validate_jwt['token'])) {
				return '';
			}
			$token_validated = $validate_jwt['token'];

			$token_validated['expires_date'] -= 30;
			$jwt[$mode] = $token_validated;

			if ($validate_jwt['need_update']) {
				$configuration->update_option('jwt', json_encode($jwt));
			}

			$bearer_token = $jwt[$mode]['access_token'];
		}

		return (string) $bearer_token;
	}

	/**
	 * @description validate usage for a given jwt
	 * @param $oauth_client_data
	 * @param $jwt
	 * @return array
	 */
	protected function validate_jwt($oauth_client_data = [], $jwt = []) {
		return $this->do_request_with_fallback( '\Payplug\Authentication::validateJWT', [$oauth_client_data, $jwt]);
	}

	/**
	 * @description Send request
	 * @param $callback
	 * @param $params
	 * @return array
	 */
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

	/**
	 * @description Send request to the api without fallback
	 * @param $callback
	 * @param $params
	 * @return mixed
	 */
	protected function do_request( $callback, $params = [] ) {

		if ( ! is_array( $params ) ) {
			$params = [ $params ];
		}

		return call_user_func_array( $callback, $params );
	}

 	// todo: this method bellow should be implemented, do no removed it for now
	public function create_client_id_and_secret(){}
	public function generate_jwt_one_shot(){}
	public function generate_jwt(){}
	public function get_permissions(){}
	public function get_register_url(){}
	public function initiate_oauth(){}
}
