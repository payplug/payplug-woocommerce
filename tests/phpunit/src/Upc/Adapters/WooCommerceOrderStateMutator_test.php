<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc\Adapters;

use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceOrderStateMutator;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PHPUnit\Framework\TestCase;

class WooCommerceOrderStateMutator_test extends TestCase
{
    /** @var \WC_Order */
    private $order;

    protected function setUp(): void
    {
        $this->order = wc_create_order();
        // A physical (non-virtual) line item, so WC_Order::needs_processing() - and so
        // payment_complete()'s own status choice - resolves to 'processing', not 'completed'; an
        // order with zero items needs_processing() defaults to false.
        $this->order->add_product(\WC_Helper_Product::create_simple_product(), 1);
        $this->order->set_status('pending');
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

    public function testPaidMapsToProcessing(): void
    {
        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::PAID);

        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testPaidOutcomeCompletesPaymentWithTheStoredOperationIdAsTransactionId(): void
    {
        // Set by WooCommercePaymentRepository::save(), always called before apply() on every real
        // call path (synchronous checkout and webhook alike).
        $this->order->update_meta_data('_payplug_upc_operation_id', 'op_paid_123');
        $this->order->save();

        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::PAID);

        $order = $this->reload();
        $this->assertSame('op_paid_123', $order->get_transaction_id());
        $this->assertNotNull($order->get_date_paid());
    }

    public function testCaptureRequiredMapsToOnHold(): void
    {
        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::CAPTURE_REQUIRED);

        $this->assertSame('on-hold', $this->reload()->get_status());
    }

    public function testAuthorizedMapsToOnHold(): void
    {
        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::AUTHORIZED);

        $this->assertSame('on-hold', $this->reload()->get_status());
    }

    public function testRefundedMapsToRefunded(): void
    {
        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::REFUNDED);

        $this->assertSame('refunded', $this->reload()->get_status());
    }

    public function testFailedMapsToFailed(): void
    {
        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::FAILED);

        $this->assertSame('failed', $this->reload()->get_status());
    }

    public function testThreeDsPendingLeavesTheOrderUntouched(): void
    {
        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::THREE_DS_PENDING);

        $this->assertSame('pending', $this->reload()->get_status());
    }

    public function testAnOrderAlreadyProcessingIsNotBouncedBackToOnHold(): void
    {
        $this->order->set_status('processing');
        $this->order->save();

        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::AUTHORIZED);

        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testAFailedOrderIsMovedToProcessingByARetriedSuccessfulPayment(): void
    {
        // 'failed' is not terminal: WC_Order::needs_payment() explicitly allows repaying a
        // failed order, and a retried checkout binds a fresh operation id before apply() runs
        // (PaymentCaptureOutcomeApplier::persist_operation()) - a genuine new PAID outcome must
        // still go through.
        $this->order->set_status('failed');
        $this->order->save();

        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::PAID);

        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testACancelledOrderIsRevivedByALatePaidNotification(): void
    {
        // e.g. WooCommerce's stock-hold timer cancels a still-pending 3DS order, then the real
        // notification for that same operation arrives - the payment did succeed and must not be
        // stranded.
        $this->order->set_status('cancelled');
        $this->order->save();

        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::PAID);

        $this->assertSame('processing', $this->reload()->get_status());
    }

    public function testAnOrderAlreadyRefundedIsNotReAppliedToProcessing(): void
    {
        $this->order->set_status('refunded');
        $this->order->save();

        (new WooCommerceOrderStateMutator())->apply((string) $this->order->get_id(), PaymentOutcome::PAID);

        $this->assertSame('refunded', $this->reload()->get_status());
    }
}
