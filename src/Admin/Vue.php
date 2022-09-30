<?php

namespace Payplug\PayplugWoocommerce\Admin;


/**
 * PayPlug admin Vue.js dashboard handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */

class Vue {

	public function init() {

		if (!empty(get_option('woocommerce_payplug_settings', [])['payplug_test_key'])) {
			$header = $this->payplug_section_header();
			$logged = $this->payplug_section_logged();

			return [
				"header" => $header,
				"logged" => $logged
			];
		}

		return [
			"header" => $this->payplug_section_header(),
			"login" => $this->payplug_section_login(),
			"subscribe" => $this->payplug_section_subscribe()
		];
	}

	public function payplug_section_logged() {
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

	public function payplug_section_login() {

		$login = [
			"name" => "generalLogin",
			"title" => __('payplug_section_logged_title', 'payplug'),
			"descriptions" => [
				"live" => [
					"description" => __('payplug_section_login_description', 'payplug'),
					"not_registered" => __('payplug_section_login_not_registered', 'payplug'),
					"connect" => __('payplug_section_login_connect', 'payplug'),
					"email_label" => __('payplug_section_login_email_label', 'payplug'),
					"email_placeholder" => __('payplug_section_login_email_label', 'payplug'),
					"password_label" => __('payplug_section_login_password_label', 'payplug'),
					"password_placeholder" => __('payplug_section_login_password_label', 'payplug'),
					"link_forgot_password" =>  [
						"text" => __('payplug_section_login_forgot_password', 'payplug'),
						"url" => "https://www.payplug.com/portal/forgot_password",
						"target" => "_blank"
					],
				],
				"sandbox" => [
					"description" => __('payplug_section_login_description', 'payplug'),
					"not_registered" => __('payplug_section_login_not_registered', 'payplug'),
					"connect" => __('payplug_section_login_connect', 'payplug'),
					"email_label" => __('payplug_section_login_email_label', 'payplug'),
					"email_placeholder" => __('payplug_section_login_email_label', 'payplug'),
					"password_label" => __('payplug_section_login_password_label', 'payplug'),
					"password_placeholder" => __('payplug_section_login_password_label', 'payplug'),
					"link_forgot_password" =>  [
						"text" => __('payplug_section_login_forgot_password', 'payplug'),
						"url" => "https://www.payplug.com/portal/forgot_password",
						"target" => "_blank"
					],
				]
			]
		];

		return [
			"login" => $login
		];
	}

	public function payplug_section_subscribe() {
		return [
			"name" => "generalSubscribe",
			"title" => __('payplug_section_logged_title', 'payplug'),
			"descriptions" => [
				"live" => [
					"description" => __('payplug_section_subscribe_description', 'payplug'),
					"link_create_account" => [
						"text" => __('payplug_section_subscribe_link_create_account', 'payplug'),
						"url" => "https://portal.payplug.com",
						"target" => "_blank"
					],
					"content_description" => __('payplug_section_subscribe_content_description', 'payplug'),
					"already_have_account" => __('payplug_section_subscribe_already_have_account', 'payplug'),
				],
				"sandbox" => [
					"description" => __('payplug_section_subscribe_description', 'payplug'),
					"link_create_account" => [
						"text" => __('payplug_section_subscribe_link_create_account', 'payplug'),
						"url" => "https://portal.payplug.com",
						"target" => "_blank"
					],
					"content_description" => __('payplug_section_subscribe_content_description', 'payplug'),
					"already_have_account" => __('payplug_section_subscribe_already_have_account', 'payplug'),
				]
			]
		];
	}

	public function payplug_section_header() {

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
