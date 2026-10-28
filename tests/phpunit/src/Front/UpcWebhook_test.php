<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\UpcWebhook;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadataRecorder;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;

class UpcWebhook_test extends \WP_Ajax_UnitTestCase
{
    private const OPERATION_ID = 'op_webhook_123';

    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        $this->order = wc_create_order();
        $this->order->set_total(50.00);
        $this->order->set_status('pending');
        // Bound by PaymentCaptureOutcomeApplier at payment-creation time; the webhook only
        // accepts a notification for an operation the order is already bound to.
        $this->order->update_meta_data('_payplug_upc_operation_id', self::OPERATION_ID);
        $this->order->save();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (UhfCard::check_table_exists()) {
            global $wpdb;
            $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        }
        wp_delete_post($this->order->get_id(), true);
        parent::tearDown();
    }

    private function notificationBody(string $execCode): string
    {
        return json_encode([
            'id' => self::OPERATION_ID,
            'execCode' => $execCode,
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);
    }

    public function testAFinalNotificationUpdatesTheOrderStatusAndMarksTheOperationTreated(): void
    {
        $controller = new UpcWebhook();

        ob_start();
        $status = $controller->receive_notification($this->notificationBody('0000'));
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(200, $status);
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));
    }

    public function testALatePaidNotificationStillCompletesAnOrderAFailedOutcomeWasAppliedTo(): void
    {
        $controller = new UpcWebhook();

        // e.g. an unmapped "Acceptation" execCode (0002, WAITING_PROVIDER) mapped to FAILED first.
        ob_start();
        $first_status = $controller->receive_notification($this->notificationBody('0002'));
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());
        $this->assertSame(200, $first_status);
        $this->assertSame('failed', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));

        ob_start();
        $second_status = $controller->receive_notification($this->notificationBody('0000'));
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());
        $this->assertSame(200, $second_status);
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));
    }

    public function testAThreeDsPendingNotificationDoesNotChangeTheOrderStatusOrMarkTreated(): void
    {
        $controller = new UpcWebhook();

        ob_start();
        $status = $controller->receive_notification($this->notificationBody('0001'));
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(200, $status);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
    }

    public function testANotificationWithAMismatchedAmountDoesNotChangeTheOrderStatus(): void
    {
        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => self::OPERATION_ID,
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()) + 100,
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(400, $status);
        $this->assertSame('pending', $order->get_status());
    }

    public function testANotificationForAnOrderInAnUnsupportedCurrencyIsRefusedWithoutAFatal(): void
    {
        // A 3-decimal currency: UPC's AmountHelper throws InvalidCurrencyException for it.
        $this->order->set_currency('KWD');
        $this->order->save();
        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => self::OPERATION_ID,
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => 50000,
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(400, $status);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
    }

    public function testANotificationForAnOperationTheOrderIsNotBoundToIsRejected(): void
    {
        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_forged_999',
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(409, $status);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
    }

    public function testANotificationMatchingTheAlternateOperationIdIsAccepted(): void
    {
        // A payment and its operations are distinct Unified API resources, so the notification may
        // carry the payment's operationIds[0] rather than its top-level id; both are bound by
        // PaymentCaptureOutcomeApplier and either one is accepted here.
        $this->order->update_meta_data('_payplug_upc_operation_id_alt', 'op_alt_456');
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_alt_456',
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(200, $status);
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));
        // The true primary operation id must survive an alt-only match untouched - save() must
        // not overwrite it with the alt id this notification actually carried.
        $this->assertSame(self::OPERATION_ID, $order->get_meta('_payplug_upc_operation_id'));
        $this->assertSame('op_alt_456', $order->get_meta('_payplug_upc_operation_id_alt'));
    }

    public function testANotificationMatchingNeitherBoundOperationIdIsRejected(): void
    {
        $this->order->update_meta_data('_payplug_upc_operation_id_alt', 'op_alt_456');
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_forged_999',
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(409, $status);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
    }

    public function testANotificationMatchingNeitherBoundOperationIdOnAnAlreadyTerminalOrderIsAcknowledgedNotRetried(): void
    {
        // Simulates a retried checkout: a later attempt already resolved the order (a different,
        // current operation id), and this notification is a late/duplicate delivery for the
        // superseded first attempt - it can never match anything again, so it must not be told
        // to keep retrying.
        $this->order->update_status('processing');
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_superseded_999',
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $this->assertSame(200, $status);
        $this->assertSame('processing', wc_get_order($this->order->get_id())->get_status());
    }

    public function testAPaidNotificationForASupersededOperationFlagsAPossibleDoubleChargeOnce(): void
    {
        // A first attempt came back FAILED (e.g. an unmapped "Acceptation" execCode) and the
        // retry rebound the order: the first operation's late PAID notification must be flagged.
        $this->order->update_meta_data('_payplug_upc_superseded_operation_ids', ['op_superseded_1']);
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_superseded_1',
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);

        ob_start();
        $first_status = $controller->receive_notification($body);
        $second_status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $flag_notes = array_filter($notes, fn ($note) => false !== strpos($note->content, 'op_superseded_1'));

        $this->assertSame(200, $first_status);
        $this->assertSame(200, $second_status);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
        $this->assertCount(1, $flag_notes);
    }

    public function testAFailedNotificationForASupersededOperationIsAcknowledgedWithoutANote(): void
    {
        $this->order->update_meta_data('_payplug_upc_superseded_operation_ids', ['op_superseded_1']);
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_superseded_1',
            'execCode' => '9999',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $notes = wc_get_order_notes(['order_id' => $this->order->get_id()]);

        $this->assertSame(200, $status);
        $this->assertSame('pending', wc_get_order($this->order->get_id())->get_status());
        $this->assertCount(0, array_filter($notes, fn ($note) => false !== strpos($note->content, 'op_superseded_1')));
    }

    public function testANotificationForAnOrderThatNeverWentThroughUpcIsRejected(): void
    {
        $this->order->delete_meta_data('_payplug_upc_operation_id');
        $this->order->save();

        $controller = new UpcWebhook();

        ob_start();
        $status = $controller->receive_notification($this->notificationBody('0000'));
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(409, $status);
        $this->assertSame('pending', $order->get_status());
    }

    public function testAMalformedNotificationBodyIsRejectedWithABadRequestStatus(): void
    {
        $controller = new UpcWebhook();

        ob_start();
        $status = $controller->receive_notification('not json');
        ob_get_clean();

        $this->assertSame(400, $status);
        $this->assertSame('pending', wc_get_order($this->order->get_id())->get_status());
    }

    public function testRegisterQueryVarAddsTheIpnQueryVar(): void
    {
        $vars = (new UpcWebhook())->register_query_var([]);

        $this->assertContains('payplug_upc_ipn', $vars);
    }

    public function testRegisterRewriteRuleAddsTheFixedIpnRoute(): void
    {
        global $wp_rewrite;

        (new UpcWebhook())->register_rewrite_rule();

        $rules = $wp_rewrite->wp_rewrite_rules();
        $matched = array_filter(array_keys($rules), static fn (string $pattern): bool => false !== strpos($pattern, 'payplug/v2/ipn'));

        $this->assertNotEmpty($matched, 'Expected a rewrite rule matching the /payplug/v2/ipn route to be registered.');
        $this->assertSame('index.php?payplug_upc_ipn=1', $rules[reset($matched)]);
    }

    public function testMatchesRouteIsTrueOnlyWhenTheIpnQueryVarIsSet(): void
    {
        $controller = new UpcWebhook();

        set_query_var('payplug_upc_ipn', '');
        $this->assertFalse($controller->matches_route());

        set_query_var('payplug_upc_ipn', '1');
        $this->assertTrue($controller->matches_route());
    }

    public function testAnAlreadyTreatedNotificationIsNotReAppliedASecondTime(): void
    {
        $controller = new UpcWebhook();

        ob_start();
        $controller->receive_notification($this->notificationBody('0000'));
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));

        // Force the order back to a non-terminal status so that, unlike 'failed', it isn't
        // WooCommerceOrderStateMutator's own terminal-status guard that would prevent a second
        // transition below - only isTreated() stands in the way.
        $order->update_status('on-hold');

        ob_start();
        $controller->receive_notification($this->notificationBody('0000'));
        ob_get_clean();

        $this->assertSame('on-hold', wc_get_order($this->order->get_id())->get_status());
    }

    public function testASuccessfulNotificationPersistsTheUhfCardWhenSaveCardWasRequested(): void
    {
        $this->order->set_customer_id(1);
        $this->order->update_meta_data('_payplug_uhf_save_card', '1');
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => self::OPERATION_ID,
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
            'paymentMethod' => [
                'id' => 'alias_webhook_1',
                'card' => ['network' => 'VISA', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['validityDate' => '2030-12'],
            ],
        ]);

        ob_start();
        $controller->receive_notification($body);
        ob_get_clean();

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $card = UhfCard::find_by_alias('alias_webhook_1', $mode);

        $this->assertNotNull($card);
        $this->assertSame('VISA', $card->brand);
        $this->assertSame('4242', $card->last4);
        $this->assertSame(12, (int) $card->exp_month);
        $this->assertSame(2030, (int) $card->exp_year);
    }

    public function testASuccessfulNotificationDoesNotPersistACardWhenSaveCardWasNotRequested(): void
    {
        $this->order->set_customer_id(1);
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => self::OPERATION_ID,
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
            'paymentMethod' => [
                'id' => 'alias_webhook_2',
                'card' => ['network' => 'VISA', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['validityDate' => '2030-12'],
            ],
        ]);

        ob_start();
        $controller->receive_notification($body);
        ob_get_clean();

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $this->assertNull(UhfCard::find_by_alias('alias_webhook_2', $mode));
    }

    public function testAThreeDsPendingNotificationNeverPersistsACard(): void
    {
        $this->order->set_customer_id(1);
        $this->order->update_meta_data('_payplug_uhf_save_card', '1');
        $this->order->save();

        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => self::OPERATION_ID,
            'execCode' => '0001',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
            'paymentMethod' => [
                'id' => 'alias_webhook_3',
                'card' => ['network' => 'VISA', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['validityDate' => '2030-12'],
            ],
        ]);

        ob_start();
        $controller->receive_notification($body);
        ob_get_clean();

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $this->assertNull(UhfCard::find_by_alias('alias_webhook_3', $mode));
    }

    /**
     * Each test binds its own operation id: orders created in setUp() outlive the test
     * transaction, and an earlier order bound to the same id would answer the lookup.
     */
    private function bind_snapshot_order(): string
    {
        $operation_id = 'op_snapshot_' . $this->order->get_id();
        $this->order->update_meta_data('_payplug_upc_operation_id', $operation_id);
        $this->order->save();
        update_option('woocommerce_payplug_settings', ['mode' => true]);
        (new UpcTransactionMetadataRecorder())->record_created($this->order, $operation_id, true);

        return $operation_id;
    }

    private function card_notification_body(string $operation_id, string $exec_code, bool $with_card = true): string
    {
        $body = [
            'id' => $operation_id,
            'execCode' => $exec_code,
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total(), $this->order->get_currency()),
        ];

        if ($with_card) {
            $body['paymentMethod'] = [
                'id' => 'alias_snapshot_1',
                'card' => ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['validityDate' => '2030-07'],
            ];
        }

        return json_encode($body);
    }

    private function snapshot(): array
    {
        $meta = wc_get_order($this->order->get_id())->get_meta(UpcTransactionMetadataRecorder::META_KEY);

        return is_array($meta) ? $meta : [];
    }

    private function receive(string $body): int
    {
        ob_start();
        $status = (new UpcWebhook())->receive_notification($body);
        ob_get_clean();

        return $status;
    }

    public function test_a_paid_notification_marks_the_snapshot_paid_and_merges_the_card(): void
    {
        $operation_id = $this->bind_snapshot_order();

        $status = $this->receive($this->card_notification_body($operation_id, '0000'));

        $meta = $this->snapshot();
        self::assertSame(200, $status);
        self::assertTrue($meta['paid']);
        self::assertTrue($meta['3ds']);
        self::assertSame('VISA', $meta['card_brand']);
        self::assertSame('4242', $meta['card_last4']);
        self::assertSame(7, $meta['card_exp_month']);
        self::assertSame(2030, $meta['card_exp_year']);
    }

    public function test_a_paid_notification_for_an_already_treated_operation_still_merges_the_card(): void
    {
        $operation_id = $this->bind_snapshot_order();
        (new UpcTransactionMetadataRecorder())->record_paid($this->order);
        $order = wc_get_order($this->order->get_id());
        $order->update_status('processing');
        $order->update_meta_data('_payplug_upc_treated', 'yes');
        $order->save();

        $status = $this->receive($this->card_notification_body($operation_id, '0000'));

        $meta = $this->snapshot();
        self::assertSame(200, $status);
        self::assertTrue($meta['paid']);
        self::assertSame('VISA', $meta['card_brand']);
        self::assertSame('4242', $meta['card_last4']);
    }

    public function test_a_three_ds_pending_notification_merges_no_card(): void
    {
        $operation_id = $this->bind_snapshot_order();

        $this->receive($this->card_notification_body($operation_id, '0001'));

        self::assertSame('', $this->snapshot()['card_brand']);
        self::assertFalse($this->snapshot()['paid']);
    }

    public function test_a_failed_notification_merges_no_card(): void
    {
        $operation_id = $this->bind_snapshot_order();

        $this->receive($this->card_notification_body($operation_id, '0300'));

        self::assertSame('', $this->snapshot()['card_brand']);
        self::assertFalse($this->snapshot()['paid']);
    }

    public function test_a_notification_for_an_operation_not_bound_to_the_order_merges_no_card(): void
    {
        $this->bind_snapshot_order();

        $status = $this->receive($this->card_notification_body('op_forged_999', '0000'));

        self::assertSame(409, $status);
        self::assertSame('', $this->snapshot()['card_brand']);
        self::assertFalse($this->snapshot()['paid']);
    }

    public function test_a_paid_notification_with_a_mismatched_amount_merges_no_card(): void
    {
        $operation_id = $this->bind_snapshot_order();
        $body = json_decode($this->card_notification_body($operation_id, '0000'), true);
        $body['amount'] += 100;

        $status = $this->receive(json_encode($body));

        self::assertSame(400, $status);
        self::assertSame('', $this->snapshot()['card_brand']);
    }

    public function test_a_paid_notification_without_payment_method_keeps_the_snapshot_card_data(): void
    {
        $operation_id = $this->bind_snapshot_order();
        (new UpcTransactionMetadataRecorder())->record_card($this->order, json_decode($this->card_notification_body($operation_id, '0000'), true));

        $this->receive($this->card_notification_body($operation_id, '0000', false));

        $meta = $this->snapshot();
        self::assertTrue($meta['paid']);
        self::assertSame('VISA', $meta['card_brand']);
        self::assertSame('4242', $meta['card_last4']);
    }
}
