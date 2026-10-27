<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\Mcp;
use PHPUnit\Framework\TestCase;

/**
 * Covers Mcp::normalizePhoneNumber(). The method is protected - invoked via reflection, as it
 * has no WordPress/order dependency of its own.
 */
class Mcp_test extends TestCase
{
    /**
     * @return mixed
     */
    private function normalizePhoneNumber($phone_number, $country)
    {
        $mcp = (new \ReflectionClass(Mcp::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass(Mcp::class))->getMethod('normalizePhoneNumber');
        $method->setAccessible(true);

        return $method->invoke($mcp, $phone_number, $country);
    }

    public function test_valid_mobile_number_is_normalized_to_e164(): void
    {
        self::assertSame('+33612345678', $this->normalizePhoneNumber('0612345678', 'FR'));
    }

    public function test_valid_landline_number_is_also_normalized_to_e164(): void
    {
        // normalizePhoneNumber() doesn't require the number to be mobile - unlike Oney3x/
        // Scalapay's checkout gate, MCP payment links just need a formattable number.
        self::assertSame('+33171254015', $this->normalizePhoneNumber('0171254015', 'FR'));
    }

    public function test_unparseable_number_returns_null(): void
    {
        self::assertNull($this->normalizePhoneNumber('not-a-phone-number', 'FR'));
    }

    public function test_non_string_phone_number_returns_null(): void
    {
        self::assertNull($this->normalizePhoneNumber(12345, 'FR'));
    }

    public function test_empty_country_returns_null(): void
    {
        self::assertNull($this->normalizePhoneNumber('0612345678', ''));
    }

    /**
     * @dataProvider foreign_and_overseas_number_provider
     */
    public function test_valid_number_of_another_country_is_normalized_to_e164(string $phone, string $country, string $e164): void
    {
        self::assertSame($e164, $this->normalizePhoneNumber($phone, $country));
    }

    public function foreign_and_overseas_number_provider(): array
    {
        return [
            'BE mobile on a FR address' => ['+32470123456', 'FR', '+32470123456'],
            'FR mobile with 00 prefix on a BE address' => ['0033609860479', 'BE', '+33609860479'],
            'metropolitan mobile on a MQ address' => ['07 81 23 45 67', 'MQ', '+33781234567'],
            'MQ mobile on a MQ address' => ['0696 12 34 56', 'MQ', '+596696123456'],
        ];
    }

    public function test_invalid_number_of_its_own_country_returns_null(): void
    {
        self::assertNull($this->normalizePhoneNumber('234234234', 'IT'));
    }
}
