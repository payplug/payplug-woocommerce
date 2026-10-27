<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PPRO\Scalapay;
use PHPUnit\Framework\TestCase;

/**
 * Scalapay requires a valid mobile phone number, of any country.
 */
class ScalapayPhone_test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_payplug_settings', [
            'enabled' => true,
            'mode' => false,
            'payment_methods' => [
                'configuration' => [
                    'payplug' => ['active' => true, 'embedded_mode' => 'redirect'],
                    'scalapay' => ['active' => true],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        remove_all_filters('payplug_gateway_payment_data');
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    /**
     * @dataProvider accepted_mobile_provider
     */
    public function test_payment_carries_a_valid_mobile_of_any_country(string $phone, string $country, string $e164): void
    {
        $payment_data = null;
        $this->process_standard_payment($phone, $country, $payment_data);

        self::assertSame($e164, $payment_data['billing']['mobile_phone_number']);
        self::assertSame($e164, $payment_data['shipping']['mobile_phone_number']);
    }

    public function accepted_mobile_provider(): array
    {
        return [
            'FR mobile on a FR address' => ['06 12 34 56 78', 'FR', '+33612345678'],
            'BE mobile on a FR address' => ['+32470123456', 'FR', '+32470123456'],
            'metropolitan mobile on a MQ address' => ['07 81 23 45 67', 'MQ', '+33781234567'],
        ];
    }

    /**
     * @dataProvider rejected_mobile_provider
     */
    public function test_payment_is_refused_without_a_valid_mobile(string $phone, string $country): void
    {
        $payment_data = null;
        $message = $this->process_standard_payment($phone, $country, $payment_data);

        self::assertSame('Mobile phone number fullfilled is invalid. Please retry.', $message);
        self::assertNull($payment_data);
    }

    public function rejected_mobile_provider(): array
    {
        return [
            'invalid IT number' => ['234234234', 'IT'],
            'not a number' => ['abc', 'FR'],
            'FR landline' => ['0171254015', 'FR'],
        ];
    }

    /**
     * @param array|null $payment_data set to the payment data built for the API, if any
     *
     * @return string message of the exception ending process_standard_payment()
     */
    private function process_standard_payment(string $phone, string $country, &$payment_data): string
    {
        add_filter('payplug_gateway_payment_data', function ($data) use (&$payment_data): void {
            $payment_data = $data;

            throw new \RuntimeException('Payment creation stopped by the test.');
        });
        $gateway = new class () extends Scalapay {
            public function checkGateway()
            {
                return true;
            }
        };

        $order = wc_create_order();
        $order->set_billing_first_name('Jane');
        $order->set_billing_last_name('Doe');
        $order->set_billing_email('jane@example.com');
        $order->set_billing_country($country);
        $order->set_billing_phone($phone);
        $order->save();

        try {
            $gateway->process_standard_payment($order, 15000, 0);
            $this->fail('process_standard_payment() must end with an exception in this test.');
        } catch (\Exception $e) {
            return $e->getMessage();
        } finally {
            $order->delete(true);
        }
    }
}
