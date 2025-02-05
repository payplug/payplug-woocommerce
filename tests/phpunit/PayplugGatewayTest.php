<?php

namespace phpunit;

use PHPUnit\Framework\TestCase;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use WC_Order;

class PayplugGatewayTest extends TestCase
{
	protected $gateway;
	protected $order;

	protected function setUp(): void
	{
		parent::setUp();

		// Create mock for WC_Order
		$this->order = $this->createMock(WC_Order::class);

		// Initialize gateway
		$this->gateway = new PayplugGateway();

	}

	public function testValidateOrderAmount()
	{
		// Test amount within valid range (€0.99 to €20000)
		$valid_amount = 1000; // €10.00
		$result = $this->gateway->validate_order_amount($valid_amount);
		$this->assertEquals($valid_amount, $result);

		// Test amount below minimum
		$invalid_low_amount = 50; // €0.50
		$result = $this->gateway->validate_order_amount($invalid_low_amount);
		$this->assertInstanceOf('WP_Error', $result);

		// Test amount above maximum
		$invalid_high_amount = 2100000; // €21000.00
		$result = $this->gateway->validate_order_amount($invalid_high_amount);
		$this->assertInstanceOf('WP_Error', $result);
	}

	public function testProcessStandardPayment()
	{
		$order_id = 123;
		$amount = 1000; // €10.00
		$customer_id = 1;

		// Setup order mock expectations
		$this->order->expects($this->any())
		            ->method('get_id')
		            ->willReturn($order_id);

		$this->order->expects($this->any())
		            ->method('get_checkout_order_received_url')
		            ->willReturn('http://example.com/order-received');

		$this->order->expects($this->any())
		            ->method('get_cancel_order_url_raw')
		            ->willReturn('http://example.com/cancel-order');

		$this->order->expects($this->any())
		            ->method('get_total')
		            ->willReturn(10.00);

		// Create payment response object
		$payment_response = new \stdClass();
		$payment_response->id = 'pay_123456';
		$payment_response->hosted_payment = new \stdClass();
		$payment_response->hosted_payment->payment_url = 'https://secure.payplug.com/pay/123456';
		$payment_response->hosted_payment->cancel_url = 'http://example.com/cancel';

		// Setup API mock expectations
		$this->gateway->api->expects($this->once())
		                   ->method('payment_create')
		                   ->willReturn($payment_response);

		// Test process_standard_payment
		$result = $this->gateway->process_standard_payment($this->order, $amount, $customer_id);

		$this->assertIsArray($result);
		$this->assertEquals('success', $result['result']);
		$this->assertEquals($payment_response->id, $result['payment_id']);
		$this->assertEquals($payment_response->hosted_payment->payment_url, $result['redirect']);
	}

	public function testProcessPaymentWithToken()
	{
		$order_id = 123;
		$amount = 1000; // €10.00
		$customer_id = 1;
		$token_id = 'tok_123456';

		// Create mock for WC_Payment_Token_CC
		$payment_token = $this->createMock('WC_Payment_Token_CC');
		$payment_token->expects($this->any())
		              ->method('get_token')
		              ->willReturn('card_123456');

		$payment_token->expects($this->any())
		              ->method('get_user_id')
		              ->willReturn($customer_id);

		// Setup order mock
		$this->order->expects($this->any())
		            ->method('get_id')
		            ->willReturn($order_id);

		$this->order->expects($this->any())
		            ->method('get_checkout_order_received_url')
		            ->willReturn('http://example.com/order-received');

		// Create payment response
		$payment_response = new \stdClass();
		$payment_response->id = 'pay_123456';
		$payment_response->is_paid = true;

		// Setup API expectations
		$this->gateway->api->expects($this->once())
		                   ->method('payment_create')
		                   ->willReturn($payment_response);

		// Test process_payment_with_token
		$result = $this->gateway->process_payment_with_token($this->order, $amount, $customer_id, $token_id);

		$this->assertIsArray($result);
		$this->assertEquals('success', $result['result']);
		$this->assertEquals($payment_response->id, $result['payment_id']);
		$this->assertTrue($result['is_paid']);
	}

	public function testRefundProcess()
	{
		$order_id = 123;
		$amount = 500; // €5.00
		$reason = 'Customer request';

		// Setup order mock
		$this->order->expects($this->any())
		            ->method('get_id')
		            ->willReturn($order_id);

		$this->order->expects($this->any())
		            ->method('get_transaction_id')
		            ->willReturn('pay_123456');

		$this->order->expects($this->any())
		            ->method('get_status')
		            ->willReturn('completed');

		// Create refund response
		$refund_response = new \stdClass();
		$refund_response->id = 'ref_123456';
		$refund_response->amount = $amount;
		$refund_response->metadata = ['reason' => $reason];

		// Setup API expectations
		$this->gateway->api->expects($this->once())
		                   ->method('refund_create')
		                   ->willReturn($refund_response);

		// Test process_refund
		$result = $this->gateway->process_refund($order_id, $amount, $reason);

		$this->assertTrue($result);
	}

	public function testOneClickAvailability()
	{
		// Create mock for permissions
		$permissions = $this->createMock('Payplug\PayplugWoocommerce\Gateway\PayplugPermissions');
		$permissions->expects($this->any())
		            ->method('has_permissions')
		            ->willReturn(true);

		// Test when oneclick should be available
		$this->gateway->id = 'payplug';
		$this->gateway->oneclick = true;
		$this->gateway->permissions = $permissions;

		$result = $this->gateway->oneclick_available();
		$this->assertTrue($result);

		// Test when oneclick should not be available
		$this->gateway->oneclick = false;
		$result = $this->gateway->oneclick_available();
		$this->assertFalse($result);
	}

	public function testLimitLength()
	{
		$long_string = str_repeat('a', 150);
		$result = $this->gateway->limit_length($long_string, 100);
		$this->assertEquals(100, strlen($result));

		$short_string = 'short string';
		$result = $this->gateway->limit_length($short_string, 100);
		$this->assertEquals($short_string, $result);
	}

	public function testGetCurrentMode()
	{
		// Test live mode
		$this->gateway->update_option('mode', 'yes');
		$this->assertEquals('live', $this->gateway->get_current_mode());

		// Test test mode
		$this->gateway->update_option('mode', 'no');
		$this->assertEquals('test', $this->gateway->get_current_mode());
	}
}
