<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLogger;
use PHPUnit\Framework\TestCase;

class WooCommerceLogger_test extends TestCase
{
    public function testDebugWritesToTheWooCommerceLogWithThePayplugUpcSource(): void
    {
        $handler = new \WC_Log_Handler_File();
        $logger = new WooCommerceLogger();

        $logger->debug('upc test debug message');

        $contents = file_get_contents($handler->get_log_file_path('payplug-upc'));

        $this->assertStringContainsString('upc test debug message', $contents);
    }

    public function testErrorWritesToTheWooCommerceLogWithThePayplugUpcSource(): void
    {
        $handler = new \WC_Log_Handler_File();
        $logger = new WooCommerceLogger();

        $logger->error('upc test error message');

        $contents = file_get_contents($handler->get_log_file_path('payplug-upc'));

        $this->assertStringContainsString('upc test error message', $contents);
    }
}
