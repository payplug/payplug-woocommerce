<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\PaymentCaptureOutcomeApplier;
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
        delete_transient('payplug_upc_redirect_html_' . $this->order->get_id());
        wp_delete_post($this->order->get_id(), true);
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

    public function testAResponseWithoutAnOperationIdPersistsNothingAndStillCompletesTheFlow(): void
    {
        $output = new PaymentOutput(200, '{"execCode":"0000"}', null, null, null);

        $result = (new PaymentCaptureOutcomeApplier())->apply($this->order, $output);

        $this->assertSame('success', $result['result']);
        $this->assertSame('processing', $this->reload()->get_status());
        $this->assertSame('', $this->reload()->get_meta('_payplug_upc_operation_id'));
    }
}
