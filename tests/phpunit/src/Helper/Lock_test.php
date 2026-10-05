<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Helper;

use Payplug\PayplugWoocommerce\Exception\LockUnavailableException;
use Payplug\PayplugWoocommerce\Helper\Lock;
use Payplug\PayplugWoocommerce\Model\Lock as LockModel;
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

    public function test_handle_insert_returns_the_lock_id_when_the_payment_is_free(): void
    {
        $lock_id = Lock::handle_insert(true, 'pay_test_123');

        self::assertGreaterThan(0, $lock_id);
        self::assertSame(1, $this->count_lock_rows('pay_test_123'));
    }

    public function test_handle_insert_returns_false_when_another_request_holds_the_payment(): void
    {
        LockModel::insert_lock('pay_test_123');

        self::assertFalse(Lock::handle_insert(true, 'pay_test_123'));
        self::assertSame(['Payment pay_test_123 already being processed.'], $this->logged_messages('info'));
    }

    public function test_handle_insert_propagates_an_unavailable_lock(): void
    {
        $this->break_lock_table_beyond_repair();

        $this->expectException(LockUnavailableException::class);

        Lock::handle_insert(true, 'pay_test_123');
    }

    public function test_handle_insert_returns_the_saved_lock_id_for_a_waiting_request(): void
    {
        $lock_id = LockModel::insert_lock('pay_test_123');

        self::assertEquals($lock_id, Lock::handle_insert(false, 'pay_test_123'));
    }

    public function test_handle_insert_returns_false_for_a_waiting_request_without_saved_lock(): void
    {
        self::assertFalse(Lock::handle_insert(false, 'pay_test_none'));
    }
}
