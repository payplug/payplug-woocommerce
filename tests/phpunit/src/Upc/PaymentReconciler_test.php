<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\PaymentReconciler;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
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
        return json_encode(['id' => 'op_pending_123', 'execCode' => $execCode]);
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
