<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Model;

use Payplug\PayplugWoocommerce\Exception\LockUnavailableException;
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

    public function test_repair_adds_auto_increment_id_and_purges_orphan_rows(): void
    {
        $this->break_lock_table_without_id_column();
        $this->insert_raw_lock_row('pay_test_orphan');

        Lock::repair_lock_table();

        self::assertTrue(Lock::is_lock_table_healthy());
        self::assertSame(0, $this->count_lock_rows());
        self::assertCount(1, $this->logged_messages('info'));
    }

    public function test_repair_makes_existing_id_auto_increment_and_purges_orphan_rows(): void
    {
        $this->break_lock_table_without_auto_increment();
        $this->insert_raw_lock_row('pay_test_orphan');

        Lock::repair_lock_table();

        self::assertTrue(Lock::is_lock_table_healthy());
        self::assertSame(0, $this->count_lock_rows());
    }

    public function test_repair_keeps_the_rows_of_a_healthy_table(): void
    {
        $this->insert_raw_lock_row('pay_test_held');

        Lock::repair_lock_table();

        self::assertSame(1, $this->count_lock_rows('pay_test_held'));
        self::assertSame([], $this->logged_messages('info'));
    }

    public function test_repair_throws_with_the_db_error_when_the_table_cannot_be_fixed(): void
    {
        $this->break_lock_table_beyond_repair();

        try {
            Lock::repair_lock_table();
            self::fail('LockUnavailableException expected');
        } catch (LockUnavailableException $e) {
            self::assertStringContainsString('Incorrect table definition', $e->getMessage());
        }

        self::assertFalse(Lock::is_lock_table_healthy());
        self::assertTrue($this->repair_lock_is_free());
    }

    public function test_repair_throws_when_the_table_is_missing(): void
    {
        Lock::delete_lock_table();

        $this->expectException(LockUnavailableException::class);

        Lock::repair_lock_table();
    }

    public function test_repair_throws_without_touching_the_table_when_another_repair_holds_the_lock(): void
    {
        $this->break_lock_table_without_auto_increment();
        $other_connection = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        $other_connection->query("SELECT GET_LOCK('" . Lock::REPAIR_LOCK_NAME . "', 0)");

        try {
            ImmediateRepairLock::repair_lock_table();
            self::fail('LockUnavailableException expected');
        } catch (LockUnavailableException $e) {
            self::assertFalse(Lock::is_lock_table_healthy());
        } finally {
            $other_connection->close();
        }
    }

    private function repair_lock_is_free(): bool
    {
        global $wpdb;

        return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', Lock::REPAIR_LOCK_NAME));
    }
}

class ImmediateRepairLock extends Lock
{
    public const REPAIR_LOCK_TIMEOUT = 0;
}
