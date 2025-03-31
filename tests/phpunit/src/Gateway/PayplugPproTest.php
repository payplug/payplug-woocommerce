<?php

use PHPUnit\Framework\TestCase;
use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;
use WC_Order;

class PayplugPproTest extends TestCase
{
    public function testRefundNotAvailable()
    {
        // Create a mock for the WC_Order class.
        $order = $this->createMock(WC_Order::class);

        $order->method('get_payment_method')->willReturn('payplug');

        // Create an instance of the PayplugGenericGateway.
        $gateway = new PayplugGenericGateway();

        // Set enable_refund property.
        $gateway->enable_refund = false;

        // Start output buffering to capture the echo output.
        ob_start();

        // Call the method.
        $gateway->refund_not_available($order);

        // Get the output and clean the buffer.
        $output = ob_get_clean();

        // Assert that the output contains the expected error message.
        $this->assertStringContainsString("<p style='color: red;'>" . __('payplug_refund_disabled_error', 'payplug') . "</p>", $output);
    }
}

