<?php

use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;
use Payplug\PayplugWoocommerce\Tests\phpunit\Mocks\PaymentGateways;
use \Monolog\Test\TestCase;


/**
 * Integration test for PayplugGenericGateway
 *
 * @package Payplug\PayplugWoocommerce\Tests\Integration
 */
class PayplugGenericGatewayTest extends TestCase
{

	/**
	 * Test that refund is not available when disabled for all available gateways
	 */
	public function testRefundNotAvailable()
	{
		// Get all available gateways from our mock
		$available_gateways = PaymentGateways::$gateways;

		// Test each gateway
		foreach ($available_gateways as $gateway_id => $gateway_config) {
			// Create a mock for WC_Order
			$order = $this->createMock(\WC_Order::class);

			// Configure the mock to return the gateway ID when get_payment_method is called
			$order->method('get_payment_method')
			      ->willReturn($gateway_id);

			// Create an instance of the gateway with correct ID
			$gateway = new PayplugGenericGateway();
			$gateway->id = $gateway_id;
			$gateway->enable_refund = false;

			// Start output buffering to capture the echo output
			ob_start();

			// Call the method
			$gateway->refund_not_available($order);

			// Get the output and clean the buffer
			$output = ob_get_clean();

			// Assert that the output contains the expected error message with gateway ID
			$this->assertStringContainsString(
				"<p style='color: red;'>" . __('payplug_refund_disabled_error', 'payplug') . "</p>",
				$output,
				"Failed asserting that refund_not_available works for gateway: $gateway_id"
			);
		}
	}


	/**
	 * Clean up the test environment
	 */
	public function tearDown(): void
	{
		parent::tearDown();
	}
}
