<?php

namespace phpunit\Controller;

use Payplug\PayplugWoocommerce\Gateway\Bancontact;
use PHPUnit\Framework\TestCase;
use WC_Payment_Gateways;
use WC_Unit_Test_Case;

class PayplugGatewayBancontact_test extends TestCase
{


	/**
	 * Function needs to be an extension of WC_Payment_Gateway_CC
	 * @test
	 */
	public function test_PayplugGateway(){
		$bancontact = new Bancontact();
		$this->assertInstanceOf("WC_Payment_Gateway_CC", $bancontact );

	}

	/**
	 * Assert $this->config is retrieving the right information from db
	 * @test
	 */
	public function test_loadConfig(){
		$bancontact = new Bancontact();
		$this->assertTrue( !empty($bancontact->email) );
		$this->assertTrue( !empty($bancontact->payment_method) );

	}

	/**
	 * Test output of function
	 * @test
	 */
	public function test_loggedUser(){
		$bancontact = new Bancontact();
		$this->assertIsBool( $bancontact->user_logged_in() );
	}

	/**
	 * @test
	 */
	public function test_BancontactConfigs(){
		$bancontact = new Bancontact();
		$this->assertEquals("bancontact", $bancontact->id);
		$this->assertEquals("Pay with Bancontact", $bancontact->title);
		$this->assertEquals("", $bancontact->description);

	}
}
