<?php

namespace Payplug\PayplugWoocommerce\Service;

class Configuration
{
	private $current_configuration = [];
	private $expected_fields = [
		// merchant configuration
		'email' => [
			'type' => 'string',
			'default' => '',
		],
		'company_id' => [
			'type' => 'string',
			'default' => '',
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
		'oauth_callback_uri' => [
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
				'apple_pay' => [
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
					'default_amounts' => [
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
						'default' => false,
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
		'version' => [
			'type' => 'string',
			'default' => '',
		],
	];
	private $old_keys = [
		'american_express' => 'payment_methods.configuration.american_express.active',
		'apple_pay' => 'payment_methods.configuration.apple_pay.active',
		'applepay_carriers' => 'payment_methods.configuration.apple_pay.carriers',
		'applepay_cart' => 'payment_methods.configuration.apple_pay.display.cart',
		'applepay_checkout' => 'payment_methods.configuration.apple_pay.display.checkout',
		'applepay_product' => 'payment_methods.configuration.apple_pay.display.product',
		'bancontact' => 'payment_methods.configuration.bancontact.active',
		'debug' => 'debug',
		'description' => 'payment_methods.configuration.payplug.description',
		'email' => 'email',
		'enabled' => 'enabled',
		'ideal' => 'payment_methods.configuration.ideal.active',
		'mode' => 'mode',
		'mybank' => 'payment_methods.configuration.mybank.active',
		'oneclick' => 'payment_methods.configuration.payplug.save_card',
		'oney' => 'payment_methods.configuration.oney.active',
		'oney_product_animation' => 'payment_methods.configuration.oney.cta_product',
		'oney_thresholds_max' => 'payment_methods.configuration.oney.custom_amounts.max',
		'oney_thresholds_min' => 'payment_methods.configuration.oney.custom_amounts.min',
		'oney_type' => 'payment_methods.configuration.oney.with_fees',
		'payment_method' => 'payment_methods.configuration.payplug.embedded_mode',
		'payplug' => 'payment_methods.configuration.payplug.active',
		'payplug_live_key' => 'api_key.live',
		'payplug_merchant_id' => 'company_id',
		'payplug_test_key' => 'api_key.test',
		'satispay' => 'payment_methods.configuration.satispay.active',
		'title' => 'payment_methods.configuration.payplug.title',
	];

	public function __construct()
	{
		$this->current_configuration = get_option('woocommerce_payplug_settings', []);
	}

	/**
	 * @description initialize option from expected fields
	 * @return array
	 */
	public function initialize_option()
	{
		$options = $this->extract_options_from_fields();
		return $this->update_options($options);
	}

	/**
	 * @description get default configuration value from expected fields (recursively)
	 * @param $fields
	 * @return array
	 */
	public function extract_options_from_fields($fields = null)
	{
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

	/**
	 * @description Delete all configurations related to the plugin
	 * @return void
	 */
	public function clean_option()
	{
		$this->current_configuration = null;
		delete_option('woocommerce_payplug_settings');
		delete_site_option('woocommerce_payplug_settings');
	}

	/**
	 * @description Get configurations
	 * @return mixed
	 */
	public function get_options()
	{
		return $this->current_configuration;
	}

	/**
	 * @description Return configuration value for a given key
	 * todo: add default return value
	 * @param $option_name
	 * @param $options
	 * @return mixed|null
	 */
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
		if (!array_key_exists($name, $options)) {
			return null;
		}

		// if no more sub option, return current one
		unset($option_name[0]);
		if (empty($option_name)) {
			return $options[$name];
		}

		// else return the sub option
		$option_name = implode('.', $option_name);
		return $this->get_option($option_name, $options[$name]);
	}

	/**
	 * @description Update confurations values
	 * todo: check return value type
	 * @param $options_to_update
	 * @return mixed
	 */
	public function update_options($options_to_update = [])
	{
		$options = $this->get_options();
		foreach ($options_to_update as $key => $value) {
			$options[$key] = $value;
		}
		$this->current_configuration = $options;
		return update_option('woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options));
	}

	/**
	 * @description Update confurations for a given key
	 * @param $key
	 * @param $value
	 * @return array
	 */
	public function update_option($key = '', $value = null) {
		if (!is_string($key) || empty($key)) {
			return [];
		}

		if (!array_key_exists($key, $this->expected_fields)) {
			return [];
		}

		$options = $this->get_options();
		$options[$key] = $value;
		return $this->update_options($options);
	}

	/**
	 * @description Update payment configuration permission
	 * @param $payment_permissions
	 * @return mixed
	 */
	public function update_payment_permissions($payment_permissions = [])
	{
		$options = $this->get_options();
		$options['payment_methods']['permissions'] = $payment_permissions;
		$this->current_configuration = $options;
		return update_option('woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options));
	}

	/**
	 * @description Get the expected fields
	 * @return array
	 */
	public function get_expected_fields()
	{
		return $this->expected_fields;
	}

	/**
	 * @description Validate if the current configuration is valide (for example: when plugin is upgraded)
	 * @return array
	 */
	public function validate_configuration()
	{
		$options = $this->extract_options_from_fields();
		foreach ($this->old_keys as $key => $value) {
			if (!isset($this->current_configuration[$key])) {
				continue;
			}
			switch ($key) {
				case 'applepay_carriers':
					$carriers = $this->current_configuration['applepay_carriers'];
					if (!empty($carriers)) {
						$options['payment_methods']['configuration']['apple_pay']['carriers'] = json_encode($carriers);
					}
					break;

				case 'applepay_cart':
				case 'applepay_checkout':
				case 'applepay_product':
					$display = str_replace('applepay_', '', $key);
					$applepay_display = json_decode($options['payment_methods']['configuration']['apple_pay']['display'], true);
					$applepay_display[$display] = 'yes' == $this->current_configuration[$key];
					$options['payment_methods']['configuration']['apple_pay']['display'] = json_encode($applepay_display);
					break;
				case 'oney_product_animation':
					$options['payment_methods']['configuration']['oney']['cta_product'] = 'yes' == $this->current_configuration['oney_product_animation'];
					break;
				case 'oney_thresholds_min':
				case 'oney_thresholds_max':
					$thresholds = str_replace('oney_thresholds_', '', $key);
					$custom_amounts = json_decode($options['payment_methods']['configuration']['oney']['custom_amounts'], true);
					$custom_amounts[$thresholds] = $this->current_configuration[$key];
					$options['payment_methods']['configuration']['oney']['custom_amounts'] = json_encode($custom_amounts);
					break;
				case 'oney_type':
					$options['payment_methods']['configuration']['oney']['with_fees'] = $this->current_configuration['oney_type'];
					break;
				case 'description':
					$options['payment_methods']['configuration']['payplug']['description'] = $this->current_configuration['description'];
					break;
				case 'payment_method':
					$options['payment_methods']['configuration']['payplug']['embedded_mode'] = $this->current_configuration['payment_method'];
					break;
				case 'oneclick':
					$options['payment_methods']['configuration']['payplug']['save_card'] = 'yes' == $this->current_configuration['oneclick'];
					break;
				case 'title':
					$options['payment_methods']['configuration']['payplug']['title'] = $this->current_configuration['title'];
					break;

				case 'payplug_test_key':
				case 'payplug_live_key':
					$mode = str_replace('payplug_', '', $key);
					$mode = str_replace('_key', '', $mode);
					$api = json_decode($options['api_key'], true);
					$api[$mode] = $this->current_configuration['payplug_' . $mode . '_key'];
					$options['api_key'] = json_encode($api);
					break;

				case 'payplug_merchant_id':
					$options['company_id'] = $this->current_configuration['payplug_merchant_id'];
					break;

				case 'american_express':
				case 'apple_pay':
				case 'bancontact':
				case 'ideal':
				case 'mybank':
				case 'oney':
				case 'payplug':
				case 'satispay':
					$options['payment_methods']['configuration'][$key]['active'] = 'yes' == $this->current_configuration[$key];
					break;

				default:
					$value = 'no' == $this->current_configuration[$key] ? false : $this->current_configuration[$key];
					$options[$key] = 'yes' == $value ? true : $value;
					break;
			}
		}
		return $options;
	}
}
