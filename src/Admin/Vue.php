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
			$payplug_wooc_settings = get_option( 'woocommerce_payplug_settings', [] );
			unset($payplug_wooc_settings["payplug_live_key"]);

			return [
				"payplug_wooc_settings" => $payplug_wooc_settings,
				"header"           		=> $header,
				"login"     			=> $this->payplug_section_login(),
				"logged"           		=> $logged,
				"payment_methods"  		=> $this->payplug_section_payment_methods(),
				"payment_paylater"  	=> $this->payplug_section_paylater(),
				"status" => $this->payplug_section_status()
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

		$disabled = false;
		if( empty(PayplugWoocommerceHelper::get_live_key()) ){
			$disabled = true;
		}

		return [
			"title"        => __( 'payplug_section_logged_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description"        => __( 'payplug_section_logged_description', 'payplug' ),
					"logout"             => __( 'payplug_section_logged_logout', 'payplug' ),
					"mode"               => __( 'payplug_section_logged_mode', 'payplug' ),
					"mode_description"   => __( 'payplug_section_logged_live_description', 'payplug' ),
					"link_learn_more"    => [
						"text"   => __( 'payplug_learn_more', 'payplug' ),
						"url"    => __( 'payplug_mode_learn_more_url', 'payplug' ),
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
						"text"   => __( 'payplug_learn_more', 'payplug' ),
						"url"    => __( 'payplug_mode_learn_more_url', 'payplug' ),
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
					"name"     => "payplug_sandbox",
					"label"    => "Live",
					"value"    => "0",
					"disabled" => $disabled,
				],
				[
					"name"    => "payplug_sandbox",
					"label"   => "Test",
					"value"   => "1"
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
						"url"    => "https://portal.payplug.com/signup",
						"target" => "_blank"
					],
					"content_description"  => __( 'payplug_section_subscribe_content_description', 'payplug' ),
					"already_have_account" => __( 'payplug_section_subscribe_already_have_account', 'payplug' ),
				],
				"sandbox" => [
					"description"          => __( 'payplug_section_subscribe_description', 'payplug' ),
					"link_create_account"  => [
						"text"   => __( 'payplug_section_subscribe_link_create_account', 'payplug' ),
						"url"    => "https://portal.payplug.com/signup",
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
		$enable = ( !empty( get_option( 'woocommerce_payplug_settings', [] )['enabled'] ) && get_option( 'woocommerce_payplug_settings', [] )['enabled'] === "yes") ? true : false;
		$payplug_requirements = $this->payplug_requirements();
		$enable = $enable && (!in_array(false, $payplug_requirements));

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
						"checked" => $enable === true ? true : false
					],
					[
						"value" => 0,
						"label" => __( 'payplug_section_header_disable_label', 'payplug' ),
						"checked" => $enable === false ? true : false
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
						"description"      => __( 'payplug_section_paylater_description_oney', 'payplug' ),
						"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), "https://support.payplug.com/hc/fr/articles/4408142346002", "_blank"),
					],
					"sandbox" => [
						"description"      => __( 'payplug_section_paylater_description_oney', 'payplug' ),
						"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), "https://support.payplug.com/hc/fr/articles/4408142346002", "_blank"),
					],
					"advanced" => [
						"description" => __( 'payplug_advanced_settings', 'payplug' ),""
					]
				],
				"options" => [
					[
						"name" => "payplug_oney_type",
						"className" => "_paylaterLabel",
						"label" => __( 'payplug_label_with_fees', 'payplug' ),
						"subText" => __( 'payplug_text_with_fees', 'payplug' ),
						"value" => 1
					],
					[
						"name" => "payplug_oney_type",
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
		$max_amount = (! empty( get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_max'] )) ? get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_max'] : 3000;
		$thresholds = [
			"name" => "thresholds",
			"image_url" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/thresholds.jpg' ),
			"title" => __( 'payplug_thresholds_oney_title', 'payplug' ),
			"descriptions" => [
				"description" => __( 'payplug_thresholds_oney_description', 'payplug' ),
				"min_amount" => [
					"name" => "oney_min_amounts",
					"value" => $min_amount,
					"placeholder" => $min_amount,
					"min" => "100"
				],
				"inter" => __( 'and', 'payplug' ),
				"max_amount" => [
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
			"image_url" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/product.jpg' ),
			"title" => __( 'display_the_oney_installments_pop_up_on_the_product_page', 'payplug' ),
			"switch" => true,
			"checked" => $active
		];
	}


	/**
	 * @return array
	 */
	public function payplug_section_status() {
		$payplug_requirements = $this->payplug_requirements();

		$status = [
			"error" => in_array(false, $payplug_requirements),
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
			"requirements" => [
				[
					"status" => $payplug_requirements["valid_curl"],
					"text" => __("payplug_section_status_curl", "payplug")
				],
				[
					"status" => $payplug_requirements["valid_php"],
					"text" => __("payplug_section_status_php", "payplug")
				],
				[
					"status" => $payplug_requirements["valid_openssl"],
					"text" => __("payplug_section_status_ssl", "payplug")
				],
				[
					"status" => $payplug_requirements["valid_account"],
					"text" => __("payplug_section_status_account", "payplug")
				]
			],
			"enable_debug_name" => "payplug_debug",
			"enable_debug_checked" => false
		];

		return $status;
	}

	private function payplug_requirements() {
		$payplug_requirements = new PayplugGatewayRequirements(new PayplugGateway());

		return [
			"valid_curl" => $payplug_requirements->valid_curl(),
			"valid_php" => $payplug_requirements->valid_php(),
			"valid_openssl" => $payplug_requirements->valid_openssl(),
			"valid_account" => $payplug_requirements->valid_account()
		];
	}

}
