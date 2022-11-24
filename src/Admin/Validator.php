<?php

namespace Payplug\PayplugWoocommerce\Admin;

class Validator {

	public static function enabled($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function mode($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function payment_method($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['redirect', 'popup'])) {
				return true;
			}
		}
		return false;
	}

	public static function debug($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function oneclick($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function bancontact($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function apple_pay($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function american_express($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
	}

	public static function oney($value) {
		if (isset($value) && !empty($value)) {
			if (in_array($value, ['yes', 'no'])) {
				return true;
			}
		}
		return false;
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
