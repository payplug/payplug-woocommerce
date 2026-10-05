<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Exception\LockUnavailableException;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugIpnResponse;
use Payplug\PayplugWoocommerce\Gateway\PayplugResponse;
use Payplug\PayplugWoocommerce\Model\Lock;
use Payplug\PayplugWoocommerce\Tests\phpunit\Mocks\LockTableFixtures;
use PHPUnit\Framework\TestCase;

class PayplugIpnResponse_test extends TestCase
{
    use LockTableFixtures;

    private $gateway;

    private $ipn_response;

    private $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reset_lock_table();
        $this->capture_logs();

        $this->gateway = $this->getMockBuilder(PayplugGateway::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate_payment'])
            ->getMock();
        $this->gateway->response = $this->createMock(PayplugResponse::class);
        $this->ipn_response = new PayplugIpnResponse($this->gateway);

        $this->resource = (object) [
            'id' => 'pay_test_123',
            'object' => 'payment',
            'metadata' => ['order_id' => 42],
        ];
    }

    protected function tearDown(): void
    {
        remove_action('woocommerce_api_paypluggateway', [$this->ipn_response, 'handle_ipn_response']);
        $this->restore_logs();
        $this->reset_lock_table();
        parent::tearDown();
    }

    public function test_process_payment_resource_does_not_process_the_payment_when_the_lock_is_unavailable(): void
    {
        $this->break_lock_table_beyond_repair();
        $this->gateway->response->expects(self::never())->method('process_payment');

        $this->expectException(LockUnavailableException::class);

        $this->ipn_response->process_payment_resource($this->resource);
    }

    public function test_process_payment_resource_processes_the_first_notification_after_repairing_the_table(): void
    {
        $this->break_lock_table_without_id_column();
        $this->insert_raw_lock_row('pay_test_123');
        $this->gateway->response->expects(self::once())
            ->method('process_payment')
            ->with($this->resource, false, 'ipn');
        $this->gateway->expects(self::never())->method('validate_payment');

        $this->ipn_response->process_payment_resource($this->resource);

        self::assertSame(0, $this->count_lock_rows());
    }

    public function test_process_payment_resource_leaves_a_payment_being_processed_to_its_lock_holder(): void
    {
        Lock::insert_lock('pay_test_123');
        $this->gateway->response->expects(self::never())->method('process_payment');

        $this->ipn_response->process_payment_resource($this->resource);

        self::assertSame(2, $this->count_lock_rows('pay_test_123'));
    }

    public function test_handle_lock_unavailable_logs_an_error_and_answers_500_so_the_notification_is_replayed(): void
    {
        $status = null;
        $capture_status = static function ($header, $code) use (&$status) {
            $status = $code;

            return $header;
        };
        add_filter('status_header', $capture_status, 10, 2);

        $this->ipn_response->handle_lock_unavailable('pay_test_123', new LockUnavailableException('db down'));

        remove_filter('status_header', $capture_status, 10);
        self::assertSame(500, $status);
        self::assertSame(
            ['Unable to lock payment pay_test_123, notification not processed (will be replayed): db down'],
            $this->logged_messages('error')
        );
    }
}
