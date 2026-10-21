<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\Bancontact;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Satispay;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Scalapay;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class PproCheckout_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        // A logged-in gateway otherwise asks the PayPlug API for the account permissions.
        set_transient('payplug_permission_live', ['use_live_mode' => true, 'can_save_cards' => false], DAY_IN_SECONDS);
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_payplug_settings', $this->settings());
    }

    protected function tearDown(): void
    {
        delete_transient('payplug_permission_live');
        delete_option('woocommerce_currency');
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    /**
     * @dataProvider cart_amounts
     */
    public function test_amount_bounds_are_inclusive(string $price, bool $shown): void
    {
        $this->cart($price, 'BE');
        $gateway = new Bancontact();

        self::assertSame($shown, array_key_exists('bancontact', $gateway->check_gateway(['bancontact' => $gateway])));
    }

    public function cart_amounts(): array
    {
        return [
            'one cent below the min' => ['0.29', false],
            'exactly the min' => ['0.30', true],
            'exactly the max' => ['20000', true],
            'one cent above the max' => ['20000.01', false],
        ];
    }

    public function test_billing_country_outside_the_api_list_hides_the_method(): void
    {
        $gateway = new Satispay();

        $this->cart('10', 'FR');
        self::assertArrayNotHasKey('satispay', $gateway->check_gateway(['satispay' => $gateway]));

        WC()->customer->set_billing_country('IT');
        self::assertArrayHasKey('satispay', $gateway->check_gateway(['satispay' => $gateway]));
    }

    public function test_gateway_built_before_the_address_change_uses_the_new_country(): void
    {
        $this->cart('10', 'IT');
        $gateway = new Satispay();

        WC()->customer->set_billing_country('FR');

        self::assertArrayNotHasKey('satispay', $gateway->check_gateway(['satispay' => $gateway]));
    }

    public function test_bancontact_uses_the_billing_country_not_the_shipping_one(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['configuration']['bancontact']['custom_countries'] = '["BE"]';
        update_option('woocommerce_payplug_settings', $settings);
        $this->cart('10', 'FR');
        WC()->customer->set_shipping_country('BE');
        $gateway = new Bancontact();

        self::assertArrayNotHasKey('bancontact', $gateway->check_gateway(['bancontact' => $gateway]));

        WC()->customer->set_billing_country('BE');
        WC()->customer->set_shipping_country('FR');
        self::assertArrayHasKey('bancontact', $gateway->check_gateway(['bancontact' => $gateway]));
    }

    public function test_other_currency_hides_the_method(): void
    {
        update_option('woocommerce_currency', 'USD');
        $this->cart('10', 'BE');
        $gateway = new Bancontact();

        self::assertArrayNotHasKey('bancontact', $gateway->check_gateway(['bancontact' => $gateway]));
    }

    public function test_missing_billing_country_hides_the_method(): void
    {
        $this->cart('10', '');
        $gateway = new Bancontact();

        self::assertArrayNotHasKey('bancontact', $gateway->check_gateway(['bancontact' => $gateway]));
    }

    public function test_method_without_cached_limits_is_not_available(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['permissions']['satispay'] = [];
        update_option('woocommerce_payplug_settings', $settings);
        $this->cart('10', 'IT');
        $gateway = new Satispay();

        self::assertSame('no', $gateway->enabled);
        self::assertArrayNotHasKey('satispay', $gateway->check_gateway(['satispay' => $gateway]));
    }

    public function test_without_cart_nothing_is_filtered(): void
    {
        $gateway = new Bancontact();
        WC()->cart = null;

        self::assertArrayHasKey('bancontact', $gateway->check_gateway(['bancontact' => $gateway]));
    }

    public function test_merchant_range_replaces_the_hard_coded_scalapay_bounds(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['configuration']['scalapay']['custom_amounts'] = '{"min":10000}';
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new Scalapay();

        $this->cart('99.99', 'IT');
        self::assertArrayNotHasKey('scalapay', $gateway->check_gateway(['scalapay' => $gateway]));

        $this->cart('100', 'IT');
        self::assertArrayHasKey('scalapay', $gateway->check_gateway(['scalapay' => $gateway]));
    }

    public function test_ppro_rules_never_remove_other_gateways(): void
    {
        $this->cart('0.10', 'FR');
        $gateway = new Satispay();
        $others = [
            'payplug' => (object) ['id' => 'payplug'],
            'oney_x3_with_fees' => (object) ['id' => 'oney_x3_with_fees'],
            'apple_pay' => (object) ['id' => 'apple_pay'],
        ];

        $result = $gateway->check_gateway(['satispay' => $gateway] + $others);

        self::assertArrayNotHasKey('satispay', $result);
        self::assertArrayHasKey('payplug', $result);
        self::assertArrayHasKey('apple_pay', $result);
    }

    public function test_inconsistent_bounds_hide_the_method_with_a_warning_without_personal_data(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['configuration']['satispay']['custom_amounts'] = '{"min":5000,"max":1000}';
        update_option('woocommerce_payplug_settings', $settings);
        $this->cart('10', 'IT');
        $gateway = new Satispay();
        $log = GatewayLogSpy::start();

        try {
            $result = $gateway->check_gateway(['satispay' => $gateway]);
        } finally {
            $log->stop();
        }

        self::assertArrayNotHasKey('satispay', $result);
        $warnings = array_values(array_filter($log->entries, static fn (array $entry): bool => 'warning' === $entry['level']));
        self::assertCount(1, $warnings);
        self::assertStringContainsString('satispay', $warnings[0]['message']);
        self::assertStringNotContainsString('IT', $warnings[0]['message']);
    }

    private function cart(string $price, string $billing_country): void
    {
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->make_product($price)->get_id());
        WC()->customer->set_billing_country($billing_country);
        WC()->cart->calculate_totals();
    }

    private function settings(): array
    {
        $settings = [
            'enabled' => true,
            'mode' => true,
            'email' => 'merchant@example.test',
            'apm_cache_mode' => 'live',
            'api_key' => '{"live":"sk_live_x","test":"sk_test_x"}',
            'jwt' => '{}',
            'payment_methods' => ['permissions' => [], 'configuration' => []],
        ];
        $countries = [
            'bancontact' => '["ALL"]',
            'satispay' => '["IT"]',
            'scalapay' => '["FR","IT"]',
        ];
        foreach ($countries as $apm => $list) {
            $settings['payment_methods']['permissions'][$apm] = [
                'enabled' => true,
                'countries' => $list,
                'amounts' => 'scalapay' === $apm ? '{"min":{"EUR":500},"max":{"EUR":200000}}' : '{"min":{"EUR":30},"max":{"EUR":2000000}}',
            ];
            $settings['payment_methods']['configuration'][$apm] = ['active' => true, 'custom_amounts' => '{}', 'custom_countries' => ''];
        }
        $settings['payment_methods']['configuration']['oney'] = ['with_fees' => true, 'custom_amounts' => '{"min":10000, "max":300000}'];

        return $settings;
    }
}
