<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\Mocks;

class PaymentGateways {

	static public $gateways = array(
		"american_express" => array(
			"id" => "american_express",
			"title" => "Pay with Amex",
			"method_title" => "Pay with Amex",
			"description" => "",
			"method_description" => "",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/Amex_logo_color.svg" alt="american_express Icon" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => false,
		),
		"apple_pay" => array(
			"id" => "apple_pay",
			"title" => "Pay with Apple Pay",
			"method_title" => "Pay with Apple Pay",
			"description" => '<div id="apple-pay-button-wrapper"><apple-pay-button buttonstyle="black" type="pay" locale="en_US"></apple-pay-button></div>',
			"method_description" => "",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/apple-pay-checkout.svg" alt="Apple Pay" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => false,
		),
		"bancontact" => array(
			"id" => "bancontact",
			"title" => "Pay with Bancontact",
			"method_title" => "Pay with Bancontact",
			"description" => "",
			"method_description" => "Activate Bancontact payments.",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/lg-bancontact-checkout.svg" alt="bancontact Icon" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => false,
		),
		"payplug" => array(
			"id" => "payplug",
			"title" => "Credit card checkout",
			"method_title" => "PayPlug",
			"description" => "You are in TEST MODE. In test mode you can use the card 4242424242424242 with any valid expiration date and CVC.",
			"method_description" => "Enable PayPlug for your customers.",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/logos_scheme_CB.svg" alt="Visa & Mastercard" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => true,
		),
		"oney_x3_with_fees" => array(
			"id" => "oney_x3_with_fees",
			"title" => "Pay by card in 3x with Oney",
			"method_title" => "PayPlug Oney 3x",
			"description" => "",
			"method_description" => "Enable PayPlug Oney 3x for your customers.",
			"has_fields" => false,
			"image" => "x3_with_fees.svg",
			"enable" => "no",
			"enable_on_test_mode" => true,
		),
		"oney_x3_without_fees" => array(
			"id" => "oney_x3_without_fees",
			"title" => "Pay by credit card in 3x installments without fees with Oney",
			"method_title" => "PayPlug Oney 3x",
			"description" => "",
			"method_description" => "Enable PayPlug Oney 3x for your customers.",
			"has_fields" => false,
			"image" => "x3_without_fees.svg",
			"enable" => "no",
			"enable_on_test_mode" => true,
		),
		"oney_x4_with_fees" => array(
			"id" => "oney_x4_with_fees",
			"title" => "Pay by card in 4x with Oney",
			"method_title" => "PayPlug Oney 4x",
			"description" => "",
			"method_description" => "Enable PayPlug Oney 4x for your customers.",
			"has_fields" => false,
			"image" => "x4_with_fees.svg",
			"enable" => "no",
			"enable_on_test_mode" => true,
		),
		"oney_x4_without_fees" => array(
			"id" => "oney_x4_without_fees",
			"title" => "Pay by credit card in 4x installments without fees with Oney",
			"method_title" => "PayPlug Oney 4x",
			"description" => "",
			"method_description" => "Enable PayPlug Oney 4x for your customers.",
			"has_fields" => false,
			"image" => "x4_without_fees.svg",
			"enable" => "no",
			"enable_on_test_mode" => true,
		),
		"ideal" => array(
			"id" => "ideal",
			"title" => "Pay with iDEAL",
			"method_title" => "Pay with iDEAL",
			"description" => "",
			"method_description" => "",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/ideal.svg" alt="ideal Icon" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => false,
		),
		"mybank" => array(
			"id" => "mybank",
			"title" => "Pay with MyBank",
			"method_title" => "Pay with MyBank",
			"description" => "",
			"method_description" => "",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/mybank.svg" alt="mybank Icon" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => false,
		),
		"satispay" => array(
			"id" => "satispay",
			"title" => "Pay with Satispay",
			"method_title" => "Pay with Satispay",
			"description" => "",
			"method_description" => "",
			"has_fields" => false,
			"image" => '<img src="'.PAYPLUG_GATEWAY_PLUGIN_URL.'/assets/images/checkout/satispay.svg" alt="satispay Icon" class="payplug-payment-icon" />',
			"enable" => "no",
			"enable_on_test_mode" => false,
		)
	);

}



