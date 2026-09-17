<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommercePaymentRepository;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Exceptions\PaymentNotFoundException;
use PHPUnit\Framework\TestCase;

class WooCommercePaymentRepository_test extends TestCase
{
    /** @var int */
    private $order_id;

    protected function setUp(): void
    {
        $order = wc_create_order();
        $this->order_id = $order->get_id();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->order_id, true);
        parent::tearDown();
    }

    private function anOperationData(): OperationData
    {
        return new OperationData('op_123', '0000', PaymentOutcome::PAID, 5000, (string) $this->order_id);
    }

    public function testSaveThenGetByOrderIdReturnsTheStoredOperation(): void
    {
        $repository = new WooCommercePaymentRepository();
        $repository->save($this->anOperationData());

        $operation = $repository->getByOrderId((string) $this->order_id);

        $this->assertSame('op_123', $operation->operationId);
        $this->assertSame('0000', $operation->execCode);
        $this->assertSame(PaymentOutcome::PAID, $operation->outcome);
        $this->assertSame(5000, $operation->amount);
        $this->assertSame((string) $this->order_id, $operation->orderId);
    }

    public function testSaveThenGetByOperationIdReturnsTheStoredOperation(): void
    {
        $repository = new WooCommercePaymentRepository();
        $repository->save($this->anOperationData());

        $operation = $repository->getByOperationId('op_123');

        $this->assertSame((string) $this->order_id, $operation->orderId);
    }

    public function testGetByOrderIdThrowsWhenNoOperationIsStored(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        (new WooCommercePaymentRepository())->getByOrderId((string) $this->order_id);
    }

    public function testGetByOperationIdThrowsWhenNoOperationIsStored(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        (new WooCommercePaymentRepository())->getByOperationId('op_never_saved');
    }

    public function testMarkTreatedThrowsWhenNoOperationIsStored(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        (new WooCommercePaymentRepository())->markTreated('op_never_saved');
    }

    public function testIsTreatedIsFalseUntilMarkTreatedIsCalled(): void
    {
        $repository = new WooCommercePaymentRepository();
        $repository->save($this->anOperationData());

        $this->assertFalse($repository->isTreated('op_123'));

        $repository->markTreated('op_123');

        $this->assertTrue($repository->isTreated('op_123'));
    }

    public function testIsTreatedIsFalseForAnUnknownOperationId(): void
    {
        $this->assertFalse((new WooCommercePaymentRepository())->isTreated('op_never_saved'));
    }
}
