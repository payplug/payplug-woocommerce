<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadataRecorder;
use PHPUnit\Framework\TestCase;

/**
 * Replaces the real wait between lock attempts by an optional callback, so a test does not sleep.
 */
class UpcTransactionMetadataRecorderWithoutWait extends UpcTransactionMetadataRecorder
{
    /** @var callable|null */
    private $on_wait;

    public function __construct(?callable $on_wait = null)
    {
        $this->on_wait = $on_wait;
    }

    protected function wait_for_lock(): void
    {
        if (null !== $this->on_wait) {
            ($this->on_wait)();
        }
    }
}

class UpcTransactionMetadataRecorder_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    /** @var array<int, array{0: string, 1: string}> */
    private $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_payplug_settings', ['mode' => true]);

        $this->order = wc_create_order();
        $this->order->set_currency('USD');
        $this->order->set_total(50.00);
        $this->order->update_meta_data('_payplug_upc_operation_id', 'pay_recorder_1');
        $this->order->update_meta_data('_payplug_upc_amount', 5000);
        $this->order->save();

        $this->logged = [];
        add_filter('woocommerce_logger_log_message', [$this, 'capture_log'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('woocommerce_logger_log_message', [$this, 'capture_log'], 10);
        delete_option('woocommerce_payplug_settings');
        $this->order->delete(true);
        parent::tearDown();
    }

    public function capture_log($message, $level, $context)
    {
        if ('payplug-upc' === ($context['source'] ?? '')) {
            $this->logged[] = [$level, $message];
        }

        return $message;
    }

    private function snapshot(): array
    {
        $meta = wc_get_order($this->order->get_id())->get_meta(UpcTransactionMetadataRecorder::META_KEY);

        return is_array($meta) ? $meta : [];
    }

    public function test_record_created_writes_an_unpaid_snapshot_with_mode_and_three_ds(): void
    {
        (new UpcTransactionMetadataRecorder())->record_created($this->order, 'pay_recorder_1', true);

        $meta = $this->snapshot();
        self::assertSame('pay_recorder_1', $meta['transaction_id']);
        self::assertFalse($meta['paid']);
        self::assertFalse($meta['refunded']);
        self::assertSame(5000, $meta['amount']);
        self::assertSame(0, $meta['amount_refunded']);
        self::assertTrue($meta['3ds']);
        self::assertTrue($meta['live']);
        self::assertSame(0, $meta['paid_at']);
    }

    public function test_record_created_in_test_mode_records_live_false(): void
    {
        update_option('woocommerce_payplug_settings', ['mode' => false]);

        (new UpcTransactionMetadataRecorder())->record_created($this->order, 'pay_recorder_1', false);

        self::assertFalse($this->snapshot()['live']);
        self::assertFalse($this->snapshot()['3ds']);
    }

    public function test_record_created_uses_the_minor_units_of_the_order_currency(): void
    {
        $this->order->set_currency('JPY');
        $this->order->set_total(1000);
        $this->order->save();

        (new UpcTransactionMetadataRecorder())->record_created($this->order, 'pay_recorder_1', false);

        self::assertSame(1000, $this->snapshot()['amount']);
    }

    public function test_record_created_with_a_new_operation_id_replaces_the_previous_snapshot(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', true);
        $recorder->record_paid($this->order);
        $recorder->record_card($this->order, ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);

        $recorder->record_created($this->order, 'pay_recorder_2', false);

        $meta = $this->snapshot();
        self::assertSame('pay_recorder_2', $meta['transaction_id']);
        self::assertFalse($meta['paid']);
        self::assertFalse($meta['3ds']);
        self::assertSame('', $meta['card_brand']);
        self::assertSame('', $meta['card_last4']);
    }

    public function test_record_created_with_the_same_operation_id_keeps_the_paid_state_and_card(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', true);
        $recorder->record_paid($this->order);
        $recorder->record_card($this->order, ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);
        $before = $this->snapshot();

        $recorder->record_created($this->order, 'pay_recorder_1', true);

        $meta = $this->snapshot();
        self::assertSame($before, $meta);
        self::assertTrue($meta['paid']);
        self::assertGreaterThan(0, $meta['paid_at']);
        self::assertSame('VISA', $meta['card_brand']);
        self::assertSame('4242', $meta['card_last4']);
    }

    public function test_record_created_with_the_same_operation_id_keeps_a_snapshot_written_by_the_webhook_first(): void
    {
        $stale = wc_get_order($this->order->get_id());
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_paid(wc_get_order($this->order->get_id()));
        $recorder->record_card(wc_get_order($this->order->get_id()), ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);

        $recorder->record_created($stale, 'pay_recorder_1', true);

        $meta = $this->snapshot();
        self::assertSame('pay_recorder_1', $meta['transaction_id']);
        self::assertTrue($meta['paid']);
        self::assertSame('VISA', $meta['card_brand']);
    }

    public function test_record_paid_marks_paid_and_sets_paid_at(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', true);

        $recorder->record_paid($this->order);

        $meta = $this->snapshot();
        self::assertTrue($meta['paid']);
        self::assertEqualsWithDelta(time(), $meta['paid_at'], 5);
        self::assertTrue($meta['3ds']);
    }

    public function test_record_paid_builds_the_snapshot_from_upc_meta_when_none_exists(): void
    {
        (new UpcTransactionMetadataRecorder())->record_paid($this->order);

        $meta = $this->snapshot();
        self::assertSame('pay_recorder_1', $meta['transaction_id']);
        self::assertSame(5000, $meta['amount']);
        self::assertTrue($meta['paid']);
        self::assertTrue($meta['live']);
    }

    public function test_record_paid_rebuilds_a_snapshot_stored_as_a_non_array(): void
    {
        $this->order->update_meta_data(UpcTransactionMetadataRecorder::META_KEY, 'corrupt');
        $this->order->save();

        (new UpcTransactionMetadataRecorder())->record_paid($this->order);

        $meta = $this->snapshot();
        self::assertSame('pay_recorder_1', $meta['transaction_id']);
        self::assertTrue($meta['paid']);
    }

    public function test_record_card_reads_the_webhook_shape_and_never_blanks_existing_card_data(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', true);

        $recorder->record_card($this->order, [
            'paymentMethod' => [
                'card' => ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['validityDate' => '2030-07'],
            ],
        ]);
        $recorder->record_card($this->order, ['execCode' => '0000']);

        $meta = $this->snapshot();
        self::assertSame('VISA', $meta['card_brand']);
        self::assertSame('4242', $meta['card_last4']);
        self::assertSame(7, $meta['card_exp_month']);
        self::assertSame(2030, $meta['card_exp_year']);
        self::assertSame('', $meta['card_country']);
        self::assertFalse($meta['paid']);
    }

    public function test_record_card_keeps_the_paid_state_written_through_another_order_instance(): void
    {
        $stale = wc_get_order($this->order->get_id());
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', true);
        $recorder->record_paid(wc_get_order($this->order->get_id()));

        $recorder->record_card($stale, ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);

        $meta = $this->snapshot();
        self::assertTrue($meta['paid']);
        self::assertTrue($meta['3ds']);
        self::assertSame('VISA', $meta['card_brand']);
    }

    public function test_record_refund_partial_then_total(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', false);
        $recorder->record_paid($this->order);

        $recorder->record_refund($this->order, 1000);

        self::assertFalse($this->snapshot()['refunded']);
        self::assertSame(1000, $this->snapshot()['amount_refunded']);

        $recorder->record_refund($this->order, 4000);

        self::assertTrue($this->snapshot()['refunded']);
        self::assertSame(5000, $this->snapshot()['amount_refunded']);
        self::assertTrue($this->snapshot()['paid']);
    }

    public function test_record_refund_without_amount_refunds_everything(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', false);

        $recorder->record_refund($this->order, null);

        self::assertTrue($this->snapshot()['refunded']);
        self::assertSame(5000, $this->snapshot()['amount_refunded']);
    }

    public function test_a_failure_is_swallowed_and_logged(): void
    {
        $empty_currency = static fn (): string => '';
        add_filter('woocommerce_order_get_currency', $empty_currency);

        (new UpcTransactionMetadataRecorder())->record_created($this->order, 'pay_recorder_1', false);

        remove_filter('woocommerce_order_get_currency', $empty_currency);
        self::assertSame([], $this->snapshot());
        self::assertContains(
            ['error', sprintf('UPC payment details could not be recorded for order #%s: currency must not be empty.', $this->order->get_id())],
            $this->logged
        );
    }

    public function test_a_write_is_skipped_and_logged_while_another_writer_holds_the_order_lock(): void
    {
        $lock = new WooCommerceLock();
        $lock->acquire($this->lock_key(), 30);

        (new UpcTransactionMetadataRecorderWithoutWait())->record_paid($this->order);

        self::assertSame([], $this->snapshot());
        self::assertContains(
            ['error', sprintf('UPC payment details could not be recorded for order #%s: another write is in progress.', $this->order->get_id())],
            $this->logged
        );
        // The lock belongs to the other writer: a skipped write must not release it.
        self::assertFalse($lock->acquire($this->lock_key(), 30));
        $lock->release($this->lock_key());
    }

    public function test_a_write_waits_for_the_order_lock_and_records_once_it_is_released(): void
    {
        $lock = new WooCommerceLock();
        $lock->acquire($this->lock_key(), 30);
        $recorder = new UpcTransactionMetadataRecorderWithoutWait(function () use ($lock): void {
            $lock->release($this->lock_key());
        });

        $recorder->record_paid($this->order);

        self::assertTrue($this->snapshot()['paid']);
        self::assertSame([], $this->logged);
    }

    public function test_the_order_lock_is_released_after_a_write_and_after_a_failure(): void
    {
        $lock = new WooCommerceLock();
        $recorder = new UpcTransactionMetadataRecorder();

        $recorder->record_paid($this->order);
        self::assertTrue($lock->acquire($this->lock_key(), 30));
        $lock->release($this->lock_key());

        $empty_currency = static fn (): string => '';
        add_filter('woocommerce_order_get_currency', $empty_currency);
        $recorder->record_created($this->order, 'pay_recorder_2', false);
        remove_filter('woocommerce_order_get_currency', $empty_currency);

        self::assertTrue($lock->acquire($this->lock_key(), 30));
        $lock->release($this->lock_key());
    }

    public function test_card_and_payment_recorded_one_after_the_other_from_stale_instances_keep_both(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', false);
        $stale_for_card = wc_get_order($this->order->get_id());
        $stale_for_paid = wc_get_order($this->order->get_id());

        $recorder->record_card($stale_for_card, ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);
        $recorder->record_paid($stale_for_paid);

        $meta = $this->snapshot();
        self::assertTrue($meta['paid']);
        self::assertSame('VISA', $meta['card_brand']);
        self::assertSame('4242', $meta['card_last4']);
    }

    private function lock_key(): string
    {
        return 'payplug_upc_metadata_' . $this->order->get_id();
    }

    public function test_retail_meta_is_never_written(): void
    {
        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($this->order, 'pay_recorder_1', true);
        $recorder->record_paid($this->order);
        $recorder->record_card($this->order, ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);
        $recorder->record_refund($this->order, 1000);

        self::assertSame('', wc_get_order($this->order->get_id())->get_meta('_payplug_metadata'));
    }
}
