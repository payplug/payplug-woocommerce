<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use PHPUnit\Framework\TestCase;

class WooCommerceLock_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option('payplug_upc_lock_test-key');
        parent::tearDown();
    }

    public function testAcquireSucceedsWhenNotHeld(): void
    {
        $lock = new WooCommerceLock();

        $this->assertTrue($lock->acquire('test-key', 30));
    }

    public function testAcquireFailsWhenAlreadyHeld(): void
    {
        $lock = new WooCommerceLock();
        $lock->acquire('test-key', 30);

        $this->assertFalse($lock->acquire('test-key', 30));
    }

    public function testReleaseAllowsReacquiring(): void
    {
        $lock = new WooCommerceLock();
        $lock->acquire('test-key', 30);
        $lock->release('test-key');

        $this->assertTrue($lock->acquire('test-key', 30));
    }

    public function testAnExpiredLockCanBeTakenOverByAnotherAcquirer(): void
    {
        $lock = new WooCommerceLock();
        // A 0-second TTL means the lock's own expiry timestamp is already in the past by the
        // time a second acquire() checks it - simulating a holder that crashed before release().
        $lock->acquire('test-key', 0);

        $this->assertTrue($lock->acquire('test-key', 30));
    }
}
