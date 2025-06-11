<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Model\HostedFields;

use Payplug\PayplugWoocommerce\Model\HostedFields;
use PHPUnit\Framework\TestCase;

class LimitLength_test extends TestCase {

	private $hostedFields;

	public function setUp(): void
	{
		$this->hostedFields = new HostedFields('secret', 'api_key', 'identifier', 'api_key_secret');
	}

	public function testLimitLengthWithShortString()
	{
		$value = "short";
		$maxlength = 10;

		$result = $this->hostedFields->limit_length($value, $maxlength);

		$this->assertEquals($value, $result);
	}

	public function testLimitLengthWithLongString()
	{
		$value = "this is a very long string";
		$maxlength = 10;

		$result = $this->hostedFields->limit_length($value, $maxlength);

		$this->assertEquals("this is a ", $result);
	}

	public function testLimitLengthWithExactLengthString()
	{
		$value = "exactly10";
		$maxlength = 10;

		$result = $this->hostedFields->limit_length($value, $maxlength);

		$this->assertEquals($value, $result);
	}

	public function testLimitLengthWithEmptyString()
	{
		$value = "";
		$maxlength = 10;

		$result = $this->hostedFields->limit_length($value, $maxlength);

		$this->assertEquals($value, $result);
	}

	public function testLimitLengthWithDefaultMaxLength()
	{
		$value = "this string is longer than 100 characters and should be truncated to the default maximum length of 100 characters.";

		$result = $this->hostedFields->limit_length($value);

		$this->assertEquals(substr($value, 0, 100), $result);
	}

}
