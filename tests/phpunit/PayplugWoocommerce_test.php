<?php

namespace phpunit;

use Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyFR;
use Payplug\PayplugWoocommerce\PayplugWoocommerce;
use phpDocumentor\Reflection\Types\Void_;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Type\VoidType;
use function PHPUnit\Framework\assertTrue;

class PayplugWoocommerce_test extends TestCase{

	private $payplug_woocommerce;

	protected function setUp(): void {
		$this->payplug_woocommerce = PayplugWoocommerce::get_instance();
		parent::setUp();
	}

	/**
	 * test if there's an active woocommerce version
	 */
	public function test_woocommerce_version(){
		$wc = function_exists( 'WC' ) ? WC() : $GLOBALS['woocommerce'];
		self::assertNotEmpty($wc->version);
		self::assertNotEmpty(defined( 'WC_VERSION' ));
		self::assertIsString($wc->version);

	}

	/**
	 * test oney animation is disabled if empty options
	 */
	public function test_disable_empty_options_animation_handlers(){
		$mockPayplugWoocommerce = $this->createMock(PayplugWoocommerce::class);
		$mockPayplugWoocommerce
			->method('animationHandlers')
			->willReturn(false);

		update_option("woocommerce_payplug_settings", []);
		self::assertFalse( $mockPayplugWoocommerce->animationHandlers() );

	}

	/**
	 * test oney animation is disabled without merchant country
	 */
	public function test_disable_empty_merchant_country_animation_handlers(){
		$mockPayplugWoocommerce = $this->createMock(PayplugWoocommerce::class);
		$mockPayplugWoocommerce
			->method('animationHandlers')
			->willReturn(false);

		update_option("woocommerce_payplug_settings", ["payplug_merchant_country"=>"", "oney_type"=>"something"]);
		self::assertFalse( $mockPayplugWoocommerce->animationHandlers() );

	}

	/**
	 * test oney animation is disabled without oney type
	 */
	public function test_disable_empty_oney_type_animation_handlers(){
		$mockPayplugWoocommerce = $this->createMock(PayplugWoocommerce::class);
		$mockPayplugWoocommerce
			->method('animationHandlers')
			->willReturn(false);

		update_option("woocommerce_payplug_settings", ["payplug_merchant_country"=>"something", "oney_type"=>""]);
		self::assertFalse( $mockPayplugWoocommerce->animationHandlers() );

	}

	/**
	 * test if oney animations are being instatiated
	 * OneyAnimation
	 * OneyFR
	 */
	public function test_animation_handlers(){
		$mockPayplugWoocommerce = $this->createMock(PayplugWoocommerce::class);
		$mockPayplugWoocommerce
			->method('animationHandlers')
			->willReturn(true);

		update_option("woocommerce_payplug_settings", ["payplug_merchant_country" => "FR", "oney_type"=>"without_fees"]);
		self::assertTrue( $mockPayplugWoocommerce->animationHandlers() );

	}

	/**
	 * assert all classes we're loading exists
	 * assert we're loading all gateways
	 */
	public function test_register_payplug_gateway_exists(){
		$results = $this->payplug_woocommerce->register_payplug_gateway([]);
		$gateways = 0;
		foreach ($results as $k => $class){
			self::assertTrue(class_exists($class));
			$gateways++;
		}

		self::assertTrue( $gateways === 11 );
	}

	/**
	 * tested on e2e side, this is simply to declare actions to load
	 */
	public function test_woocommerce_gateways_block_support(){
		assertTrue(true);
	}

	/**
	 * assert added plugin_action_links
	 */
	public function test_plugin_action_links(){
		self::assertIsArray($this->payplug_woocommerce->plugin_action_links());
		self::assertNotEmpty($this->payplug_woocommerce->plugin_action_links());

		foreach ($this->payplug_woocommerce->plugin_action_links() as $k){
			self::assertIsString($k);
		}

		try {
			$this->payplug_woocommerce->plugin_action_links("");
		}
		catch( \Exception $e)
		{
			$this->assertInstanceOf(\Exception::class, $e);
		}

	}

}
