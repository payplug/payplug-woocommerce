<?php

namespace Payplug\PayplugWoocommerce\Admin\Vue;

class PaymentMethods {

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public function payment_method_standard( ) {

		$option = (get_option( 'woocommerce_payplug_settings', [] )['payment_method'] != "") ? get_option( 'woocommerce_payplug_settings', [] )['payment_method'] : false;

		$redirect = false;
		if($option === "redirect"){
			$redirect = true;
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
				$this->embeded_option($redirect),
				$this->one_click_option(),
			],
			"advanced_settings"  => [
				"title" => "Advanced configuration",
				"options" => [
					[
						"title" => "Activate non-guaranteed fractional payments",
						"class" => "-installment",
						"switch_enable" => [
							"name" => "payplug_inst",
							"checked" => false
						],
						"installements_descriptions" => [
							[
								"text" => "You can view all your payment deadlines (past and future) in this ",
								"links" => [
									[
										"text" => "dedicated menu. ",
										"url" => "#some_url",
										"target" => "_blank"
									],
									[
										"text" => "Find out more.",
										"url" => "#some_url",
										"target" => "_blank"
									]
								]
							]
						],
						"installements" => [
							"description" => "Offer your customers the option to pay for their orders in",
							"text_from" => "from",
							"options_installment_name" => "payplug_inst_mode",
							"options_installment_disabled" => true,
							"options_installment" => [
								[
									"value" => 2,
									"label" => "2 instalments",
									"checked" => true
								],
								[
									"value" => 3,
									"label" => "3 instalments",
									"checked" => false
								],
								[
									"value" => 4,
									"label" => "4 instalments",
									"checked" => false
								]
							],
							"input_amount" => [
								"name" => "payplug_inst_min_amount",
								"value" => 150,
								"min" => 4,
								"step" => 1,
								"max" => 20000
							],
							"input_amount_error" => "Amount must be greater than 4€ and lower than 20000€."
						],
						"notes" => [
							"type" => "-warning",
							"description" => "Please note => this type of payment is not guaranteed.<br/\\>If a default affects one of the future instalments, this amount will be lost."
						]
					],
					[
						"title" => "Defer the payment",
						"class" => "-deferred",
						"switch_enable" => [
							"name" => "payplug_deferred",
							"checked" => false
						],
						"deferred_descriptions" => [
							[
								"text" => "You have a maximum of 7 days to capture the payment (from the date of authorisation).",
								"links" => [
									[
										"text" => "Find out more.",
										"url" => "https =>//support.payplug.com/hc/en-gb/articles/360010088420",
										"target" => "_blank"
									]
								]
							]
						],
						"deferred_description" => "How do you want to trigger the payment capture?",
						"capture_disabled" => true,
						"capture_name" => "payplug_deferred_state",
						"capture" => [
							[
								"value" => "1",
								"label" => "Manual capture",
								"checked" => true
							],
							[
								"value" => "2",
								"label" => "Automatic capture at status => Awaiting check payment",
								"checked" => false
							],
							[
								"value" => "3",
								"label" => "Automatic capture at status => Payment accepted",
								"checked" => false
							]
						]
					]
				]
			]
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
	public function embeded_option($redirect) {

		return [
			"type"         => "payment_option",
			"sub_type"     => "IOptions",
			"name"         => "embeded",
			"title"        => __( 'payplug_section_standard_payment_option_title', 'payplug' ),
			"descriptions" => [
				"live"    => [
					"description_redirect"    => __( 'payplug_section_standard_payment_redirected_description', 'payplug' ),
					"description_popup"    => __( 'payplug_section_standard_payment_popup_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						__( 'payplug_embeded_option_url', 'payplug' ),
						"_blank" ),
				],
				"sandbox" => [
					"description_redirect"    => __( 'payplug_section_standard_payment_redirected_description', 'payplug' ),
					"description_popup"    => __( 'payplug_section_standard_payment_popup_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						__( 'payplug_embeded_option_url', 'payplug' ),
						"_blank" ),
				]
			],
			"options"      => [
				[
					"name"  => "payplug_embedded",
					"label" => __( 'payplug_section_standard_payment_option_popup_label', 'payplug' ),
					"value" => "popup",
					"checked" => $redirect ? false : true
				],
				[
					"name"    => "payplug_embedded",
					"label"   => __( 'payplug_section_standard_payment_option_redirected_label', 'payplug' ),
					"value"   => "redirect",
					"checked" => $redirect ? true : false
				]
			]
		];
	}

	/**
	 * @param $active
	 *
	 * @return array
	 */
	public static function payment_method_applepay( $active = false ) {
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

}
