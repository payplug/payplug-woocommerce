<?php

namespace unit;
use Payplug;


class Payment_Gateway_Test extends WP_UnitTestCase {
	private $gateway;

	public function setUp(): void {
		parent::setUp();
		$this->gateway = new Payplug();
	}

	public function test_gateway_initialization() {
		// Test if the gateway is properly initialized
		$this->assertNotNull( $this->gateway );
		$this->assertTrue( $this->gateway->is_available() );
	}

	public function test_payment_fields() {
		// Test payment form fields generation
		ob_start();
		$this->gateway->payment_fields();
		$payment_fields = ob_get_clean();

		$this->assertStringContainsString( 'payment-form-input', $payment_fields );
	}

	public function test_validate_fields() {
		// Simulate form submission and test field validation
		$_POST['my_payment_field'] = '1234567890'; // Example field
		$this->assertTrue( $this->gateway->validate_fields() );

		// Test invalid input
		$_POST['my_payment_field'] = '';
		$this->assertFalse( $this->gateway->validate_fields() );
	}
}
