<?php

namespace Payplug\PayplugWoocommerce\Service;

class Configuration
{
	private $current_configuration = null;
	private $expected_fields = [
		// merchant configuration
		'email' => [
			'type' => 'string',
			'default' => '',
		],
		'company_id' => [
			'type' => 'string',
			'default' => '{}',
		],
		'company_iso' => [
			'type' => 'string',
			'default' => '',
		],
		'country' => [
			'type' => 'string',
			'default' => '',
		],
		'currencies' => [
			'type' => 'string',
			'default' => '{}',
		],

		// authentication
		'jwt' => [
			'type' => 'string',
			'default' => '{}',
		],
		'api_key' => [
			'type' => 'string',
			'default' => '{}',
		],
		'oauth_client_data' => [
			'type' => 'string',
			'default' => '{}',
		],
		'oauth_client_id' => [
			'type' => 'string',
			'default' => '',
		],
		'oauth_code_verifier' => [
			'type' => 'string',
			'default' => '',
		],
		'oauth_company_id' => [
			'type' => 'string',
			'default' => '',
		],

		// payment_methods permissions
		'payment_methods' => [
			'permissions' => [
				//
				'payplug' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'installment' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'deferred' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'one_click' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],

				//
				'apple_pay' => [
					'allowed_domains' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'american_express' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'oney_x3_with_fees' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'oney_x4_with_fees' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'oney_x3_without_fees' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'oney_x4_without_fees' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'bancontact' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'giropay' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'ideal' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'mybank' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'satispay' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'sofort' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'wero' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'bizum' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
				'scalapay' => [
					'enabled' => [
						'type' => 'bool',
						'default' => false,
					],
					'countries' => [
						'type' => 'string',
						'default' => '{}', // Precise iso code if require e.g. "0 => 'FR'" or "0 => 'ALL'"
					],
					'amounts' => [
						'type' => 'string',
						'default' => '{}', // Contain currency e.g. "{"min":{"EUR": 30}, "max":{"EUR": 2000000}}"
					],
				],
			],
			'configuration' => [
				'payplug' => [
					'active' => [
						'type' => 'bool',
						'default' => true,
					],
					'title' => [
						'type' => 'string',
						'default' => '',
					],
					'description' => [
						'type' => 'string',
						'default' => '',
					],
					'save_card' => [
						'type' => 'bool',
						'default' => false,
					],
					'defered' => [
						'type' => 'bool',
						'default' => false,
					],
					'auto_capture' => [
						'type' => 'integer',
						'default' => 0,
					],
					'embedded_mode' => [
						'type' => 'string',
						'default' => 'redirect',
					],
				],
				'installments' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
					'min_amount' => [
						'type' => 'integer',
						'default' => 150,
					],
					'mode' => [
						'type' => 'integer',
						'default' => 3,
					],
				],
				'applepay' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
					'carriers' => [
						'type' => 'string',
						'default' => '{}',
					],
					'display' => [
						'type' => 'string',
						'default' => '{"cart":true,"checkout":true,"product":false}',
					],
				],
				'oney' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
					'cta_cart' => [
						'type' => 'bool',
						'default' => false,
					],
					'cta_product' => [
						'type' => 'bool',
						'default' => false,
					],
					'default_amount' => [
						'type' => 'string',
						'default' => '{"min":10000, "max":300000}',
					],
					'custom_amounts' => [
						'type' => 'string',
						'default' => '{"min":10000, "max":300000}',
					],
					'with_fees' => [
						'type' => 'bool',
						'default' => false,
					],
					'optimized' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'american_express' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'bancontact' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'giropay' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'ideal' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'mybank' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'satispay' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'sofort' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'wero' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'bizum' => [
					'active' => [
						'type' => 'bool',
						'default' => false,
					],
				],
				'scalapay' => [
					'active' => [
						'type' => 'bool',
						'default' => true,
					],
				],
			],
		],

		// other configuration
		'telemetry_hash' => [
			'type' => 'string',
			'default' => '',
		],
		'enabled' => [
			'type' => 'bool',
			'default' => true,
		],
		'debug' => [
			'type' => 'bool',
			'default' => false,
		],
		'mode' => [
			'type' => 'bool',
			'default' => false,
		],
	];

	public function __construct()
	{
		$this->current_configuration = get_option('woocommerce_payplug_settings', []);
	}

	public function initialize_option()
	{
		$options = $this->extract_options_from_fields();
		return $this->update_options($options);
	}

	public function extract_options_from_fields($fields = null) {
		if ($fields === null) {
			$fields = $this->get_expected_fields();
		}
		$options = [];
		foreach ($fields as $key => $value) {
			if (is_array($value) && array_key_exists('default', $value)) {
				$options[$key] = $value['default'];
			} elseif (is_array($value)) {
				$options[$key] = $this->extract_options_from_fields($value);
			}
		}
		return $options;
	}

	public function clean_option() {
		$this->current_configuration = null;
		delete_option('woocommerce_payplug_settings');
		delete_site_option('woocommerce_payplug_settings');
	}

	public function get_options()
	{
		return $this->current_configuration;
	}

	// todo: add default return value
	public function get_option($option_name = '', $options = null)
	{
		if (!is_string($option_name) || empty($option_name)) {
			return null;
		}

		$options = $options ? $options : $this->get_options();

		// Convert name to array
		$option_name = explode('.', $option_name);

		// Get first iteration
		$name = reset($option_name);
		if(!array_key_exists($name, $options)) {
			return null;
		}

		// if no more sub option, return current one
		unset($option_name[0]);
		if(empty($option_name)) {
			return $options[$name];
		}

		// else return the sub option
		$option_name = implode('.', $option_name);
		return $this->get_option($option_name, $options[$name]);
	}

	// todo: check return value type
	public function update_options($options_to_update = [])
	{
		$options = $this->get_options();
		foreach ($options_to_update as $key => $value) {
			$options[$key] = $value;
		}
		$this->current_configuration = $options;
		return update_option('woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options));
	}

	public function update_payment_permissions($payment_permissions = [])
	{
		$options = $this->get_options();
		$options['payment_methods']['permissions'] = $payment_permissions;
		$this->current_configuration = $options;
		return update_option('woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options));
	}

	public function get_expected_fields() {
		return $this->expected_fields;
	}
}
