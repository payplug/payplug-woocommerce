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
			$payplug = (new PayplugGateway());

			//TODO:: use the same get_option everywhere on the BO
			$payplug_wooc_settings = get_option( 'woocommerce_payplug_settings', [] );

			//show IP button - when the changes on IP availability are stable this should be deleted and conditions on vue side too
			$payplug_wooc_settings['can_use_integrated_payments'] = true;

			update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $payplug_wooc_settings) );

			if ((empty($payplug_wooc_settings['oney_thresholds_default_min'])) && (empty($payplug_wooc_settings['oney_thresholds_default_max']))) {

				$payplug_wooc_settings['oney_thresholds_default_min'] = $payplug->min_oney_price;
				$payplug_wooc_settings['oney_thresholds_default_max'] = $payplug->max_oney_price;

				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug',$payplug_wooc_settings ) );
			}

			unset($payplug_wooc_settings["payplug_live_key"]);
			unset($payplug_wooc_settings["payplug_test_key"]);
			unset($payplug_wooc_settings["payplug_password"]);
			unset($payplug_wooc_settings["payplug_merchant_id"]);

			return [
				"payplug_wooc_settings" => $payplug_wooc_settings,
				"header"           		=> $header,
				"login"     			=> $this->payplug_section_login(),
				"logged"           		=> $logged,
				"payment_methods"  		=> $this->payplug_section_payment_methods($payplug_wooc_settings),
				"payment_paylater"  	=> $this->payplug_section_paylater($payplug_wooc_settings),
				"status" 				=> $this->payplug_section_status($payplug_wooc_settings),
				"footer" 				=> $this->payplug_section_footer(),
			];
		}

		return [
			"header"    => $this->payplug_section_header(),
			"login"     => $this->payplug_section_login(),
			"subscribe" => $this->payplug_section_subscribe(),
			"payment_methods"  => $this->payplug_section_payment_methods(),
			"payment_paylater"  => $this->payplug_section_paylater(),
			"status" => $this->payplug_section_status(),
			"footer" => $this->payplug_section_footer(),
		];
	}

	/**
	 * @return array
	 */
	public function payplug_section_logged() {

		$inactive = false;
		if( empty(PayplugWoocommerceHelper::get_live_key()) ){
			$inactive = true;
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
						"url"    => "https://portal.payplug.com/",
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
						"url"    => "https://portal.payplug.com/",
						"target" => "_blank"
					],
				]
			],
			"options"      => [
				[
					"name"    => "payplug_sandbox",
					"label"   => "Test",
					"value"   => 1, //test
					"checked" => !PayplugWoocommerceHelper::check_mode()
				],
				[
					"name"     => "payplug_sandbox",
					"label"    => "Live",
					"value"    => 0, //live
					"checked" => PayplugWoocommerceHelper::check_mode()
				]
			],
			"inactive_modal"		   => [
				"inactive" => $inactive,
				"title" => __( 'payplug_live_mode', 'payplug' ),
				"description" => __( 'payplug_section_logged_modal_description', 'payplug' ),
				"password_label" => __( 'payplug_section_login_password_label', 'payplug' ),
				"cancel" => __( 'payplug_cancel', 'payplug' ),
				"ok" => __( 'payplug_ok', 'payplug' ),
			],
			"inactive_account" => [
				"warning" => [
					"title" => __( 'payplug_inactive_account_warning_title', 'payplug' ),
					"description" => __( 'payplug_inactive_account_warning_description1', 'payplug' ) .
						__( 'payplug_inactive_account_warning_description2', 'payplug' ) .
						__( 'payplug_inactive_account_warning_description3', 'payplug' ),
				],
				"error" => [
					"title" => __( 'payplug_inactive_account_error_title', 'payplug' ),
					"description" => __( 'payplug_inactive_account_error_description', 'payplug' ),
				]
			],
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
		$enable = $enable && $this->payplug_requirements();
		$disabled = !$this->payplug_requirements();

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
				"disabled" => $disabled,
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
	public function payplug_section_payment_methods($options = array()) {

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
				PaymentMethods::payment_method_amex(!empty($options) && $options['american_express'] === 'yes'),
				PaymentMethods::payment_method_applepay(!empty($options) && $options['apple_pay'] === 'yes'),
				PaymentMethods::payment_method_bancontact(!empty($options) && $options['bancontact'] === 'yes'),
				PaymentMethods::payment_method_satispay(!empty($options) && $options['satispay'] === 'yes'),
				PaymentMethods::payment_method_mybank(!empty($options) && $options['mybank'] === 'yes'),
				PaymentMethods::payment_method_sofort(!empty($options) && $options['sofort'] === 'yes'),
				PaymentMethods::payment_method_giropay(!empty($options) && $options['giropay'] === 'yes'),
				PaymentMethods::payment_method_ideal(!empty($options) && $options['ideal'] === 'yes'),

			]
		];

		return $section;
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function payplug_section_paylater($options = array() ) {

		$max = !empty($options['oney_thresholds_max']) ? $options['oney_thresholds_max'] : 3000;
		$min = !empty($options['oney_thresholds_min']) ? $options['oney_thresholds_min'] : 100;
		$product_page = !empty($options['oney_product_animation']) && $options['oney_product_animation'] === 'yes' ? true : false;

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
				"checked" => !empty($options) && $options['oney'] === 'yes',
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
						"value" => "with_fees",
						"checked" => !empty($options) && $options['oney_type'] === 'with_fees',
					],
					[
						"name" => "payplug_oney_type",
						"className" => "_paylaterLabel",
						"label" => __( 'payplug_label_without_fees', 'payplug' ),
						"subText" => __( 'payplug_text_without_fees', 'payplug' ),
						"value" => "without_fees",
						"checked" => !empty($options) && $options['oney_type'] === 'without_fees',
					]
				],
				"advanced_options" => [
					$this->thresholds_option($max, $min),
					$this->show_oney_popup_product($product_page)
				]
			]
		];

		return $section;
	}

	/**
	 * @return array
	 */
	public function thresholds_option($max, $min) {

		$thresholds = [
			"name" => "thresholds",
			"image_url" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/thresholds.svg' ),
			"title" => __( 'payplug_thresholds_oney_title', 'payplug' ),
			"descriptions" => [
				"description" => __( 'payplug_thresholds_oney_description', 'payplug' ),
				"min_amount" => [
					"name" => "oney_min_amounts",
					"value" => $min,
					"placeholder" => $min,
					"default" => get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_default_min']
				],
				"inter" => __( 'and', 'payplug' ),
				"max_amount" => [
					"name" => "oney_max_amounts",
					"value" => $max,
					"placeholder" => $max,
					"default" => get_option( 'woocommerce_payplug_settings', [] )['oney_thresholds_default_max']
				],
				"error" => [
					"text" => __( 'payplug_thresholds_error_msg', 'payplug' ),
					"maxtext" => __('payplug_thresholds_error_maxtext_msg', 'payplug'),
					"mintext" => __('payplug_thresholds_error_mintext_msg', 'payplug'),
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
			"name" => "oney_product_animation",
			"image_url" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/product.svg' ),
			"title" => __( 'display_the_oney_installments_pop_up_on_the_product_page', 'payplug' ),
			"descriptions" => [[
				"description" => __( 'payplug_oney_product_page_description', 'payplug' ),
				"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), "https://support.payplug.com/hc/fr/articles/4408142346002", "_blank")
				]],
			"switch" => true,
			"checked" => $active
		];
	}


	/**
	 * @return array
	 */
	public function payplug_section_status( $options = [] ) {
		$payplug_requirements = new PayplugGatewayRequirements(new PayplugGateway());
		$checked = !empty($options['debug']) && $options['debug'] === 'yes' ? true : false;

		$status = [
			"error" => !$this->payplug_requirements(),
			"title" => __("payplug_section_status_title", "payplug"),
			"descriptions" => [
				"live" => [
					"description" => __("payplug_section_status_description", "payplug"),
					"errorMessage" => __("payplug_section_status_errorMessage", "payplug"),
					"check" => __("payplug_section_status_check", "payplug"),
					"check_success" => __("payplug_section_status_check_success", "payplug"),
				],
				"sandbox" => [
					"description" => __("payplug_section_status_description", "payplug"),
					"errorMessage" => __("payplug_section_status_errorMessage", "payplug"),
					"check" => __("payplug_section_status_check", "payplug"),
					"check_success" => __("payplug_section_status_check_success", "payplug"),
				]
			],
			"requirements" => [
				$payplug_requirements->curl_requirement(),
				$payplug_requirements->php_requirement(),
				$payplug_requirements->openssl_requirement(),
				$payplug_requirements->currency_requirement(), //MISSING THIS MESSAGES
				$payplug_requirements->account_requirement(),
			],
			"debug" => [
				"live" => [
					"title" => __("payplug_section_status_debug_label", "payplug"),
					"description" => __("payplug_section_status_debug_description", "payplug"),
				],
				"sandbox" => [
					"title" => __("payplug_section_status_debug_label", "payplug"),
					"description" => __("payplug_section_status_debug_description", "payplug"),
				]
			],
			"enable_debug_check" => $checked
		];

		return $status;
	}

	/**
	 * check if there's any requirement missing
	 * @return bool
	 */
	private function payplug_requirements() {
		$payplug_requirements = new PayplugGatewayRequirements(new PayplugGateway());
		return $payplug_requirements->satisfy_requirements();
	}

	/**
	 * @return array
	 */
	public function payplug_section_footer( ) {
		return [
			"save_changes_text" => __("payplug_save_changes_text", "payplug"),
			"description" => [
				__("payplug_section_help_description1", "payplug"),
				__("payplug_section_help_description2", "payplug")
			],
			"link_help" => Component::link(
				__( 'payplug_section_help_link_help_text', 'payplug' ),
				__( 'payplug_section_help_link_help_url', 'payplug' ),
				"_blank"
			),
		];
	}

}
