<?php

namespace Payplug\PayplugWoocommerce\Admin;

class Validator {

	public static function enabled($value) {

		if ($value == 1){
			return true;
		}

		if ($value == 0){
			return false;
		}

		http_response_code(400);
		wp_send_json_error(['error' => 'enabled is missing']);
	}

	public static function mode($value) {
		if ($value == 1){
			return false;
		}

		if ($value == 0){
			return true;
		}

		http_response_code(400);
		wp_send_json_error(['error' => 'mode is missing']);
	}

	public static function payment_method($value) {

		if ( !empty($value) && in_array($value, ['redirect', 'popup', 'integrated']) ) {
			return true;
		}

		http_response_code(400);
		wp_send_json_error(['error' => 'payment_method is missing']);
	}

	public static function debug($value) {

		if ($value == 1){
			return true;
		}

		if ($value == 0){
			return false;
		}

		http_response_code(400);
		wp_send_json_error(['error' => 'mode is missing']);

		return false;
	}

	public static function save_card($value) {

		if ($value == 1){
			return true;
		}

		if ($value == 0){
			return false;
		}

		http_response_code(400);
		wp_send_json_error(['error' => 'oneclick is missing']);
	}

	public static function genericPaymentGateway($value, $payment, $test_mode) {

		if($test_mode){
			return false;
		}

		if ($value == 1 || $value){
			return true;
		}

		return false;
	}

	/**
	 * prevent saving when neither cart and checkout is enabled
	 *
	 * @param $cart
	 * @param $product
	 * @param $checkout
	 * @return true
	 */
	public static function applePayPaymentGatewayOptions($apple_pay, $cart, $product, $checkout, $carriers){

		if($apple_pay === false){
			return true;
		}

		if( $cart === false && $product === false && $checkout === false ){
			http_response_code(200);

			$arr = [
				'msg'=>__( 'applepay_cart_checkout_option_validation', 'payplug' ),
				'class'=>'error',
				'title'=>__( 'applepay_cart_checkout_option_validation_title', 'payplug' ),
				'close'=> __( 'payplug_ok', 'payplug' )
			];

			wp_send_json_error($arr);
		}

		if( ($cart === true || $product === true) && empty($carriers)){
			http_response_code(200);

			$arr = [
				'msg' => __( 'applepay_cart_carrier_enabled', 'payplug' ),
				'class' => 'error',
				'title' => __( 'applepay_cart_checkout_option_validation_title', 'payplug' ),
				'close' =>  __( 'payplug_ok', 'payplug' )
			];

			wp_send_json_error($arr);
		}

		return true;

	}

	public static function oney($value) {

		if ($value == 1){
			return true;
		}

		return false;

		http_response_code(400);
		wp_send_json_error(['error' => 'oney is missing']);
	}

	public static function oney_type($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['with_fees', 'without_fees'])) {
				return true;
			}
		}
		return false;
	}

	public static function oney_thresholds($min, $max) {

		$rmin = 100;
		$rmax = 3000;
		if( $min > 99 && $min<$max){
			$rmin = $min;
		}

		if( $max <= 3000 && $max>$min ){
			$rmax = $max;
		}

		return array("min"=>$rmin, "max"=>$rmax);
	}

	public static function oney_product_animation($status){

		if($status){
			return true;
		}else{
			return false;
		}

	}
}
