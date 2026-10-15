<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

/**
 * Logger of PayplugGateway::log() between start() and stop(), recording each entry instead of writing it.
 */
class GatewayLogSpy extends \WC_Logger
{
    /**
     * @var array[] level and message of each entry
     */
    public $entries = [];

    /**
     * @var array logger and logging flag of PayplugGateway before start()
     */
    private $previous = [];

    public static function start(): self
    {
        $spy = new self();
        $spy->previous = self::swap($spy, true);

        return $spy;
    }

    public function stop(): void
    {
        self::swap(...$this->previous);
    }

    public function log($level, $message, $context = []): void
    {
        $this->entries[] = ['level' => $level, 'message' => $message];
    }

    /**
     * @param \WC_Logger_Interface|null $log
     * @param bool|null $enabled
     *
     * @return array the logger and logging flag replaced
     */
    private static function swap($log, $enabled): array
    {
        $swap = \Closure::bind(static function ($log, $enabled): array {
            $previous = [PayplugGateway::$log, PayplugGateway::$log_enabled];
            PayplugGateway::$log = $log;
            PayplugGateway::$log_enabled = $enabled;

            return $previous;
        }, null, PayplugGateway::class);

        return $swap($log, $enabled);
    }
}
