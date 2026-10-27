<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\Bancontact;
use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3xWithoutFees;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney4x;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney4xWithoutFees;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Bizum;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Scalapay;
use PHPUnit\Framework\TestCase;

/**
 * Oney and Bizum send only phone numbers belonging to the billing country; every other
 * gateway sends any valid number.
 */
class PhoneRegionMatch_test extends TestCase
{
    /** @var array|null */
    private $payment_data;

    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_payplug_settings', [
            'enabled' => true,
            'mode' => false,
            'payment_methods' => [
                'configuration' => [
                    'payplug' => [
                        'active' => true,
                        'title' => 'Credit card checkout',
                        'description' => '',
                        'save_card' => false,
                        'embedded_mode' => 'redirect',
                    ],
                    'oney' => [
                        'active' => true,
                        'with_fees' => true,
                        'min_amounts' => ['EUR' => 100],
                        'max_amounts' => ['EUR' => 300000],
                        'allowed_countries' => ['FR'],
                        'custom_amounts' => '{"min":100,"max":300000}',
                    ],
                    'bancontact' => ['active' => false],
                    'scalapay' => ['active' => false],
                    'bizum' => ['active' => false],
                ],
            ],
        ]);

        $this->payment_data = null;
        add_filter('payplug_gateway_payment_data', function ($payment_data): void {
            $this->payment_data = $payment_data;

            throw new \RuntimeException('Payment creation stopped by the test.');
        });
    }

    protected function tearDown(): void
    {
        remove_all_filters('payplug_gateway_payment_data');
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    /**
     * @dataProvider gateway_provider
     */
    public function test_only_oney_and_bizum_require_the_phone_region_to_match(string $gateway_class, bool $expected): void
    {
        $gateway = new $gateway_class();

        self::assertSame($expected, $gateway->requires_phone_region_match());
    }

    public function gateway_provider(): array
    {
        return [
            'card' => [PayplugCreditCard::class, false],
            'base gateway' => [PayplugGateway::class, false],
            'Bancontact' => [Bancontact::class, false],
            'Scalapay' => [Scalapay::class, false],
            'Bizum' => [Bizum::class, true],
            'Oney 3x' => [PayplugGatewayOney3x::class, true],
            'Oney 4x' => [PayplugGatewayOney4x::class, true],
            'Oney 3x without fees' => [PayplugGatewayOney3xWithoutFees::class, true],
            'Oney 4x without fees' => [PayplugGatewayOney4xWithoutFees::class, true],
        ];
    }

    public function test_bizum_payment_carries_no_foreign_phone_number(): void
    {
        $gateway = new class () extends Bizum {
            public function checkGateway()
            {
                return true;
            }
        };

        $this->create_payment($gateway, '+32470123456', 'FR');

        self::assertArrayNotHasKey('mobile_phone_number', $this->payment_data['billing']);
        self::assertArrayNotHasKey('mobile_phone_number', $this->payment_data['shipping']);
        self::assertArrayNotHasKey('landline_phone_number', $this->payment_data['billing']);
    }

    public function test_bizum_payment_carries_a_phone_number_of_the_billing_country(): void
    {
        $gateway = new class () extends Bizum {
            public function checkGateway()
            {
                return true;
            }
        };

        $this->create_payment($gateway, '06 12 34 56 78', 'FR');

        self::assertSame('+33612345678', $this->payment_data['billing']['mobile_phone_number']);
    }

    public function test_other_generic_gateway_payment_carries_a_foreign_phone_number(): void
    {
        $gateway = new class () extends Bancontact {
            public function checkGateway()
            {
                return true;
            }
        };

        $this->create_payment($gateway, '+32470123456', 'FR');

        self::assertSame('+32470123456', $this->payment_data['billing']['mobile_phone_number']);
        self::assertSame('+32470123456', $this->payment_data['shipping']['mobile_phone_number']);
    }

    /**
     * @param PayplugGateway $gateway
     */
    private function create_payment($gateway, string $phone, string $country): void
    {
        $order = wc_create_order();
        $order->set_billing_first_name('Jane');
        $order->set_billing_last_name('Doe');
        $order->set_billing_email('jane@example.com');
        $order->set_billing_address_1('1 rue de la Paix');
        $order->set_billing_postcode('75000');
        $order->set_billing_city('Paris');
        $order->set_billing_country($country);
        $order->set_billing_phone($phone);
        $order->set_total(100);
        $order->save();

        try {
            $gateway->process_standard_payment($order, 10000, 0);
            $this->fail('The payment creation must be stopped by the payment data filter.');
        } catch (\Exception $e) {
            self::assertNotNull($this->payment_data, $e->getMessage());
        } finally {
            $order->delete(true);
        }
    }
}
