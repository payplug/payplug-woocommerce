<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\UnifiedApiPaymentServiceFactory;
use PayplugUnifiedCore\Services\UnifiedApiPaymentService;
use PHPUnit\Framework\TestCase;

class UnifiedApiPaymentServiceFactory_test extends TestCase
{
    public function testCreateReturnsAUnifiedApiPaymentServiceInstance(): void
    {
        $service = (new UnifiedApiPaymentServiceFactory())->create();

        $this->assertInstanceOf(UnifiedApiPaymentService::class, $service);
    }
}
