<?php

namespace Payplug\PayplugWoocommerce\Admin\Vue;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PaymentMethods {

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function payment_method_standard( ) {

		$option = (get_option( 'woocommerce_payplug_settings', [] )['payment_method'] != "") ? get_option( 'woocommerce_payplug_settings', [] )['payment_method'] : "";

		$method = [
			"redirect" => false,
			"popup"	  => false,
			"integrated" => false,
		];

		switch($option){
			case "popup" : $method["popup"] = true;break;
			case "integrated" : $method["integrated"] = true;break;
			default: $method["redirect"] = true;break;
		}

		return [
			"type"         => "payment_method",
			"name"         => "standard",
			"title"        => __( 'payplug_section_standard_payment_title', 'payplug' ),
			"image"        => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/standard.svg' ),
			"checked"      => true,
			"hide"			=> true,
			"available_test_mode" => true,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_standard_payment_description', 'payplug' ),
					"advanced_options" => __( 'payplug_section_standard_payment_advanced_options_label', 'payplug' ),
				],
				"sandbox" => [
					"description"      => __( 'payplug_section_standard_payment_description', 'payplug' ),
					"advanced_options" => __( 'payplug_section_standard_payment_advanced_options_label', 'payplug' ),
				]
			],
			"options"      => [
				$this->title_field(),
				$this->description_field(),
				$this->embeded_option($method),
				$this->standard_warning_message(),
				$this->one_click_option()
			],
		];
	}

	private function description_field(){
		$description = get_option( 'woocommerce_payplug_settings', [] )['description'];
		return array(
			"type"         => "payment_option",
			"sub_type"     => "input",
			"name"     	   => "standard_payment_description",
			"title"		   => __("payplug_standard_payment_description_title", "payplug"),
			"value"		   => $description ?: null,
			"descriptions" => [
				"live"    => [
					"description"      => __("payplug_standard_payment_description_description", "payplug"),
					"placeholder"      => __("payplug_standard_payment_description_placeholder", "payplug"),
				],
				"sandbox" => [
					"description"      => __("payplug_standard_payment_description_description", "payplug"),
					"placeholder"      => __("payplug_standard_payment_description_placeholder", "payplug"),
				]
			]
		);
	}
	private function title_field(){
		$title = get_option( 'woocommerce_payplug_settings', [] )['title'];
		return array(
				"type"         => "payment_option",
				"sub_type"     => "input",
				"name"     	   => "standard_payment_title",
				"title"		   => __("payplug_standard_payment_title_title", "payplug"),
				"value"		   => !empty($title) ? $title : __("payplug_standard_payment_title_placeholder", "payplug"),
				"descriptions" => [
					"live"    => [
						"description"      => __("payplug_standard_payment_title_description", "payplug"),
						"placeholder"      => __("payplug_standard_payment_title_placeholder", "payplug"),
					],
					"sandbox" => [
						"description"      => __("payplug_standard_payment_title_description", "payplug"),
						"placeholder"      => __("payplug_standard_payment_title_placeholder", "payplug"),
					]
				],
		);
	}

	/**
	 * @param $active
	 *
	 * @return array|bool[]|false[]
	 */
	public function standard_warning_message( $active = null ) {
		$arr = [
			"type"         		=> "warning_message",
			"sub_type"     		=> "warning",
			"name"        		=> "warning_message",
			"payment_method"	=> "integrated",
			"description"  		=> __( 'payplug_section_standard_ip_warning', 'payplug' )
		];

		return $arr;
	}

	/**
	 * @param $active
	 *
	 * @return array|bool[]|false[]
	 */
	public function one_click_option( $active = null ) {
		$option = [
			"type"         => "payment_option",
			"sub_type"     => "switch",
			"name"         => "one_click",
			"title"        => __( 'payplug_section_one_click_option_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description"    => __( 'payplug_section_one_click_option_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						"https://support.payplug.com/hc/en-gb/articles/4409698334098",
						"_blank" ),
				],
				"sandbox" => [
					"description"    => __( 'payplug_section_one_click_option_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						"https://support.payplug.com/hc/en-gb/articles/4409698334098",
						"_blank" ),
				]
			]
		];
		if (isset(get_option( 'woocommerce_payplug_settings', [] )['oneclick'])) {
			if (get_option( 'woocommerce_payplug_settings', [] )['oneclick'] != 'no') {
				$option = $option + ["checked" => true];
			} elseif (get_option( 'woocommerce_payplug_settings', [] )['oneclick'] == 'no') {
				$option = $option + ["checked" => false];
			}

		}
		return $option;
	}

	/**
	 * @return array
	 */
	public function embeded_option($method) {

		$options = Array(
			array(
				"name"    => "payplug_embedded",
				"label"   => __( 'payplug_section_standard_payment_option_integrated_label', 'payplug' ),
				"value"   => "integrated",
				"checked" => $method['integrated']
			),
			array(
			"name"  => "payplug_embedded",
			"label" => __( 'payplug_section_standard_payment_option_popup_label', 'payplug' ),
			"value" => "popup",
			"checked" => $method['popup']
			),
			array(
			"name"    => "payplug_embedded",
			"label"   => __( 'payplug_section_standard_payment_option_redirected_label', 'payplug' ),
			"value"   => "redirect",
			"checked" => $method['redirect']
			)
		);

		return [
			"type"         => "payment_option",
			"sub_type"     => "IOptions",
			"name"         => "embeded",
			"title"        => __( 'payplug_section_standard_payment_option_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description_redirect"    => __( 'payplug_section_standard_payment_redirected_description', 'payplug' ),
					"description_popup"    => __( 'payplug_section_standard_payment_popup_description', 'payplug' ),
					"description_integrated"    => __( 'payplug_section_standard_payment_integrated_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						__( 'payplug_embeded_option_url', 'payplug' ),
						"_blank" ),
				],
				"sandbox" => [
					"description_redirect"    => __( 'payplug_section_standard_payment_redirected_description', 'payplug' ),
					"description_popup"    => __( 'payplug_section_standard_payment_popup_description', 'payplug' ),
					"description_integrated"    => __( 'payplug_section_standard_payment_integrated_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						__( 'payplug_embeded_option_url', 'payplug' ),
						"_blank" ),
				]
			],
			"options" => $options
		];
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public static function payment_method_applepay( $active = false, $options, $carriers = [] ) {

		$checkout = $cart = false;

		if( !empty($options['applepay_checkout']) && ($options['applepay_checkout'] == "yes" ) ){
			$checkout = true;
		}

		if( !empty($options['applepay_cart']) && ($options['applepay_cart'] == "yes" ) ){
			$cart = true;
		}

		if( $active === true  && !$checkout && !$cart ){
			$checkout = true;
		}

		return [
			"type" => "payment_method",
			"name" => "applepay",
			"title" => __( 'payplug_section_applepay_payment_title', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/applepay.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_applepay_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_applepay_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_applepay_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_applepay_more_url', 'payplug' ), "_blank"),
				]
			],
			"options" =>
				[
					[
						"type" => "payment_option",
						"sub_type" => "IOptions",
						"name" => "applepay_display",
						"title" => __( 'applepay_display_choice_title', 'payplug' ),
						"multiple" => true,
						"options" =>
							[
								[
									"name" => "applepay_display",
									"label" => __( 'applepay_display_checkout', 'payplug' ),
									"value" => "checkout",
									"checked" => $checkout || ($active && !$cart)
								],
								[
									"name" => "applepay_display",
									"label" => __( 'applepay_display_cart', 'payplug' ),
									"value" => "cart",
									"checked" => $cart
								]
							],
						"carriers" =>
							[
								"title" => __( 'applepay_active_carriers_title', 'payplug' ),
								"alert" => __( 'applepay_active_carriers_alert', 'payplug' ),
								"descriptions" => [
									"live"    => [
										"description"      => __( 'applepay_active_carriers_description', 'payplug' ),
										"description_bold" => __( 'applepay_active_carriers_description_bold', 'payplug' ),
										"description_warning" => __( 'applepay_active_carriers_description_warning', 'payplug' ),
									],
									"sandbox" => []
								],
								"instructions" => __( 'applepay_active_carriers_instructions', 'payplug' ),
								"carriers_list" => PayplugWoocommerceHelper::available_shipping_methods($carriers),

							]
					]
				]
		];
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public static function payment_method_bancontact( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "bancontact",
			"title" => __( 'payplug_section_bancontact_payment_title', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/bancontact.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_bancontact_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_bancontact_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_bancontact_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_bancontact_more_url', 'payplug' ), "_blank"),
				]
			],
		];
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public static function payment_method_amex( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "american_express",
			"title" => __( 'payplug_section_american_express_payment_title', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/Amex_logo_color.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_american_express_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_amex_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_amex_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_amex_more_url', 'payplug' ), "_blank"),
				]
			],
		];
	}

	/**
	 * @param $active
	 * @return array
	 */
	public static function payment_method_satispay( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "satispay",
			"title" => __( 'payplug_satispay_activate', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/satispay.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_satispay_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_satispay_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_satispay_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_satispay_more_url', 'payplug' ), "_blank"),
				]
			],
			"options"      => [
				self::get_allowed_countries("satispay")
			],
		];
	}

	/**
	 * @param $active
	 * @return array
	 */
	public static function payment_method_mybank( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "mybank",
			"title" => __( 'payplug_mybank_activate', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/mybank.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_mybank_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_mybank_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_mybank_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_mybank_more_url', 'payplug' ), "_blank"),
				]
			],
			"options"      => [
				self::get_allowed_countries("mybank")
			],
		];
	}

	/**
	 * @param $active
	 * @return array
	 */
	public static function payment_method_sofort( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "sofort",
			"title" => __( 'payplug_sofort_activate', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/sofort.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_sofort_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_sofort_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_sofort_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_sofort_more_url', 'payplug' ), "_blank"),
				]
			],
			"options"      => [
				self::get_allowed_countries("sofort")
			],
		];
	}

	/**
	 * @param $active
	 * @return array
	 */
	public static function payment_method_giropay( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "giropay",
			"title" => __( 'payplug_giropay_activate', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/giropay.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_giropay_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_giropay_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_giropay_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_giropay_more_url', 'payplug' ), "_blank"),
				]
			],
			"options"      => [
				self::get_allowed_countries("giropay")
			],
		];
	}

	/**
	 * @param $active
	 * @return array
	 */
	public static function payment_method_ideal( $active = false ) {
		return [
			"type" => "payment_method",
			"name" => "ideal",
			"title" => __( 'payplug_ideal_activate', 'payplug' ),
			"image" => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/ideal.svg' ),
			"checked" =>  $active,
			"available_test_mode" => false,
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_ideal_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_ideal_more_url', 'payplug' ), "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_ideal_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ), __( 'payplug_ideal_more_url', 'payplug' ), "_blank"),
				]
			],
			"options"      => [
				self::get_allowed_countries("ideal")
			],
		];
	}

	/**
	 * @param $id
	 * @return false|string
	 */
	private static function get_allowed_countries($id){
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if(empty($account['payment_methods'][$id])){
			return false;
		}

		$arr = [
			"type"         		=> "info_message",
			"sub_type"     		=> "text",
			"name"        		=> "allowed_countries",
			"payment_method"	=> $id,
			"description"  		=> trim(sprintf(__("payplug_payment_gateways_country_permissions", "payplug"), ucfirst($id))) . implode(", ", $account['payment_methods'][$id]['allowed_countries']) . "."
		];

		 return $arr;

	}


}
