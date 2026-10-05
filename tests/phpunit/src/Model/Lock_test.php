<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Model;

use Payplug\PayplugWoocommerce\Model\Lock;
use Payplug\PayplugWoocommerce\Tests\phpunit\Mocks\LockTableFixtures;
use PHPUnit\Framework\TestCase;

class Lock_test extends TestCase
{
    use LockTableFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reset_lock_table();
        $this->capture_logs();
    }

    protected function tearDown(): void
    {
        $this->restore_logs();
        $this->reset_lock_table();
        parent::tearDown();
    }

    public function test_table_is_healthy_once_created(): void
    {
        self::assertTrue(Lock::is_lock_table_healthy());
    }

    public function test_table_is_unhealthy_without_id_column(): void
    {
        $this->break_lock_table_without_id_column();

        self::assertFalse(Lock::is_lock_table_healthy());
    }

    public function test_table_is_unhealthy_when_id_is_not_auto_increment(): void
    {
        $this->break_lock_table_without_auto_increment();

        self::assertFalse(Lock::is_lock_table_healthy());
    }

    public function test_table_is_unhealthy_when_missing(): void
    {
        Lock::delete_lock_table();

        self::assertFalse(Lock::is_lock_table_healthy());
    }
}
