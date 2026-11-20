<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\OperationOutcomeProcessor;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadataRecorder;
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
        delete_option('woocommerce_payplug_settings');
        $this->order->delete(true);
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

    private function snapshot(): array
    {
        $meta = $this->reload()->get_meta(UpcTransactionMetadataRecorder::META_KEY);

        return is_array($meta) ? $meta : [];
    }

    private function record_created(): void
    {
        update_option('woocommerce_payplug_settings', ['mode' => true]);
        (new UpcTransactionMetadataRecorder())->record_created($this->order, self::OPERATION_ID, true);
    }

    public function test_a_paid_outcome_marks_the_snapshot_paid(): void
    {
        $this->record_created();

        (new OperationOutcomeProcessor())->process($this->operation('0000', PaymentOutcome::PAID));

        $meta = $this->snapshot();
        self::assertTrue($meta['paid']);
        self::assertEqualsWithDelta(time(), $meta['paid_at'], 5);
        self::assertTrue($meta['3ds']);
        self::assertSame(self::OPERATION_ID, $meta['transaction_id']);
    }

    public function test_a_failed_outcome_leaves_the_snapshot_unpaid(): void
    {
        $this->record_created();

        (new OperationOutcomeProcessor())->process($this->operation('0002', PaymentOutcome::FAILED));

        self::assertFalse($this->snapshot()['paid']);
        self::assertSame(0, $this->snapshot()['paid_at']);
    }

    public function test_a_replayed_paid_outcome_does_not_change_paid_at(): void
    {
        $this->record_created();
        $processor = new OperationOutcomeProcessor();
        $processor->process($this->operation('0000', PaymentOutcome::PAID));
        $order = $this->reload();
        $meta = $this->snapshot();
        $meta['paid_at'] = 1600000000;
        $order->update_meta_data(UpcTransactionMetadataRecorder::META_KEY, $meta);
        $order->save();

        $result = $processor->process($this->operation('0000', PaymentOutcome::PAID));

        self::assertSame(OperationOutcomeProcessor::ALREADY_TREATED, $result);
        self::assertSame(1600000000, $this->snapshot()['paid_at']);
    }

    public function test_a_locked_operation_leaves_the_snapshot_unpaid(): void
    {
        $this->record_created();
        $lock_key = 'payplug_upc_treat_' . self::OPERATION_ID;
        $lock = new WooCommerceLock();
        $lock->acquire($lock_key, 30);

        (new OperationOutcomeProcessor())->process($this->operation('0000', PaymentOutcome::PAID));

        $lock->release($lock_key);
        self::assertFalse($this->snapshot()['paid']);
    }

    public function test_a_corrupt_snapshot_is_rebuilt_and_the_operation_still_marked_treated(): void
    {
        update_option('woocommerce_payplug_settings', ['mode' => true]);
        $this->order->update_meta_data(UpcTransactionMetadataRecorder::META_KEY, 'corrupt');
        $this->order->save();

        $result = (new OperationOutcomeProcessor())->process($this->operation('0000', PaymentOutcome::PAID));

        self::assertSame(OperationOutcomeProcessor::APPLIED, $result);
        self::assertSame('yes', $this->reload()->get_meta('_payplug_upc_treated'));
        self::assertTrue($this->snapshot()['paid']);
        self::assertSame(5000, $this->snapshot()['amount']);
    }

    public function test_a_recorder_failure_does_not_prevent_marking_the_operation_treated(): void
    {
        delete_option('woocommerce_payplug_settings');
        $failing_mode = static function (): void {
            throw new \RuntimeException('configuration unavailable');
        };
        add_filter('option_woocommerce_payplug_settings', $failing_mode);
        add_filter('default_option_woocommerce_payplug_settings', $failing_mode);

        try {
            $result = (new OperationOutcomeProcessor())->process($this->operation('0000', PaymentOutcome::PAID));
        } finally {
            remove_filter('option_woocommerce_payplug_settings', $failing_mode);
            remove_filter('default_option_woocommerce_payplug_settings', $failing_mode);
        }

        self::assertSame(OperationOutcomeProcessor::APPLIED, $result);
        self::assertSame('yes', $this->reload()->get_meta('_payplug_upc_treated'));
        self::assertSame([], $this->snapshot());
    }
}
