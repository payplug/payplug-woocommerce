<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway\Blocks;

use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugSatispay;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Satispay;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class PayplugGenericBlock_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        // A logged-in gateway otherwise asks the PayPlug API for the account permissions.
        set_transient('payplug_permission_live', ['use_live_mode' => true, 'can_save_cards' => false], DAY_IN_SECONDS);
        update_option('woocommerce_currency', 'EUR');
    }

    protected function tearDown(): void
    {
        delete_transient('payplug_permission_live');
        delete_option('woocommerce_currency');
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_restrictions_expose_the_effective_limits(): void
    {
        update_option('woocommerce_payplug_settings', $this->settings('{"min":1000}', '["IT"]'));

        $data = $this->block()->get_payment_method_data();

        self::assertSame(['min' => 1000, 'max' => 2000000, 'countries' => ['IT'], 'currency' => 'EUR', 'ok' => true], $data['ppro_restrictions']);
        self::assertArrayNotHasKey('allowed_country_codes', $data);
    }

    public function test_restrictions_without_cached_limits_are_not_ok(): void
    {
        $settings = $this->settings('{}', '');
        $settings['payment_methods']['permissions']['satispay'] = [];
        update_option('woocommerce_payplug_settings', $settings);

        $data = $this->block()->get_payment_method_data();

        self::assertFalse($data['ppro_restrictions']['ok']);
    }

    public function test_restrictions_with_inconsistent_merchant_bounds_are_not_ok(): void
    {
        update_option('woocommerce_payplug_settings', $this->settings('{"min":5000,"max":1000}', ''));

        self::assertFalse($this->block()->get_payment_method_data()['ppro_restrictions']['ok']);
    }

    public function test_api_countries_apply_without_merchant_selection(): void
    {
        update_option('woocommerce_payplug_settings', $this->settings('{}', ''));

        self::assertSame(['BE', 'FR', 'IT'], $this->block()->get_payment_method_data()['ppro_restrictions']['countries']);
    }

    /**
     * A block method whose is_active() is false is never registered client side, so it must not
     * depend on the cart total nor on the billing country.
     */
    public function test_restricted_block_activation_ignores_cart_and_country(): void
    {
        update_option('woocommerce_payplug_settings', $this->settings('{}', ''));
        WC()->cart->add_to_cart($this->make_product('0.10')->get_id());
        WC()->cart->calculate_totals();
        WC()->customer->set_billing_country('US');

        self::assertTrue($this->active($this->block()));
    }

    public function test_restricted_block_without_cached_limits_is_not_active(): void
    {
        $settings = $this->settings('{}', '');
        $settings['payment_methods']['permissions']['satispay'] = [];
        update_option('woocommerce_payplug_settings', $settings);

        self::assertFalse($this->active($this->block()));
    }

    private function block(): PayplugSatispay
    {
        $block = new PayplugSatispay();
        $gateway = new Satispay();
        \Closure::bind(static function () use ($block, $gateway): void {
            $block->gateway = $gateway;
        }, null, PayplugSatispay::class)();

        return $block;
    }

    private function active(PayplugSatispay $block): bool
    {
        return \Closure::bind(static fn (): bool => $block->ppro_block_active(), null, PayplugSatispay::class)();
    }

    private function settings(string $custom_amounts, string $custom_countries): array
    {
        return [
            'enabled' => true,
            'mode' => true,
            'email' => 'merchant@example.test',
            'apm_cache_mode' => 'live',
            'api_key' => '{"live":"sk_live_x","test":"sk_test_x"}',
            'jwt' => '{}',
            'payment_methods' => [
                'permissions' => [
                    'satispay' => [
                        'enabled' => true,
                        'countries' => '["BE","FR","IT"]',
                        'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
                    ],
                ],
                'configuration' => [
                    'satispay' => ['active' => true, 'custom_amounts' => $custom_amounts, 'custom_countries' => $custom_countries],
                    'payplug' => ['save_card' => false],
                    'oney' => ['with_fees' => true, 'custom_amounts' => '{"min":10000, "max":300000}'],
                ],
            ],
        ];
    }
}
