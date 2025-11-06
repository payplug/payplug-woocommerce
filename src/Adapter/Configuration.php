<?php

namespace Payplug\PayplugWoocommerce\Adapter;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

trait Configuration
{
	public function get_options() {
		return PayplugWoocommerceHelper::get_payplug_options();
	}
}
