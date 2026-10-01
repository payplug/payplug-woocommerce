<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\UpcWebhook;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()) + 100,
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $order = wc_get_order($this->order->get_id());

        $this->assertSame(400, $status);
        $this->assertSame('pending', $order->get_status());
    }

    public function testANotificationForAnOperationTheOrderIsNotBoundToIsRejected(): void
    {
        $controller = new UpcWebhook();
        $body = json_encode([
            'id' => 'op_forged_999',
            'execCode' => '0000',
            'orderId' => (string) $this->order->get_id(),
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
        ]);

        ob_start();
        $status = $controller->receive_notification($body);
        ob_get_clean();

        $this->assertSame(200, $status);
        $this->assertSame('processing', wc_get_order($this->order->get_id())->get_status());
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
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
}
