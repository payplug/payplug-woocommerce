<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Monolog\Test\TestCase;

use Payplug\PayplugWoocommerce\PayplugWoocommerce;
use Payplug\PayplugWoocommerce\Tests\phpunit\Mocks\PaymentGateways;

class PaymentGateway extends TestCase {

	private $payment_gateways = array();

	protected function setUp(): void {
		$this->mock_payment_gateways = PaymentGateways::$gateways;
		$payplug = PayplugWoocommerce::get_instance();
		$this->payment_gateways = $payplug->register_payplug_gateway([]);
	}

	public function test_standard_gateway_properties(){
		foreach ($this->payment_gateways as $class){
			$gateway = new $class;
			$gateway_mock = $this->mock_payment_gateways[$gateway->id];

			self::assertTrue(!empty($this->mock_payment_gateways[$gateway->id]));
			self::assertTrue($gateway_mock['id'] === $gateway->id );
			self::assertTrue($gateway_mock['title'] === $gateway->get_title() );
			self::assertTrue($gateway_mock['method_title'] === $gateway->get_method_title() );
			self::assertTrue($gateway_mock['description'] === $gateway->get_description() );
			self::assertTrue($gateway_mock['method_description'] === $gateway->get_method_description() );
			self::assertTrue($gateway_mock['has_fields'] === $gateway->has_fields() );
			self::assertTrue($gateway_mock['enable'] === $gateway->enabled );
			self::assertTrue($gateway_mock['enable_on_test_mode'] === $gateway::ENABLE_ON_TEST_MODE );

			//TODO:: untestable method on oney refact
			if(!in_array($gateway->id, ['oney_x3_with_fees', 'oney_x3_without_fees', 'oney_x4_with_fees', 'oney_x4_without_fees'] )){
				self::assertTrue($gateway_mock['image'] === $gateway->get_icon() );
			}

		}
	}

}
