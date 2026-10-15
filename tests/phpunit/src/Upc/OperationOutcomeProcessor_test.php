<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\OperationOutcomeProcessor;
use PayplugUnifiedCore\DataValues\OperationData;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PHPUnit\Framework\TestCase;

class OperationOutcomeProcessor_test extends TestCase
{
    private const OPERATION_ID = 'op_processor_1';

    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        $this->order = wc_create_order();
        $this->order->add_product(\WC_Helper_Product::create_simple_product(), 1);
        $this->order->set_total(50.00);
        $this->order->set_status('pending');
        $this->order->update_meta_data('_payplug_upc_operation_id', self::OPERATION_ID);
        $this->order->save();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        wp_delete_post($this->order->get_id(), true);
        parent::tearDown();
    }

    private function reload(): \WC_Order
    {
        return wc_get_order($this->order->get_id());
    }

    private function operation(string $exec_code, string $outcome): OperationData
    {
        return new OperationData(self::OPERATION_ID, $exec_code, $outcome, 5000, (string) $this->order->get_id());
    }

    public function testAPaidOutcomeIsAppliedSavedAndMarkedTreated(): void
    {
        $on_paid_calls = 0;

        $result = (new OperationOutcomeProcessor())->process(
            $this->operation('0000', PaymentOutcome::PAID),
            static function () use (&$on_paid_calls): void {
                ++$on_paid_calls;
            }
        );

        $order = $this->reload();
        $this->assertSame(OperationOutcomeProcessor::APPLIED, $result);
        $this->assertSame('processing', $order->get_status());
        $this->assertSame(PaymentOutcome::PAID, $order->get_meta('_payplug_upc_outcome'));
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));
        $this->assertSame(1, $on_paid_calls);
    }

    public function testAFailedOutcomeIsAppliedButNeverMarkedTreated(): void
    {
        $on_paid_calls = 0;

        $result = (new OperationOutcomeProcessor())->process(
            $this->operation('0002', PaymentOutcome::FAILED),
            static function () use (&$on_paid_calls): void {
                ++$on_paid_calls;
            }
        );

        $order = $this->reload();
        $this->assertSame(OperationOutcomeProcessor::APPLIED, $result);
        $this->assertSame('failed', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
        $this->assertSame(0, $on_paid_calls);
    }

    public function testALaterPaidOutcomeStillCompletesAnOrderAFailedOutcomeWasAppliedTo(): void
    {
        $processor = new OperationOutcomeProcessor();

        $processor->process($this->operation('0002', PaymentOutcome::FAILED));
        $result = $processor->process($this->operation('0000', PaymentOutcome::PAID));

        $this->assertSame(OperationOutcomeProcessor::APPLIED, $result);
        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testAnAlreadyTreatedOperationIsNotAppliedAgain(): void
    {
        // 'on-hold' is not a terminal status, so only the isTreated() guard can keep this order
        // from moving to 'processing'.
        $this->order->update_status('on-hold');
        $this->order->update_meta_data('_payplug_upc_treated', 'yes');
        $this->order->save();

        $result = (new OperationOutcomeProcessor())->process($this->operation('0000', PaymentOutcome::PAID));

        $this->assertSame(OperationOutcomeProcessor::ALREADY_TREATED, $result);
        $this->assertSame('on-hold', $this->reload()->get_status());
    }

    public function testALockedOperationIsLeftToWhoeverHoldsTheLock(): void
    {
        $lock_key = 'payplug_upc_treat_' . self::OPERATION_ID;
        $lock = new WooCommerceLock();
        $lock->acquire($lock_key, 30);

        $result = (new OperationOutcomeProcessor())->process($this->operation('0000', PaymentOutcome::PAID));

        $lock->release($lock_key);

        $this->assertSame(OperationOutcomeProcessor::LOCKED, $result);
        $this->assertSame('pending', $this->reload()->get_status());
        $this->assertSame('', $this->reload()->get_meta('_payplug_upc_treated'));
    }
}
