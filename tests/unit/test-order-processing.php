<?php
use WP_UnitTestCase;
/**
 * Order Processing Unit Tests
 */
class Order_Processing_Test extends WP_UnitTestCase {
	private $gateway;

	public function setUp(): void {
		parent::setUp();
		$this->gateway = new My_Payment_Gateway();
	}

	public function test_process_payment() {
		// Create a test order
		$order = $this->create_test_order();

		// Process payment
		$result = $this->gateway->process_payment( $order->get_id() );

		// Assert payment processing result
		$this->assertTrue( $result['result'] === 'success' );
		$this->assertNotEmpty( $result['redirect'] );
	}

	private function create_test_order() {
		$order = wc_create_order();
		$order->set_billing_first_name( 'John' );
		$order->set_billing_last_name( 'Doe' );
		$order->set_billing_email( 'john.doe@example.com' );
		$order->save();

		return $order;
	}
}
