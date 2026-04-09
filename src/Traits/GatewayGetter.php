<?php

namespace Payplug\PayplugWoocommerce\Traits;

trait GatewayGetter
{
    public function get_gateway($name = '')
    {
        if (!is_string($name) || empty($name)) {
            return null;
        }

        try {
            $gateway_name = '\Payplug\PayplugWoocommerce\Gateway\\';
            $gateway_name .= str_replace('_', '', ucwords($name, '_')) . 'Gateway';

            if (!class_exists($gateway_name)) {
                return null;
            }
            $gateway = new $gateway_name();
        } catch (\Exception $e) {
            return null;
        }

        return $gateway;
    }
}
