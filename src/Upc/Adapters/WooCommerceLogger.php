<?php

namespace Payplug\PayplugWoocommerce\Upc\Adapters;

use PayplugUnifiedCore\Contracts\ILogger;

class WooCommerceLogger implements ILogger
{
    private const SOURCE = 'payplug-upc';

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * The Apple Pay route also runs while WooCommerce is inactive: nothing is logged then.
     */
    private function log(string $level, string $message, array $context): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->log($level, $message, array_merge($context, ['source' => self::SOURCE]));
    }
}
