<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\PaymentCaptureOutcomeApplier;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadataRecorder;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Output\PaymentOutput;
use PHPUnit\Framework\TestCase;

class PaymentCaptureOutcomeApplier_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        $this->order = wc_create_order();
        $this->order->set_status('pending');
        $this->order->save();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (UhfCard::check_table_exists()) {
            global $wpdb;
            $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        }
        delete_transient('payplug_upc_redirect_html_' . $this->order->get_id());
        delete_option('woocommerce_payplug_settings');
        $this->order->delete(true);
        parent::tearDown();
    }

    private function reload(): \WC_Order
    {
        return wc_get_order($this->order->get_id());
    }

    public function testRedirectHtmlStoresItAndReturnsTheIntermediateUrlWithoutTouchingOrderStatus(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_html_1","execCode":"0001"}', null, '<html>3ds form</html>', null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('success', $result['result']);
        $this->assertStringContainsString('wc-api=payplug_upc_3ds', $result['redirect']);
        $this->assertStringContainsString('order_key=' . $this->order->get_order_key(), $result['redirect']);
        $this->assertSame('<html>3ds form</html>', get_transient('payplug_upc_redirect_html_' . $this->order->get_id()));
        $this->assertSame('pending', $this->reload()->get_status());
    }

    public function testRedirectHtmlBindsTheOperationIdToTheOrderAsThreeDsPending(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_html_1","execCode":"0001"}', null, '<html>3ds form</html>', null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $order = $this->reload();

        $this->assertSame('op_html_1', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('0001', $order->get_meta('_payplug_upc_exec_code'));
        $this->assertSame(PaymentOutcome::THREE_DS_PENDING, $order->get_meta('_payplug_upc_outcome'));
    }

    public function testRedirectUrlIsReturnedDirectlyWithoutTouchingOrderStatus(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_url_1"}', 'https://bank.example.test/challenge', null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('success', $result['result']);
        $this->assertSame('https://bank.example.test/challenge', $result['redirect']);
        $this->assertSame('pending', $this->reload()->get_status());
    }

    public function testRedirectUrlWithoutAnExecCodeStillBindsTheOperationAsThreeDsPending(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_url_1"}', 'https://bank.example.test/challenge', null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $order = $this->reload();

        $this->assertSame('op_url_1', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('0001', $order->get_meta('_payplug_upc_exec_code'));
        $this->assertSame(PaymentOutcome::THREE_DS_PENDING, $order->get_meta('_payplug_upc_outcome'));
    }

    public function testDirectSuccessAppliesThePaidOutcomeAndRedirectsToOrderReceived(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_direct_1","execCode":"0000"}', null, null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('success', $result['result']);
        $this->assertSame($this->order->get_checkout_order_received_url(), $result['redirect']);
        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testDirectSuccessBindsTheOperationIdToTheOrder(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_direct_1","execCode":"0000"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $order = $this->reload();

        $this->assertSame('op_direct_1', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('0000', $order->get_meta('_payplug_upc_exec_code'));
        $this->assertSame(PaymentOutcome::PAID, $order->get_meta('_payplug_upc_outcome'));
    }

    public function testAnOperationIdsEntryIsBoundAsTheAlternateOperationIdAlongsideTheTopLevelId(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_direct_1","operationIds":["op_alt_1"],"execCode":"0000"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $order = $this->reload();

        $this->assertSame('op_direct_1', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('op_alt_1', $order->get_meta('_payplug_upc_operation_id_alt'));
    }

    public function testAnOperationIdsEntryEqualToTheTopLevelIdIsNotStoredAsAnAlternate(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_direct_1","operationIds":["op_direct_1"],"execCode":"0000"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $order = $this->reload();

        $this->assertSame('op_direct_1', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('', $order->get_meta('_payplug_upc_operation_id_alt'));
    }

    public function testAResponseWithoutOperationIdsBindsNoAlternateOperationId(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_direct_1","execCode":"0000"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('', $this->reload()->get_meta('_payplug_upc_operation_id_alt'));
    }

    public function testARetryWithNoAlternateOperationIdClearsThePreviousAttemptsStaleAlternate(): void
    {
        // First attempt (e.g. a failed 3DS challenge, or a timed-out request retried by the
        // shopper) bound an alt id.
        $first_output = new PaymentOutput(200, '{"id":"op_attempt_1","operationIds":["op_attempt_1_alt"],"execCode":"9999"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->order, $first_output);

        // Second attempt succeeds, but its response carries no operationIds at all - without
        // clearing first, the stale alt from attempt 1 would still be accepted by
        // Front\UpcWebhook for a notification that is no longer relevant to this order's current
        // operation.
        $second_output = new PaymentOutput(200, '{"id":"op_attempt_2","execCode":"0000"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->reload(), $second_output);

        $order = $this->reload();
        $this->assertSame('op_attempt_2', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('', $order->get_meta('_payplug_upc_operation_id_alt'));
    }

    public function testDirectFailureAppliesTheFailedOutcomeAndReturnsAFailureResult(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_direct_2","execCode":"9999"}', null, null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('failure', $result['result']);
        $this->assertSame('failed', $this->reload()->get_status());
    }

    public function testAResponseWithoutATopLevelIdIsBoundToItsFirstOperationId(): void
    {
        // Shape observed on staging (2026-10-05): no top-level "id", only operationIds.
        $output = new PaymentOutput(200, '{"execCode":"0000","operationIds":["op_only_1"]}', null, null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $order = $this->reload();
        $this->assertSame('success', $result['result']);
        $this->assertSame('op_only_1', $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('', $order->get_meta('_payplug_upc_operation_id_alt'));
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));
        $this->assertSame('processing', $order->get_status());
    }

    public function testAResponseWithoutAnOperationIdPersistsNothingAndStillCompletesTheFlow(): void
    {
        $output = new PaymentOutput(200, '{"execCode":"0000"}', null, null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('success', $result['result']);
        $this->assertSame('processing', $this->reload()->get_status());
        $this->assertSame('', $this->reload()->get_meta('_payplug_upc_operation_id'));
    }

    public function testDirectSuccessDoesNotApplyTheOutcomeWhenAWebhookAlreadyHoldsTheLockForThisOperation(): void
    {
        $lock_key = 'payplug_upc_treat_op_direct_1';
        $lock = new WooCommerceLock();
        $lock->acquire($lock_key, 30);

        $output = new PaymentOutput(200, '{"id":"op_direct_1","execCode":"0000"}', null, null, null);
        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        // persist_operation() still binds the operation id regardless (it doesn't take the lock),
        // but apply_terminal_outcome() must leave the status alone since it lost the lock race.
        $this->assertSame('success', $result['result']);
        $this->assertSame('op_direct_1', $this->reload()->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('pending', $this->reload()->get_status());

        $lock->release($lock_key);
    }

    public function testDirectSuccessDoesNothingWhenTheOperationWasAlreadyTreatedByTheWebhook(): void
    {
        // Simulates the webhook having already applied and released this exact operation between
        // the Unified API's synchronous response and this method running - same 'on-hold'
        // rationale as PaymentReconciler_test.php's own equivalent test: not one of
        // WooCommerceOrderStateMutator's terminal statuses, so only the isTreated() guard (not
        // that other, independent guard) can be what keeps this from moving to 'processing'.
        $this->order->update_status('on-hold');
        $this->order->update_meta_data('_payplug_upc_operation_id', 'op_direct_1');
        $this->order->update_meta_data('_payplug_upc_treated', 'yes');
        $this->order->save();

        $output = new PaymentOutput(200, '{"id":"op_direct_1","execCode":"0000"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->reload(), $output);

        $this->assertSame('on-hold', $this->reload()->get_status());
    }

    public function testASecondAttemptWithANewOperationIdStillAppliesAfterTheFirstAttemptWasAlreadyTreated(): void
    {
        // Regression test for the fix that clears _payplug_upc_treated when the primary operation
        // id changes: without it, this second (successful) attempt would silently inherit the
        // first (failed) attempt's stale 'treated' marker via isTreated() - keyed by whichever
        // operation id currently matches the order, not by the specific operation that was
        // actually marked - and apply_terminal_outcome() would skip applying PAID entirely.
        $first_output = new PaymentOutput(200, '{"id":"op_attempt_1","execCode":"9999"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->order, $first_output);

        $this->assertSame('failed', $this->reload()->get_status());

        // A FAILED outcome is no longer marked treated by itself - set the stale marker by hand
        // to keep exercising the "new operation id clears it" fix.
        $order = $this->reload();
        $order->update_meta_data('_payplug_upc_treated', 'yes');
        $order->save();

        $second_output = new PaymentOutput(200, '{"id":"op_attempt_2","execCode":"0000"}', null, null, null);
        $result = (new PaymentCaptureOutcomeApplier())->apply($this->reload(), $second_output);

        $this->assertSame('success', $result['result']);
        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testARetryWithANewOperationIdRemembersTheReplacedOperationIds(): void
    {
        $first_output = new PaymentOutput(200, '{"id":"op_attempt_1","operationIds":["op_attempt_1_alt"],"execCode":"9999"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->order, $first_output);

        $this->assertSame('', $this->reload()->get_meta('_payplug_upc_superseded_operation_ids'));

        $second_output = new PaymentOutput(200, '{"id":"op_attempt_2","execCode":"0000"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->reload(), $second_output);

        $this->assertSame(['op_attempt_1', 'op_attempt_1_alt'], $this->reload()->get_meta('_payplug_upc_superseded_operation_ids'));
    }

    public function testDirectFailureIsNotMarkedTreatedSoALaterPaidNotificationCanStillApply(): void
    {
        // An unmapped "Acceptation" execCode (here 0002, WAITING_PROVIDER) falls into FAILED.
        $output = new PaymentOutput(200, '{"id":"op_failed_1","execCode":"0002"}', null, null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('failure', $result['result']);
        $this->assertSame('failed', $this->reload()->get_status());
        $this->assertSame('', $this->reload()->get_meta('_payplug_upc_treated'));
        wc_clear_notices();
    }

    public function testEveryPersistedOperationRecordsWhenTheAttemptWasCreated(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_html_1","execCode":"0001"}', null, '<html>3ds form</html>', null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertEqualsWithDelta(time(), (int) $this->reload()->get_meta('_payplug_upc_created_at'), 5);
    }

    public function testDirectSuccessWithSaveCardPersistsTheAliasUsingFallbackData(): void
    {
        $this->order->set_customer_id(1);
        $this->order->save();

        $output = new PaymentOutput(200, '{"id":"op_alias_1","execCode":"0000"}', null, null, 'alias_new_1');

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output, true, [
            'brand' => 'VISA',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2099,
        ]);

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $card = UhfCard::find_by_alias('alias_new_1', $mode);

        $this->assertNotNull($card);
        $this->assertSame('VISA', $card->brand);
    }

    public function testDirectSuccessWithoutSaveCardDoesNotPersistAnAlias(): void
    {
        $this->order->set_customer_id(1);
        $this->order->save();

        $output = new PaymentOutput(200, '{"id":"op_alias_2","execCode":"0000"}', null, null, 'alias_new_2');

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output, false, []);

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $this->assertNull(UhfCard::find_by_alias('alias_new_2', $mode));
    }

    public function testDirectFailureNeverPersistsAnAliasEvenIfOneWasReturned(): void
    {
        $this->order->set_customer_id(1);
        $this->order->save();

        $output = new PaymentOutput(200, '{"id":"op_alias_3","execCode":"9999"}', null, null, 'alias_new_3');

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output, true, [
            'brand' => 'VISA',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2099,
        ]);

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $this->assertNull(UhfCard::find_by_alias('alias_new_3', $mode));
    }

    public function testSaveCardIntentIsPersistedAsOrderMetaAndClearedOnARetryWithoutIt(): void
    {
        $output = new PaymentOutput(200, '{"id":"op_intent_1","execCode":"0000"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output, true);

        $this->assertSame('1', $this->reload()->get_meta('_payplug_uhf_save_card'));

        $second_output = new PaymentOutput(200, '{"id":"op_intent_2","execCode":"0000"}', null, null, null);
        (new PaymentCaptureOutcomeApplier())->apply($this->reload(), $second_output, false);

        $this->assertSame('', $this->reload()->get_meta('_payplug_uhf_save_card'));
    }

    private function prepare_snapshot_order(): void
    {
        update_option('woocommerce_payplug_settings', ['mode' => true]);
        $this->order->set_currency('USD');
        $this->order->set_total(50.00);
        $this->order->save();
    }

    private function snapshot(): array
    {
        $meta = $this->reload()->get_meta(UpcTransactionMetadataRecorder::META_KEY);

        return is_array($meta) ? $meta : [];
    }

    public function test_a_three_ds_redirect_response_records_an_unpaid_three_ds_snapshot(): void
    {
        $this->prepare_snapshot_order();
        $output = new PaymentOutput(200, '{"id":"op_html_1","execCode":"0001"}', null, '<html>3ds form</html>', null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $meta = $this->snapshot();
        self::assertSame('op_html_1', $meta['transaction_id']);
        self::assertFalse($meta['paid']);
        self::assertTrue($meta['3ds']);
        self::assertSame(5000, $meta['amount']);
        self::assertTrue($meta['live']);
    }

    public function test_a_three_ds_redirect_url_response_records_a_three_ds_snapshot(): void
    {
        $this->prepare_snapshot_order();
        $output = new PaymentOutput(200, '{"id":"op_url_1"}', 'https://bank.example.test/challenge', null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        self::assertTrue($this->snapshot()['3ds']);
        self::assertFalse($this->snapshot()['paid']);
    }

    public function test_a_direct_paid_response_records_a_paid_non_three_ds_snapshot_with_the_operation_id(): void
    {
        $this->prepare_snapshot_order();
        $output = new PaymentOutput(200, '{"id":"op_direct_1","execCode":"0000"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $meta = $this->snapshot();
        self::assertSame('op_direct_1', $meta['transaction_id']);
        self::assertTrue($meta['paid']);
        self::assertEqualsWithDelta(time(), $meta['paid_at'], 5);
        self::assertFalse($meta['3ds']);
        self::assertSame(5000, $meta['amount']);
    }

    public function test_a_direct_failed_response_records_an_unpaid_snapshot(): void
    {
        $this->prepare_snapshot_order();
        $output = new PaymentOutput(200, '{"id":"op_direct_2","execCode":"9999"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        self::assertSame('op_direct_2', $this->snapshot()['transaction_id']);
        self::assertFalse($this->snapshot()['paid']);
        wc_clear_notices();
    }

    public function test_a_retried_operation_id_resets_the_snapshot(): void
    {
        $this->prepare_snapshot_order();
        (new PaymentCaptureOutcomeApplier())->apply($this->order, new PaymentOutput(200, '{"id":"op_attempt_1","execCode":"0001"}', null, '<html>3ds form</html>', null));
        (new UpcTransactionMetadataRecorder())->record_card($this->reload(), ['paymentMethod' => ['card' => ['network' => 'visa', 'code6x4' => '4242']]]);

        (new PaymentCaptureOutcomeApplier())->apply($this->reload(), new PaymentOutput(200, '{"id":"op_attempt_2","execCode":"0000"}', null, null, null));

        $meta = $this->snapshot();
        self::assertSame('op_attempt_2', $meta['transaction_id']);
        self::assertFalse($meta['3ds']);
        self::assertTrue($meta['paid']);
        self::assertSame('', $meta['card_brand']);
        self::assertSame('', $meta['card_last4']);
    }

    public function test_a_response_without_operation_id_records_nothing(): void
    {
        $this->prepare_snapshot_order();
        $output = new PaymentOutput(200, '{"execCode":"0000"}', null, null, null);

        (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        self::assertSame('', $this->reload()->get_meta(UpcTransactionMetadataRecorder::META_KEY));
    }
}
