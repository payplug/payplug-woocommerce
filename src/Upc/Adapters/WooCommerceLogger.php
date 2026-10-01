<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\ILogger;

class WooCommerceLogger implements ILogger
{
    private const SOURCE = 'payplug-upc';

    public function debug(string $message, array $context = []): void
    {
        wc_get_logger()->debug($message, array_merge($context, ['source' => self::SOURCE]));
    }

    public function info(string $message, array $context = []): void
    {
        wc_get_logger()->info($message, array_merge($context, ['source' => self::SOURCE]));
    }

    public function error(string $message, array $context = []): void
    {
        wc_get_logger()->error($message, array_merge($context, ['source' => self::SOURCE]));
    }
}
