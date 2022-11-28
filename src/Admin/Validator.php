<?php

namespace Payplug\PayplugWoocommerce\Admin;

class Validator {

	public static function enabled($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return true;
			elseif ($value === "false")
				return false;
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'enabled is missing']);
	}

	public static function mode($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'mode is missing']);
	}

	public static function payment_method($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['redirect', 'popup'])) {
				return true;
			}
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'payment_method is missing']);
	}

	public static function debug($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'debug is missing']);
	}

	public static function oneclick($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'oneclick is missing']);
	}

	public static function bancontact($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'bancntact is missing']);
	}

	public static function apple_pay($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
		http_response_code(400);
		wp_send_json_error(['error' => 'apple pay is missing']);
	}

	public static function american_express($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
		return false;
	}

	public static function oney($value) {
		if (isset($value) && !empty($value)) {
			if ($value === "true")
				return "yes";
			elseif ($value === "false")
				return "no";
		}
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
		if (($min > 100) && ($max < 3000) && ($min < $max)) {
			return true;
		}
		return false;
	}

}
