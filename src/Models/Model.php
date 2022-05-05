<?php

namespace Payplug\PayplugWoocommerce\Models;

use Payplug\PayplugWoocommerce\Traits\Base_Registry;

if ( ! class_exists( __NAMESPACE__ . '\\' . 'Model' ) ) {

	class Model {
		use Base_Registry;
	}

}
