<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use PHPUnit\Framework\TestCase;

class PayplugGatewayOney3x_test extends TestCase
{
    private array $base_settings = [
        'enabled' => true,
        'mode' => false,
        'payment_methods' => [
            'configuration' => [
                'oney' => [
                    'active' => true,
                    'with_fees' => true,
                    'min_amounts' => ['EUR' => 100],
                    'max_amounts' => ['EUR' => 300000],
                    'allowed_countries' => ['FR'],
                    'custom_amounts' => '{"min":100,"max":300000}',
                ],
            ],
        ],
    ];

    protected function tearDown(): void
    {
        remove_all_filters('payplug_gateway_payment_data');
        delete_option('woocommerce_payplug_settings');
        delete_transient('payplug_config_test');
        parent::tearDown();
    }

    /**
     * min_amounts/max_amounts (account, in cents) drive $gateway->min_oney_price/max_oney_price
     * via AmountHelper::fromCents() in set_oney_configuration() - validate_order_amount()
     * exercises that conversion end-to-end (PRE-3634: UPC AmountHelper migration).
     */
    private function withAccountThresholds(int $min_cents, int $max_cents): void
    {
        set_transient('payplug_config_test', [
            'configuration' => [
                'oney' => [
                    'min_amounts' => ['EUR' => $min_cents],
                    'max_amounts' => ['EUR' => $max_cents],
                    'allowed_countries' => ['FR'],
                ],
            ],
        ]);
    }

    public function test_validate_order_amount_within_account_thresholds_is_accepted(): void
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $this->withAccountThresholds(10000, 300000); // 100.00€ - 3000.00€

        $gateway = new PayplugGatewayOney3x();

        self::assertSame(150000, $gateway->validate_order_amount(150000));
    }

    public function test_validate_order_amount_below_account_minimum_is_rejected(): void
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $this->withAccountThresholds(10000, 300000); // 100.00€ - 3000.00€

        $gateway = new PayplugGatewayOney3x();

        self::assertInstanceOf(\WP_Error::class, $gateway->validate_order_amount(9999));
    }

    public function test_validate_order_amount_above_account_maximum_is_rejected(): void
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $this->withAccountThresholds(10000, 300000); // 100.00€ - 3000.00€

        $gateway = new PayplugGatewayOney3x();

        self::assertInstanceOf(\WP_Error::class, $gateway->validate_order_amount(300001));
    }

    public function test_validate_order_amount_at_exact_boundaries_is_accepted(): void
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $this->withAccountThresholds(10000, 300000); // 100.00€ - 3000.00€

        $gateway = new PayplugGatewayOney3x();

        self::assertSame(10000, $gateway->validate_order_amount(10000));
        self::assertSame(300000, $gateway->validate_order_amount(300000));
    }

    public function test_gateway_disabled_on_wc_payments_page_when_oney_inactive(): void
    {
        // PayplugGateway::__construct() (the top of this class's parent chain) sets
        // $this->id = 'payplug' and calls init_settings() - which reads
        // woocommerce_payplug_settings - before PayplugGatewayOney3x::__construct() reassigns
        // $this->id to 'oney_x3_with_fees'. So $this->enabled is never sourced from a
        // per-gateway woocommerce_oney_x3_with_fees_settings option; only checkGateway()'s
        // read of payment_methods.configuration.oney.active (from woocommerce_payplug_settings)
        // controls it here - that's what this asserts (PRE-3597 review).
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['oney']['active'] = false;
        update_option('woocommerce_payplug_settings', $settings);

        $gateway = new PayplugGatewayOney3x();

        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_enabled_when_oney_active(): void
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);

        $gateway = new PayplugGatewayOney3x();

        self::assertSame('yes', $gateway->enabled);
    }

    /**
     * @dataProvider rejected_mobile_provider
     */
    public function test_payment_is_refused_without_a_mobile_of_the_billing_country(string $phone, string $country): void
    {
        $payment_data = null;
        $message = $this->process_standard_payment($phone, $country, $payment_data);

        self::assertSame('Mobile phone number fullfilled is invalid. Please retry.', $message);
        self::assertNull($payment_data);
    }

    public function rejected_mobile_provider(): array
    {
        return [
            'BE mobile on a FR address' => ['+32470123456', 'FR'],
            'FR mobile on a DE address' => ['+33612345678', 'DE'],
            'not a number' => ['abc', 'FR'],
            'FR landline' => ['0171254015', 'FR'],
            'empty phone' => ['', 'FR'],
        ];
    }

    /**
     * @dataProvider accepted_mobile_provider
     */
    public function test_payment_carries_a_mobile_of_the_billing_country_or_fr_for_a_territory(
        string $phone,
        string $country,
        string $e164
    ): void {
        $payment_data = null;
        $this->process_standard_payment($phone, $country, $payment_data);

        self::assertSame($e164, $payment_data['billing']['mobile_phone_number']);
        self::assertSame($e164, $payment_data['shipping']['mobile_phone_number']);
    }

    public function accepted_mobile_provider(): array
    {
        return [
            'FR mobile on a FR address' => ['06 12 34 56 78', 'FR', '+33612345678'],
            'metropolitan mobile on a MQ address' => ['07 81 23 45 67', 'MQ', '+33781234567'],
            'MQ mobile on a MQ address' => ['0696 12 34 56', 'MQ', '+596696123456'],
        ];
    }

    /**
     * @param array|null $payment_data set to the payment data built for the API, if any
     *
     * @return string message of the exception ending process_standard_payment()
     */
    private function process_standard_payment(string $phone, string $country, &$payment_data): string
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);
        add_filter('payplug_gateway_payment_data', function ($data) use (&$payment_data): void {
            $payment_data = $data;

            throw new \RuntimeException('Payment creation stopped by the test.');
        });
        $gateway = new class () extends PayplugGatewayOney3x {
            public function check_oney_is_available()
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
