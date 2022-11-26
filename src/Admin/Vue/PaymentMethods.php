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

		$redirect = true;
		if($option === "embedded"){
			$redirect = false;
		}

		return [
			"type"         => "payment_method",
			"name"         => "standard",
			"title"        => __( 'payplug_section_standard_payment_title', 'payplug' ),
			"image"        => esc_url( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/standard.svg' ),
			"checked"      => true,
			"hide"		=> true,
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
			]
		];
	}

	private function description_field(){
		return array(
			"type"         => "payment_option",
			"sub_type"     => "input",
			"name"     	   => "standard_payment_description",
			"title"		   => __("payplug_standard_payment_description_title", "payplug"),
			"value"		   => null,
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
					"description_redirected"    => __( 'payplug_section_standard_payment_redirected_description', 'payplug' ),
					"description_popup"    => __( 'payplug_section_standard_payment_popup_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						"https://support.payplug.com/hc/en-gb/articles/4409698334098",
						"_blank" ),
				],
				"sandbox" => [
					"description_redirected"    => __( 'payplug_section_standard_payment_redirected_description', 'payplug' ),
					"description_popup"    => __( 'payplug_section_standard_payment_popup_description', 'payplug' ),
					"link_know_more" => Component::link(
						__( 'payplug_know_more_label', 'payplug' ),
						"https://support.payplug.com/hc/en-gb/articles/4409698334098",
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
					"value"   => "redirected",
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
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_applepay_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/5149384347292", "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/5149384347292", "_blank"),
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
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_bancontact_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/4408157435794", "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/4408157435794", "_blank"),
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
			"descriptions" => [
				"live"    => [
					"description"      => __( 'payplug_section_american_express_payment_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/4408157435794", "_blank"),
				],
				"sandbox" => [
					"description"      => __( 'payplug_unavailable_testmode_description', 'payplug' ),
					"link_know_more" => Component::link(__( 'payplug_know_more_label', 'payplug' ),"https://support.payplug.com/hc/en-gb/articles/4408157435794", "_blank"),
				]
			],
		];
	}

}
