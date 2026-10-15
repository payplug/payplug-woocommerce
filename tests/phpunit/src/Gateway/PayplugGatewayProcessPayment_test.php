<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\FakePayment;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\PayplugWoocommerceRequestDouble;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class PayplugGatewayProcessPayment_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
    }

    protected function tearDown(): void
    {
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    /**
     * The Store API (Blocks checkout) never sets order_awaiting_payment: it keeps the order in
     * store_api_draft_order. A guest paying through Blocks must still own the order afterwards.
     */
    public function test_process_payment_of_a_guest_store_api_order_leaves_it_owned_by_the_session(): void
    {
        $order = $this->make_order(['payment_method' => 'payplug']);
        WC()->session->set('store_api_draft_order', $order->get_id());
        self::assertSame(0, $this->session_order_id());

        $this->make_gateway()->process_payment($order->get_id());

        self::assertSame($order->get_id(), $this->session_order_id());
    }

    public function test_check_payment_accepts_a_guest_store_api_order_after_process_payment(): void
    {
        $order = $this->make_order(['payment_method' => 'payplug', 'transaction_id' => true]);
        WC()->session->set('store_api_draft_order', $order->get_id());
        $this->make_gateway()->process_payment($order->get_id());

        $request = new PayplugWoocommerceRequestDouble();
        $request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $request->payment->is_paid = true;
        $request->payment->paid_at = 1700000000;
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$request, 'check_payment']);

        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['success']);
    }

    public function test_process_payment_of_an_unknown_order_does_not_touch_the_session(): void
    {
        try {
            $this->make_gateway()->process_payment(987654321);
            self::fail('An unknown order must throw.');
        } catch (\Exception $e) {
            self::assertSame(0, $this->session_order_id());
        }
    }

    private function make_gateway(): PayplugGateway
    {
        return new class () extends PayplugGateway {
            public function process_standard_payment($order, $amount, $customer_id)
            {
                return ['result' => 'success'];
            }

            public function validate_order_amount($amount)
            {
                return $amount;
            }
        };
    }
}
