<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use Payplug\PayplugWoocommerce\Traits\Configuration;

class AccountGateway
{
	use ServiceGetter;
	use Configuration;

	protected $service_api;

	public function __construct() {
		$this->service_api = $this->get_api_service();
	}

	public function register($email = '', $password = '')
	{
		// get admin keys
		$keys = $this->service_api->get_keys_by_login($email, $password);

		// if keys got, register keys in database
		$secret_keys = $keys['response']['secret_keys'];
		$options_to_update = [
			'enabled' => 'yes',
			'mode' => isset($secret_keys['live']) && !empty($secret_keys['live']) ? 'live' : 'test',
			'payplug_test_key' => isset($secret_keys['test']) ? $secret_keys['test'] : '',
			'payplug_live_key' => isset($secret_keys['live']) ? $secret_keys['live'] : '',
			'email' => $email,
		];
		$this->update_option($options_to_update);

		// then get permissions
		$account_permissions = $this->service_api->get_account();


		die(var_dump(__LINE__));
		return $registration;
	}

	public function logout()
	{

	}

	public function get_permissions()
	{

	}
}
