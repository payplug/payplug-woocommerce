<?php

namespace Payplug\PayplugWoocommerce\Admin;


/**
 * PayPlug admin Vue.js dashboard handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */

class Vue {

	public function init(): array {

		if (!empty(get_option('woocommerce_payplug_settings', [])['payplug_test_key'])) {
			$header = $this->payplug_section_header();
			$logged = $this->payplug_section_logged();

			return [
				"header" => $header,
				"logged" => $logged
			];
		}

		return $this->payplug_section_login();
	}

	public function payplug_section_logged(): array {
		return [
			"title" => __('payplug_section_logged_title', 'payplug'),
			"descriptions" => [
				"live" => [
					"description"=> __('payplug_section_logged_description', 'payplug'),
					"logout"=> __('payplug_section_logged_logout', 'payplug'),
					"mode"=> __('payplug_section_logged_mode', 'payplug'),
					"mode_description"=>  __('payplug_section_logged_live_description', 'payplug'),
					"link_learn_more" => [
						"text" => "Learn more",
						"url" => "https://support.payplug.com/hc/en-gb/articles/360021142492",
						"target" => "_blank"
					],
					"link_access_portal" => [
						"text" => __('payplug_section_logged_link_access_portal', 'payplug'),
						"url" => "https://www.payplug.com/portal",
						"target" => "_blank"
					],
				],
				"sandbox" => [
					"description"=> __('payplug_section_logged_description', 'payplug'),
					"logout"=> __('payplug_section_logged_logout', 'payplug'),
					"mode"=> __('payplug_section_logged_mode', 'payplug'),
					"mode_description"=> __('payplug_section_logged_test_description', 'payplug'),
					"link_learn_more" => [
						"text" => "Learn more",
						"url" => "https://support.payplug.com/hc/en-gb/articles/360021142492",
						"target" => "_blank"
					],
					"link_access_portal" => [
						"text" => __('payplug_section_logged_link_access_portal', 'payplug'),
						"url" => "https://www.payplug.com/portal",
						"target" => "_blank"
					],
				]
			],
			"options" => [
				[
					"name"    => "payplug_sandbox",
					"label"   => "Live",
					"value"   => "0",
					"checked" => true
				],
				[
					"name"    => "payplug_sandbox",
					"label"   => "Test",
					"value"   => "1"
				],
			]
		];
	}

	public function payplug_section_login(): array {
		$header = $this->payplug_section_header();

		return [
			"header" => $header,
		];
	}

	public function payplug_section_header(): array {

		return [
			"title" => __('payplug_section_header_title', 'payplug'),
			"descriptions" => [
				"live" => [
					"description" => __('payplug_section_header_live_description', 'payplug'),
					"plugin_version" => PAYPLUG_GATEWAY_VERSION
				],
				"sandbox" => [
					"description" => __('payplug_section_header_test_description', 'payplug'),
					"plugin_version" => PAYPLUG_GATEWAY_VERSION
				],
			],
			"options" => [
				"type" => "select",
				"name" => "payplug_enable",
				"options" => [
					[
						"value" => 1,
						"label" => __('payplug_section_header_enable_label', 'payplug'),
						"checked" => true
					],
					[
						"value" => 0,
						"label" => __('payplug_section_header_disable_label', 'payplug'),
					]
				]
			]
		];

	}
}
