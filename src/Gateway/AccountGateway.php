<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use Payplug\PayplugWoocommerce\Traits\Configuration;

class AccountGateway
{
	use ServiceGetter;

	private $configuration;

	public function _construct()
	{
		$this->configuration = $this->get_service('configuration');
	}

	private function get_configuration()
	{
		$this->configuration = empty($this->configuration)
			? $this->get_service('configuration')
			: $this->configuration;
		return $this->configuration;
	}

	public function register($email = '', $password = '')
	{
		// Clean previous configuration to avoid any conflict with
		$this->get_configuration()->clean_option();

		// Initialize default option value
		$this->get_configuration()->initialize_option();

		// get admin keys
		$api = $this->get_service('api');
		$keys = $api->get_keys_by_login($email, $password);

		// if keys got, register keys in database
		$secret_keys = $keys['response']['secret_keys'];
		$options_to_update = [
			'version' => PAYPLUG_GATEWAY_VERSION,
			'enabled' => true,
			'mode' => isset($secret_keys['live']) && !empty($secret_keys['live']) ? 'live' : 'test',
			'email' => $email,
			'api_key' => json_encode([
				'live' => isset($secret_keys['live']) ? $secret_keys['live'] : '',
				'test' => isset($secret_keys['test']) ? $secret_keys['test'] : '',
			]),
		];
		$this->get_configuration()->update_options($options_to_update);

		// then get permissions
		$formated_permissions = $this->get_permissions();
		if(empty($formated_permissions)) {
			return [];
		}

		// then get test merchant id
		$company_id = $this->get_merchant_id();
		$formated_permissions['global']['company_id'] = $company_id;

		// and update options
		$this->get_configuration()->update_options($formated_permissions['global']);
		$this->get_configuration()->update_payment_permissions($formated_permissions['payment_methods']);

		return $this->get_configuration()->get_options();
	}

	public function is_logged()
	{
		$jwt = json_decode($this->get_configuration()->get_option('jwt'), true);

		if (!empty($jwt)) {
			$is_logged = isset($jwt['test']) && isset($jwt['test']['access_token']) && !empty($jwt['test']['access_token']);
		} else {
			$api_key = json_decode($this->get_configuration()->get_option('api_key'), true);
			$is_logged = isset($api_key['test']) && !empty($api_key['test']);
		}

		return $is_logged;
	}

	public function logout()
	{

	}

	public function get_permissions($mode = '')
	{
		$api = $this->get_service('api');
		$account_permissions = $api->get_account($mode);
		return $this->format_account_permissions($account_permissions['response']);
	}

	public function get_merchant_id()
	{
		$api = $this->get_service('api');
		$account_permissions = $api->get_account('test');
		return $account_permissions['response']['id'];
	}

	protected function format_account_permissions($account_permissions = [])
	{
		if(!is_array($account_permissions) || empty($account_permissions)) {
			return [];
		}

		$expected_fields = $this->get_configuration()->get_expected_fields();
		$options = $this->get_configuration()->get_options();

		$formated_account_permissions = [];
		$expected_payment_permissions = $expected_fields['payment_methods']['permissions'];

		// format global configuration
		foreach ($expected_fields as $key => $field) {
			switch (true) {
				case 'company_id' == $key:
					$value = (string) $account_permissions['id'];
					break;
				case 'company_iso' == $key:
					$value = (string) $account_permissions['country'];
					break;
				case 'currencies' == $key:
					$value = json_encode($account_permissions['configuration']['currencies']);
					break;
				default:
					$value = null;
					break;
			}
			if (null !== $value) {
				$formated_account_permissions['global'][$key] = $value;
			}
		}

		// format payment configuration
		foreach ($expected_payment_permissions as $payment_name => $value) {
			$payment_permissions = [];
			switch (true) {
				default:
					$account_payment = isset($account_permissions['payment_methods'][$payment_name])
					&& !empty($account_permissions['payment_methods'][$payment_name])
						? $account_permissions['payment_methods'][$payment_name]
						: [];
					if (!empty($account_payment)) {
						$account_payment = $account_permissions['payment_methods'][$payment_name];
						$countries = isset($account_payment['allowed_countries']) && !empty($account_payment['allowed_countries'])
							? $account_payment['allowed_countries']
							: ['ALL'];
						$min_amounts = isset($account_payment['min_amounts']) && !empty($account_payment['min_amounts'])
							? $account_payment['min_amounts']
							: $account_permissions['configuration']['min_amounts'];
						$max_amounts = isset($account_payment['max_amounts']) && !empty($account_payment['max_amounts'])
							? $account_payment['max_amounts']
							: $account_permissions['configuration']['max_amounts'];

						$payment_permissions['enabled'] = $account_payment['enabled'];
						$payment_permissions['countries'] = json_encode($countries);
						$payment_permissions['amounts'] = json_encode([
							'min' => $min_amounts,
							'max' => $max_amounts,
						]);

						if ('apple_pay' == $payment_name) {
							$payment_permissions['allowed_domains'] = json_encode($account_permissions['payment_methods'][$payment_name]['allowed_domain_names']);
						}
					}
					break;
				case 'payplug' == $payment_name:
					$payment_permissions = [
						'enabled' => true,
						'countries' => json_encode(['ALL']),
						'amounts' => json_encode([
							'min' => $account_permissions['configuration']['min_amounts'],
							'max' => $account_permissions['configuration']['max_amounts'],
						]),
					];
					break;
				case 'installment' == $payment_name:
					$payment_permissions = [
						'enabled' => $account_permissions['permissions']['can_create_installment_plan'],
						'countries' => json_encode(['ALL']),
						'amounts' => json_encode([
							'min' => $account_permissions['configuration']['min_amounts'],
							'max' => $account_permissions['configuration']['max_amounts'],
						]),
					];
					break;
				case 'deferred' == $payment_name:
					$payment_permissions = [
						'enabled' => $account_permissions['permissions']['can_create_deferred_payment'],
						'countries' => json_encode(['ALL']),
						'amounts' => json_encode([
							'min' => $account_permissions['configuration']['min_amounts'],
							'max' => $account_permissions['configuration']['max_amounts'],
						]),
					];
					break;
				case 'one_click' == $payment_name:
					$payment_permissions = [
						'enabled' => $account_permissions['permissions']['can_save_cards'],
						'countries' => json_encode(['ALL']),
						'amounts' => json_encode([
							'min' => $account_permissions['configuration']['min_amounts'],
							'max' => $account_permissions['configuration']['max_amounts'],
						]),
					];
					break;
			}
			$formated_account_permissions['payment_methods'][$payment_name] = $payment_permissions;
		}

		return $formated_account_permissions;
	}
}
