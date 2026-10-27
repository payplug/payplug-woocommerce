<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use PHPUnit\Framework\TestCase;

/**
 * Covers PayplugAddressData::prepare_address_data()'s phone handling.
 */
class PayplugAddressData_test extends TestCase
{
    /**
     * @param array<string, mixed> $overrides return values for get_billing_* / get_customer_id
     */
    private function mock_order(array $overrides = [])
    {
        $order = $this->createMock(\WC_Order::class);

        $values = array_merge([
            'get_customer_id' => 1,
            'get_billing_first_name' => 'Jane',
            'get_billing_last_name' => 'Doe',
            'get_billing_email' => 'jane@example.com',
            'get_billing_address_1' => '1 rue de la Paix',
            'get_billing_address_2' => '',
            'get_billing_postcode' => '75000',
            'get_billing_city' => 'Paris',
            'get_billing_country' => 'FR',
            'get_billing_company' => '',
            'get_billing_phone' => '',
            'needs_shipping_address' => false,
        ], $overrides);

        foreach ($values as $method => $value) {
            $order->method($method)->willReturn($value);
        }

        return $order;
    }

    public function test_mobile_number_sets_e164_mobile_field_on_billing_and_shipping(): void
    {
        $order = $this->mock_order(['get_billing_phone' => '0612345678']);

        $address_data = PayplugAddressData::from_order($order);

        self::assertSame('+33612345678', $address_data->get_billing()['mobile_phone_number']);
        self::assertSame('+33612345678', $address_data->get_shipping()['mobile_phone_number']);
        self::assertArrayNotHasKey('landline_phone_number', $address_data->get_billing());
    }

    public function test_landline_number_sets_e164_landline_field_on_billing_only(): void
    {
        // 01 71 25 40 15 - a Paris landline range, not a mobile number.
        $order = $this->mock_order(['get_billing_phone' => '0171254015']);

        $address_data = PayplugAddressData::from_order($order);

        self::assertSame('+33171254015', $address_data->get_billing()['landline_phone_number']);
        self::assertArrayNotHasKey('mobile_phone_number', $address_data->get_billing());
        self::assertArrayNotHasKey('mobile_phone_number', $address_data->get_shipping());
    }

    public function test_invalid_phone_number_is_silently_dropped(): void
    {
        $order = $this->mock_order(['get_billing_phone' => 'not-a-phone-number']);

        $address_data = PayplugAddressData::from_order($order);

        self::assertArrayNotHasKey('mobile_phone_number', $address_data->get_billing());
        self::assertArrayNotHasKey('landline_phone_number', $address_data->get_billing());
    }

    /**
     * @dataProvider non_strict_mobile_provider
     */
    public function test_valid_mobile_number_is_sent_in_its_own_country_when_region_match_is_not_required(
        string $phone,
        string $country,
        string $e164
    ): void {
        $order = $this->mock_order(['get_billing_phone' => $phone, 'get_billing_country' => $country]);

        $address_data = PayplugAddressData::from_order($order);

        self::assertSame($e164, $address_data->get_billing()['mobile_phone_number']);
        self::assertSame($e164, $address_data->get_shipping()['mobile_phone_number']);
        self::assertArrayNotHasKey('landline_phone_number', $address_data->get_billing());
    }

    public function non_strict_mobile_provider(): array
    {
        return [
            'BE mobile on a FR address' => ['+32470123456', 'FR', '+32470123456'],
            'FR mobile on a DE address' => ['+33612345678', 'DE', '+33612345678'],
            'FR mobile with 00 prefix on a BE address' => ['0033609860479', 'BE', '+33609860479'],
            'BE mobile with 00 prefix on a FR address' => ['0032 470 12 34 56', 'FR', '+32470123456'],
            'metropolitan mobile on a MQ address' => ['07 81 23 45 67', 'MQ', '+33781234567'],
            'MQ mobile on a MQ address' => ['0696 12 34 56', 'MQ', '+596696123456'],
            'GP/BL mobile on a BL address' => ['0690 12 34 56', 'BL', '+590690123456'],
        ];
    }

    public function test_foreign_number_is_not_sent_when_region_match_is_required(): void
    {
        $order = $this->mock_order(['get_billing_phone' => '+32470123456', 'get_billing_country' => 'FR']);

        $address_data = PayplugAddressData::from_order($order, true);

        $this->assert_no_phone_number($address_data);
    }

    public function test_metropolitan_mobile_on_a_territory_is_sent_when_region_match_is_required(): void
    {
        $order = $this->mock_order(['get_billing_phone' => '06 12 34 56 78', 'get_billing_country' => 'MQ']);

        $address_data = PayplugAddressData::from_order($order, true);

        self::assertSame('+33612345678', $address_data->get_billing()['mobile_phone_number']);
        self::assertSame('+33612345678', $address_data->get_shipping()['mobile_phone_number']);
    }

    public function test_landline_on_a_foreign_address_is_sent_on_billing_only_when_region_match_is_not_required(): void
    {
        $order = $this->mock_order(['get_billing_phone' => '+33171254015', 'get_billing_country' => 'DE']);

        $address_data = PayplugAddressData::from_order($order);

        self::assertSame('+33171254015', $address_data->get_billing()['landline_phone_number']);
        self::assertArrayNotHasKey('mobile_phone_number', $address_data->get_billing());
        self::assertArrayNotHasKey('mobile_phone_number', $address_data->get_shipping());
        self::assertArrayNotHasKey('landline_phone_number', $address_data->get_shipping());
    }

    /**
     * @dataProvider unusable_number_provider
     */
    public function test_unusable_number_is_not_sent_in_either_mode(string $phone, string $country): void
    {
        foreach ([false, true] as $require_phone_region_match) {
            $order = $this->mock_order(['get_billing_phone' => $phone, 'get_billing_country' => $country]);

            $address_data = PayplugAddressData::from_order($order, $require_phone_region_match);

            $this->assert_no_phone_number($address_data);
        }
    }

    public function unusable_number_provider(): array
    {
        return [
            'invalid IT number' => ['234234234', 'IT'],
            'not a number' => ['not-a-phone-number', 'FR'],
            'unknown country' => ['0612345678', 'ZZ'],
        ];
    }

    private function assert_no_phone_number(PayplugAddressData $address_data): void
    {
        foreach ([$address_data->get_billing(), $address_data->get_shipping()] as $address) {
            self::assertArrayNotHasKey('mobile_phone_number', $address);
            self::assertArrayNotHasKey('landline_phone_number', $address);
            self::assertNotContains('', $address);
        }
    }
}
