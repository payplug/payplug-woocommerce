<?php

namespace Payplug\PayplugWoocommerce\Traits;

trait ServiceGetter
{
//	public $configuration;

	public function get_service($name = '')
	{
		if(!is_string($name) || empty($name)) {
			return null;
		}

		try {
			$service_name = '\Payplug\PayplugWoocommerce\Service\\';
			$service_name .= str_replace('_', '', ucwords($name, '_'));

			if (!class_exists($service_name)) {
				return null;
			}
			$service = new $service_name();
//			$this->{$name} = new $service_name();
		} catch (\Exception $e) {
			return null;
		}

		return $service;
	}
}
