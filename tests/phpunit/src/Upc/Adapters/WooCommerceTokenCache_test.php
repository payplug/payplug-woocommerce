<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceTokenCache;
use PHPUnit\Framework\TestCase;

class WooCommerceTokenCache_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_transient('upc_token_test-key');
        parent::tearDown();
    }

    public function testGetReturnsNullWhenNotSet(): void
    {
        $cache = new WooCommerceTokenCache();

        $this->assertNull($cache->get('test-key'));
    }

    public function testSetThenGetReturnsTheStoredValue(): void
    {
        $cache = new WooCommerceTokenCache();
        $cache->set('test-key', 'token-value-123', 60);

        $this->assertSame('token-value-123', $cache->get('test-key'));
    }

    public function testDeleteRemovesTheStoredValue(): void
    {
        $cache = new WooCommerceTokenCache();
        $cache->set('test-key', 'token-value-123', 60);
        $cache->delete('test-key');

        $this->assertNull($cache->get('test-key'));
    }
}
