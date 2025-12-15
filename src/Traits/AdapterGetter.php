<?php

namespace Payplug\PayplugWoocommerce\Traits;

trait AdapterGetter
{
	public function get_adapter($name = '')
	{
		if(!is_string($name) || empty($name)) {
			return null;
		}

		try {
			$adapter_name = '\Payplug\PayplugWoocommerce\Adapter\\';
			$adapter_name .= str_replace('_', '', ucwords($name, '_'));

			if (!class_exists($adapter_name)) {
				return null;
			}
			$adapter = new $adapter_name();
		} catch (\Exception $e) {
			return null;
		}

		return $adapter;
	}
}
