<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Model\HostedFields;

class populateCreatePayment extends BaseHostedFields {

	public function setUp(): void {
		parent::setUp(); // Call the parent setUp method to initialize the hostedFields object
		// Additional setup can be done here if needed
	}

	public function testPopulateCreatePaymentWithValidData()
	{
		$payment_data = [];
		$order = $this->createMock(\WC_Order::class);
		$order_id = 12345;
		$hf_token = 'valid_token';
		$amount = 5000;

		// Mock order methods
		$order->method('get_billing_first_name')->willReturn('John');
		$order->method('get_billing_last_name')->willReturn('Doe');
		$order->method('get_billing_email')->willReturn('john.doe@example.com');
		$order->method('get_customer_user_agent')->willReturn('Mozilla/5.0');
		$order->method('get_customer_ip_address')->willReturn('127.0.0.1');
		$order->method('get_customer_order_notes')->willReturn('Test order notes');

		// Mock HostedFields methods
		$this->hostedFields->method('get_api_version')->willReturn('3.0');
		$this->hostedFields->method('get_api_key')->willReturn('api_key');
		$this->hostedFields->method('buildHashContent')->willReturn('hashed_content');
		$this->hostedFields->method('limit_length')->willReturn('https://example.com');

		$result = $this->hostedFields->populateCreatePayment($payment_data, $order, $order_id, $hf_token, $amount);

		$this->assertArrayHasKey('method', $result);
		$this->assertEquals('payment', $result['method']);

		$this->assertArrayHasKey('params', $result);
		$this->assertEquals('1000', $result['params']['AMOUNT']);
		$this->assertEquals('hashed_content', $result['params']['HASH']);
		$this->assertEquals('JohnDoe', $result['params']['CLIENTIDENT']);
		$this->assertEquals('john.doe@example.com', $result['params']['CLIENTEMAIL']);
	}

	public function testPopulateCreatePaymentThrowsExceptionForEmptyToken()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Hosted fields token is empty.');

		$payment_data = [];
		$order = $this->createMock(\WC_Order::class);
		$order_id = 12345;
		$hf_token = '';
		$amount = 5000;

		$this->hostedFields->populateCreatePayment($payment_data, $order, $order_id, $hf_token, $amount);
	}
}
