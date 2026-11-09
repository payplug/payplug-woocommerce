<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

use PayplugUnifiedCore\Contracts\ILogger;

/**
 * Logger recording each entry instead of writing it.
 */
final class ArrayLogger implements ILogger
{
    /**
     * @var array[] level, message and context of each entry
     */
    public $records = [];

    public function debug(string $message, array $context = []): void
    {
        $this->records[] = ['debug', $message, $context];
    }

    public function info(string $message, array $context = []): void
    {
        $this->records[] = ['info', $message, $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->records[] = ['error', $message, $context];
    }
}
