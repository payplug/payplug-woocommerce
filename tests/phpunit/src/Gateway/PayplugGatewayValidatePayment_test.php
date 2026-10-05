<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugApi;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugResponse;
use Payplug\PayplugWoocommerce\Tests\phpunit\Mocks\LockTableFixtures;
use PHPUnit\Framework\TestCase;

class PayplugGatewayValidatePayment_test extends TestCase
{
    use LockTableFixtures;

    private $gateway;

    private $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reset_lock_table();
        $this->capture_logs();

        $this->gateway = $this->getMockBuilder(PayplugGateway::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->gateway->id = 'payplug';
        $this->gateway->payplug_api = $this->createMock(PayplugApi::class);
        $this->gateway->response = $this->createMock(PayplugResponse::class);

        // WooCommerce is activated after WordPress init in the test bootstrap, so WC::init() has not built the factory.
        if (null === WC()->order_factory) {
            WC()->order_factory = new \WC_Order_Factory();
        }
        $this->order = wc_create_order();
        $this->order->set_payment_method('payplug');
        $this->order->set_transaction_id('pay_test_123');
        $this->order->save();
    }

    protected function tearDown(): void
    {
        $this->order->delete(true);
        $this->restore_logs();
        $this->reset_lock_table();
        parent::tearDown();
    }

    public function test_validate_payment_logs_and_returns_when_the_lock_is_unavailable(): void
    {
        $this->break_lock_table_beyond_repair();
        $this->gateway->payplug_api->expects(self::never())->method('payment_retrieve');
        $this->gateway->response->expects(self::never())->method('process_payment');

        $this->gateway->validate_payment($this->order->get_id(), true, true);

        $errors = $this->logged_messages('error');
        self::assertCount(1, $errors);
        self::assertStringStartsWith(sprintf('Order #%s : Unable to lock payment pay_test_123: ', $this->order->get_id()), $errors[0]);
    }

    public function test_validate_payment_processes_the_payment_and_releases_the_lock(): void
    {
        $payment = (object) ['id' => 'pay_test_123'];
        $this->gateway->payplug_api->expects(self::once())
            ->method('payment_retrieve')
            ->with('pay_test_123')
            ->willReturn($payment);
        $this->gateway->response->expects(self::once())->method('process_payment')->with($payment);

        $this->gateway->validate_payment($this->order->get_id(), true, true);

        self::assertSame(0, $this->count_lock_rows());
    }
}
