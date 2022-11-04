<?php

namespace Payplug\PayplugWoocommerce\Admin;


use Payplug\PayplugWoocommerce\Admin\Vue\Component;
use Payplug\PayplugWoocommerce\Admin\Vue\PaymentMethods;
use Payplug\PayplugWoocommerce\Controller\ApplePay;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayRequirements;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

/**
 * PayPlug admin Vue.js dashboard handler.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class Vue {

	/**
	 * @return array
	 */
	public function init() {

		if ( PayplugWoocommerceHelper::user_logged_in() ) {
			$header = $this->payplug_section_header();
			$logged = $this->payplug_section_logged();

			return [
				"db_save_options" => get_option( 'woocommerce_payplug_settings', [] ),
				"header"           => $header,
				"logged"           => $logged,
				"payment_methods"  => $this->payplug_section_payment_methods(),
				"payment_paylater"  => $this->payplug_section_paylater(),
			];
		}

		return [
			"header"    => $this->payplug_section_header(),
			"login"     => $this->payplug_section_login(),
			"subscribe" => $this->payplug_section_subscribe(),
			"payment_methods"  => $this->payplug_section_payment_methods(),
			"payment_paylater"  => $this->payplug_section_paylater(),
			"status" => $this->payplug_section_status()
		];
	}

	/**
	 * @return array
	 */
	public function payplug_section_logged() {

		return [
			"title"        => __( 'payplug_section_logged_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description"        => __( 'payplug_section_logged_description', 'payplug' ),
					"logout"             => __( 'payplug_section_logged_logout', 'payplug' ),
					"mode"               => __( 'payplug_section_logged_mode', 'payplug' ),
					"mode_description"   => __( 'payplug_section_logged_live_description', 'payplug' ),
					"link_learn_more"    => [
						"text"   => "Learn more",
						"url"    => "https://support.payplug.com/hc/en-gb/articles/360021142492",
						"target" => "_blank"
					],
					"link_access_portal" => [
						"text"   => __( 'payplug_section_logged_link_access_portal', 'payplug' ),
						"url"    => "https://www.payplug.com/portal",
						"target" => "_blank"
					],
				],
				"sandbox" => [
					"description"        => __( 'payplug_section_logged_description', 'payplug' ),
					"logout"             => __( 'payplug_section_logged_logout', 'payplug' ),
					"mode"               => __( 'payplug_section_logged_mode', 'payplug' ),
					"mode_description"   => __( 'payplug_section_logged_test_description', 'payplug' ),
					"link_learn_more"    => [
						"text"   => "Learn more",
						"url"    => "https://support.payplug.com/hc/en-gb/articles/360021142492",
						"target" => "_blank"
					],
					"link_access_portal" => [
						"text"   => __( 'payplug_section_logged_link_access_portal', 'payplug' ),
						"url"    => "https://www.payplug.com/portal",
						"target" => "_blank"
					],
				]
			],
			"options"      => [
				[
					"name"    => "payplug_sandbox",
					"label"   => "Live",
					"value"   => "0",
					"checked" => true
				],
				[
					"name"  => "payplug_sandbox",
					"label" => "Test",
					"value" => "1"
				],
			]
		];
	}

	/**
	 * @return array[]
	 */
	public function payplug_section_login() {

		$login = [
			"name"         => "generalLogin",
			"title"        => __( 'payplug_section_logged_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description"          => __( 'payplug_section_login_description', 'payplug' ),
					"not_registered"       => __( 'payplug_section_login_not_registered', 'payplug' ),
					"connect"              => __( 'payplug_section_login_connect', 'payplug' ),
					"email_label"          => __( 'payplug_section_login_email_label', 'payplug' ),
					"email_placeholder"    => __( 'payplug_section_login_email_label', 'payplug' ),
					"password_label"       => __( 'payplug_section_login_password_label', 'payplug' ),
					"password_placeholder" => __( 'payplug_section_login_password_label', 'payplug' ),
					"link_forgot_password" => [
						"text"   => __( 'payplug_section_login_forgot_password', 'payplug' ),
						"url"    => "https://www.payplug.com/portal/forgot_password",
						"target" => "_blank"
					],
				],
				"sandbox" => [
					"description"          => __( 'payplug_section_login_description', 'payplug' ),
					"not_registered"       => __( 'payplug_section_login_not_registered', 'payplug' ),
					"connect"              => __( 'payplug_section_login_connect', 'payplug' ),
					"email_label"          => __( 'payplug_section_login_email_label', 'payplug' ),
					"email_placeholder"    => __( 'payplug_section_login_email_label', 'payplug' ),
					"password_label"       => __( 'payplug_section_login_password_label', 'payplug' ),
					"password_placeholder" => __( 'payplug_section_login_password_label', 'payplug' ),
					"link_forgot_password" => [
						"text"   => __( 'payplug_section_login_forgot_password', 'payplug' ),
						"url"    => "https://www.payplug.com/portal/forgot_password",
						"target" => "_blank"
					],
				]
			]
		];

		return $login;
	}

	/**
	 * @return array
	 */
	public function payplug_section_subscribe() {
		return [
			"name"         => "generalSubscribe",
			"title"        => __( 'payplug_section_logged_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description"          => __( 'payplug_section_subscribe_description', 'payplug' ),
					"link_create_account"  => [
						"text"   => __( 'payplug_section_subscribe_link_create_account', 'payplug' ),
						"url"    => "https://portal.payplug.com",
						"target" => "_blank"
					],
					"content_description"  => __( 'payplug_section_subscribe_content_description', 'payplug' ),
					"already_have_account" => __( 'payplug_section_subscribe_already_have_account', 'payplug' ),
				],
				"sandbox" => [
					"description"          => __( 'payplug_section_subscribe_description', 'payplug' ),
					"link_create_account"  => [
						"text"   => __( 'payplug_section_subscribe_link_create_account', 'payplug' ),
						"url"    => "https://portal.payplug.com",
						"target" => "_blank"
					],
					"content_description"  => __( 'payplug_section_subscribe_content_description', 'payplug' ),
					"already_have_account" => __( 'payplug_section_subscribe_already_have_account', 'payplug' ),
				]
			]
		];
	}

	/**
	 * @return array
	 */
	public function payplug_section_header() {

		return [
			"title"        => __( 'payplug_section_header_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description"    => __( 'payplug_section_header_live_description', 'payplug' ),
					"plugin_version" => PAYPLUG_GATEWAY_VERSION
				],
				"sandbox" => [
					"description"    => __( 'payplug_section_header_test_description', 'payplug' ),
					"plugin_version" => PAYPLUG_GATEWAY_VERSION
				],
			],
			"options"      => [
				"type"    => "select",
				"name"    => "payplug_enable",
				"options" => [
					[
						"value"   => 1,
						"label"   => __( 'payplug_section_header_enable_label', 'payplug' ),
						"checked" => true
					],
					[
						"value" => 0,
						"label" => __( 'payplug_section_header_disable_label', 'payplug' ),
					]
				]
			]
		];

	}

	/**
	 * @return array
	 */
	public function payplug_section_payment_methods() {
		$section = [
			"name"         => "paymentMethodsBlock",
			"title"        => __( 'payplug_section_payment_methods_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description" => __( 'payplug_section_payment_methods_description', 'payplug' ),
				],
				"sandbox" => [
					"description" => __( 'payplug_section_payment_methods_description', 'payplug' ),
				]
			],
			"options"      => [
				(new PaymentMethods())->payment_method_standard(),
				PaymentMethods::payment_method_applepay(),
				PaymentMethods::payment_method_bancontact(),
				PaymentMethods::payment_method_amex()
			]
		];

		return $section;
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function payplug_section_paylater($active = false) {
		$section = [
			"name"         => "paymentMethodsBlock",
			"title"        => __( 'payplug_section_paylater_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description" => __( 'payplug_section_paylater_description', 'payplug' ),
				],
				"sandbox" => [
					"description" => __( 'payplug_section_paylater_description', 'payplug' ),
				]
			],
			"options" => [
				"name" => "oney",
				"title" => __( 'payplug_section_oney_title', 'payplug' ),
				"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/lg-oney.png' ),
				"checked" => $active,
				"descriptions" => [
					"live"    => [
						"description"      => __( 'payplug_section_bancontact_payment_description', 'payplug' ),
						"link_know_more" => Component::link(__( 'payplug_section_bancontact_payment_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/360013071080", "_blank"),
					],
					"sandbox" => [
						"description"      => __( 'payplug_section_applepay_payment_description', 'payplug' ),
						"link_know_more" => Component::link(__( 'payplug_section_bancontact_payment_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/360013071080", "_blank"),
					]
				],
				"options" => [
					[
						"name" => "payplug_embedded",
						"className" => "_paylaterLabel",
						"label" => __( 'payplug_label_with_fees', 'payplug' ),
						"subText" => __( 'payplug_text_with_fees', 'payplug' ),
						"value" => 1
					],
					[
						"name" => "payplug_embedded",
						"className" => "_paylaterLabel",
						"label" => __( 'payplug_label_without_fees', 'payplug' ),
						"subText" => __( 'payplug_text_without_fees', 'payplug' ),
						"value" => 0
					]
				],
				"advanced_options" => [
					$this->thresholds_option(),
					$this->show_oney_popup_product()
				]
			]
		];

		return $section;
	}

	/**
	 * @return array
	 */
	public function thresholds_option() {
		$min_amount = (! empty( get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_min'] )) ? get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_min'] : 100;
		$max_amount = (! empty( get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_max'] )) ? get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_min'] : 100;
		$thresholds = [
			"name" => "thresholds",
			"image_url" => "",
			"title" => __( 'payplug_thresholds_oney_title', 'payplug' ),
			"descriptions" => [
				"description" => __( 'payplug_thresholds_oney_description', 'payplug' ),
				"min_amount" => [
					"name" => "oney_min_amounts",
					"value" => $min_amount,
					"placeholder" => $min_amount,
					"min" => "100"
				],
				"inter" => "and",
				[
					"name" => "oney_max_amounts",
					"value" => $max_amount,
					"placeholder" => $max_amount,
					"min" => "3000"
				],
				"error" => [
					"text" => __( 'payplug_thresholds_error_msg', 'payplug' )
				]
			],
			"switch" => false
		];

		return $thresholds;
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function show_oney_popup_product($active = false) {
		return [
			"name" => "product",
			"image_url" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/admin/screen/product.jpg' ),
			"title" => __( 'display_the_oney_installments_pop_up_on_the_product_page', 'payplug' ),
			"switch" => true,
			"checked" => $active
		];
	}


	/**
	 * @return array
	 */
	public function payplug_section_status() {
		$payplug_requirements = new PayplugGatewayRequirements(new PayplugGateway());

		$status = [
			"title" => __("payplug_section_status_title", "payplug"),
			"descriptions" => [
				"live" => [
					"description" => __("payplug_section_status_description", "payplug"),
					"errorMessage" => __("payplug_section_status_errorMessage", "payplug"),
					"check" => __("payplug_section_status_check", "payplug"),
					"enable_debug_label" => __("payplug_section_status_debug_label", "payplug"),
					"enable_debug_description" => __("payplug_section_status_debug_description", "payplug"),
				],
				"sandbox" => [
					"description" => __("payplug_section_status_description", "payplug"),
					"errorMessage" => __("payplug_section_status_errorMessage", "payplug"),
					"check" => __("payplug_section_status_check", "payplug"),
					"enable_debug_label" => __("payplug_section_status_debug_label", "payplug"),
					"enable_debug_description" => __("payplug_section_status_debug_description", "payplug"),
				]
			],
			"options" => [
				"type" => "-warning",
				"name" => "requirements",
				"options" => [
					[
						"status" => $payplug_requirements->valid_curl(),
						"text" => __("payplug_section_status_curl", "payplug")
					],
					[
						"status" => $payplug_requirements->valid_php(),
						"text" => __("payplug_section_status_php", "payplug")
					],
					[
						"status" => $payplug_requirements->valid_openssl(),
						"text" => __("payplug_section_status_ssl", "payplug")
					],
					[
						"status" => $payplug_requirements->valid_currency(),
						"text" => __("payplug_section_status_currency", "payplug")
					],
					[
						"status" => $payplug_requirements->valid_account(),
						"text" => __("payplug_section_status_account", "payplug")
					]
				]
			],
			"enable_debug_name" => "payplug_debug",
			"enable_debug_checked" => false
		];

		return $status;
	}

}
