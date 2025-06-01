<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Model\HostedFields;

use Monolog\Test\TestCase;
use Payplug\PayplugWoocommerce\Model\HostedFields;

class templateFormtest extends TestCase {

	public function testTemplateFormContainsFormTag()
	{
		$result = HostedFields::template_form();

		$this->assertStringContainsString('<form id="payment-form"', $result);
		$this->assertStringContainsString('class="payplug IntegratedPayment -loaded"', $result);
		$this->assertStringContainsString('onsubmit="event.preventDefault(); HostedFields.tokenizeHandler();"', $result);
	}

	public function testTemplateFormContainsCardHolderInput()
	{
		$result = HostedFields::template_form();

		$this->assertStringContainsString('<input type="text" name="hosted-fields-cardHolder"', $result);
		$this->assertStringContainsString('id="hosted-fields-cardHolder"', $result);
		$this->assertStringContainsString('class="hosted-fields hosted-fields-input-state"', $result);
	}

	public function testTemplateFormContainsErrorMessages()
	{
		$result = HostedFields::template_form();

		$this->assertStringContainsString('data-e2e-error="invalidField"', $result);
		$this->assertStringContainsString('data-e2e-error="paymentError"', $result);
	}

	public function testTemplateFormContainsTransactionSection()
	{
		$result = HostedFields::template_form();

		$this->assertStringContainsString('<img class="lock-icon"', $result);
		$this->assertStringContainsString('<label class="transaction-label">', $result);
		$this->assertStringContainsString('<img class="payplug-logo"', $result);
	}

	public function testTemplateFormContainsPrivacyPolicyLink()
	{
		$result = HostedFields::template_form();

		$this->assertStringContainsString('<a href="', $result);
		$this->assertStringContainsString('target="_blank">', $result);
	}

}
