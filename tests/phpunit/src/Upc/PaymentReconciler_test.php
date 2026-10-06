<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\PaymentReconciler;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;
use PHPUnit\Framework\TestCase;

class PaymentReconciler_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        $this->order = wc_create_order();
        $this->order->add_product(\WC_Helper_Product::create_simple_product(), 1);
        $this->order->set_total(50.00);
        $this->order->set_status('pending');
        $this->order->update_meta_data('_payplug_upc_operation_id', 'op_pending_123');
        $this->order->update_meta_data('_payplug_upc_outcome', PaymentOutcome::THREE_DS_PENDING);
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

    private function body(string $execCode): string
    {
        return json_encode([
            'id' => 'op_pending_123',
            'execCode' => $execCode,
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
        ]);
    }

    public function testAFinalPaidStateMovesTheOrderToProcessingAndMarksTheOperationTreated(): void
    {
        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', $this->body('0000'));

        $order = $this->reload();
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('yes', $order->get_meta('_payplug_upc_treated'));
    }

    public function testAFinalFailedStateMovesTheOrderToFailed(): void
    {
        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', $this->body('0300'));

        $this->assertSame('failed', $this->reload()->get_status());
    }

    public function testAStillPendingStateLeavesTheOrderUntouched(): void
    {
        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', $this->body('0001'));

        $order = $this->reload();
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
    }

    public function testAMalformedResponseBodyIsIgnored(): void
    {
        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', 'not json');

        $this->assertSame('pending', $this->reload()->get_status());
    }

    public function testAResponseWithAMismatchedAmountDoesNotChangeTheOrderStatus(): void
    {
        $body = json_encode([
            'id' => 'op_pending_123',
            'execCode' => '0000',
            'amount' => AmountHelper::toCents((float) $this->order->get_total()) + 100,
        ]);

        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', $body);

        $order = $this->reload();
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));
    }

    public function testReconcileFetchesTheOperationResourceByItsAlternateOperationId(): void
    {
        // getPayment() answers 404 on staging; the public operation endpoint answers for
        // operationIds[0], stored as the alternate operation id.
        $this->order->update_meta_data('_payplug_upc_operation_id_alt', 'op_alt_456');
        $this->order->save();
        $requested_urls = [];

        add_filter('pre_http_request', function ($preempt, $args, $url) use (&$requested_urls) {
            $requested_urls[] = $url;

            if (false !== strpos($url, '/oauth2/token')) {
                return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 3600, 'token_type' => 'Bearer']), 'headers' => [], 'cookies' => []];
            }

            if (false !== strpos($url, '/processing-operations/operations/public/op_alt_456')) {
                return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => $this->body('0000'), 'headers' => [], 'cookies' => []];
            }

            return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => '', 'headers' => [], 'cookies' => []];
        }, 10, 3);

        (new PaymentReconciler())->reconcile($this->reload());

        remove_all_filters('pre_http_request');
        delete_transient('upc_token_upc_oauth_token:');
        delete_transient('payplug_upc_reconcile_' . $this->order->get_id());

        $this->assertSame('processing', $this->reload()->get_status());
        $this->assertEmpty(array_filter($requested_urls, static fn (string $url): bool => false !== strpos($url, '/api/payment-gateway/payments/')), 'getPayment() must not be called anymore.');
    }

    public function testAPaidReconciliationSavesTheCardFromTheFetchedOperationBody(): void
    {
        $this->order->set_customer_id(1);
        $this->order->update_meta_data('_payplug_uhf_save_card', '1');
        $this->order->save();

        $body = json_encode([
            'id' => 'op_pending_123',
            'execCode' => '0000',
            'amount' => AmountHelper::toCents((float) $this->order->get_total()),
            'paymentMethod' => [
                'id' => 'alias_reconciled_1',
                'card' => ['network' => 'visa', 'code6x4' => '424242XXXXXX4242'],
                'details' => ['selectedBrand' => 'VISA', 'validityDate' => '2099-12'],
            ],
        ]);

        (new PaymentReconciler())->apply_reconciled_state($this->reload(), 'op_pending_123', $body);

        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $card = UhfCard::find_by_alias('alias_reconciled_1', $mode);

        $this->assertNotNull($card);
        $this->assertSame('4242', $card->last4);

        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
    }

    public function testReconcileSkipsOrdersNotInThreeDsPending(): void
    {
        $this->order->update_meta_data('_payplug_upc_outcome', PaymentOutcome::PAID);
        $this->order->save();

        // No HTTP call should even be attempted - if it were, an unconfigured factory would
        // throw. Reaching the assertion below is itself proof the early return fired.
        (new PaymentReconciler())->reconcile($this->reload());

        $this->assertSame('pending', $this->reload()->get_status());
    }

    public function testReconcileSkipsOrdersWithNoBoundOperationId(): void
    {
        $this->order->delete_meta_data('_payplug_upc_operation_id');
        $this->order->save();

        (new PaymentReconciler())->reconcile($this->reload());

        $this->assertSame('pending', $this->reload()->get_status());
    }

    public function testApplyReconciledStateDoesNothingWhenAWebhookIsAlreadyHandlingTheSameOperation(): void
    {
        $lock_key = 'payplug_upc_treat_op_pending_123';
        $lock = new WooCommerceLock();
        $lock->acquire($lock_key, 30);

        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', $this->body('0000'));

        $order = $this->reload();
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('', $order->get_meta('_payplug_upc_treated'));

        $lock->release($lock_key);
    }

    public function testApplyReconciledStateDoesNothingWhenTheOperationWasAlreadyTreatedByTheWebhook(): void
    {
        // Simulates the webhook having already applied and released this exact operation between
        // this reconciliation reading THREE_DS_PENDING and calling apply_reconciled_state(). Uses
        // 'on-hold' deliberately: it is NOT one of WooCommerceOrderStateMutator's own terminal
        // statuses, so only the isTreated() guard below - not that other, independent guard -
        // can be what keeps a PAID outcome from moving it to 'processing'.
        $this->order->update_status('on-hold');
        $this->order->update_meta_data('_payplug_upc_treated', 'yes');
        $this->order->save();

        (new PaymentReconciler())->apply_reconciled_state($this->order, 'op_pending_123', $this->body('0000'));

        $this->assertSame('on-hold', $this->reload()->get_status());
    }
}
