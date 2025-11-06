<?php

namespace Payplug\PayplugWoocommerce\Traits;

use Payplug\PayplugWoocommerce\Service\API;

trait ServiceGetter
{
	public function get_api_service() {
		return new API();
	}
}
