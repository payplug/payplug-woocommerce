<?php

namespace Payplug\PayplugWoocommerce\Traits;

trait ServiceGetter
{
    private $configuration;
    private $api;

    /**
     * @param $name
     *
     * @return object|null
     */
    public function get_service($name = '')
    {
        if (!is_string($name) || empty($name)) {
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

    /**
     * @return object|null
     */
    public function get_configuration()
    {
        $this->configuration = empty($this->configuration)
            ? $this->get_service('configuration')
            : $this->configuration;

        return $this->configuration;
    }

    /**
     * @return object|null
     */
    public function get_api()
    {
        $this->api = empty($this->api)
            ? $this->get_service('api')
            : $this->api;

        return $this->api;
    }
}
