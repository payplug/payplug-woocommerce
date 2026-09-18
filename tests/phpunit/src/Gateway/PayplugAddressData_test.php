<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use PHPUnit\Framework\TestCase;

/**
 * Covers PayplugAddressData::prepare_address_data()'s phone handling, migrated from direct
 * libphonenumber calls to UPC's PhoneHelper (PRE-3634).
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

    public function test_number_valid_for_a_different_region_than_billing_country_is_dropped(): void
    {
        // A valid Belgian mobile number, but billing country is FR - PhoneHelper requires the
        // number's region to match $country, stricter than the pre-UPC libphonenumber check
        // (accept-list only checked isValidNumber()+getNumberType(), no region match).
        $order = $this->mock_order(['get_billing_phone' => '+32470123456', 'get_billing_country' => 'FR']);

        $address_data = PayplugAddressData::from_order($order);

        self::assertArrayNotHasKey('mobile_phone_number', $address_data->get_billing());
        self::assertArrayNotHasKey('landline_phone_number', $address_data->get_billing());
    }
}
