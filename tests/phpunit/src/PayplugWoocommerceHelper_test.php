<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use PHPUnit\Framework\TestCase;

class PayplugWoocommerceHelper_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option('woocommerce_currency');
        delete_option('woocommerce_payplug_settings');
        parent::tearDown();
    }

    /**
     * get_payplug_amount() delegates to AmountHelper::toCents() (PRE-3634: UPC AmountHelper
     * migration; PRE-3727: currency-aware) - covers the null pass-through kept from the original
     * implementation, the standard and half-cent-boundary rounding cases, a zero-decimal currency
     * and the shop-currency default.
     */
    public function test_get_payplug_amount_converts_euros_to_cents(): void
    {
        self::assertSame(4999, PayplugWoocommerceHelper::get_payplug_amount(49.99, 'EUR'));
    }

    public function test_get_payplug_amount_sends_a_zero_decimal_currency_as_is(): void
    {
        self::assertSame(1000, PayplugWoocommerceHelper::get_payplug_amount(1000, 'JPY'));
    }

    public function test_get_payplug_amount_defaults_to_the_shop_currency(): void
    {
        update_option('woocommerce_currency', 'JPY');
        self::assertSame(1000, PayplugWoocommerceHelper::get_payplug_amount(1000));

        update_option('woocommerce_currency', 'USD');
        self::assertSame(100000, PayplugWoocommerceHelper::get_payplug_amount(1000));
    }

    public function test_get_payplug_amount_returns_null_unchanged(): void
    {
        self::assertNull(PayplugWoocommerceHelper::get_payplug_amount(null, 'EUR'));
    }

    public function test_get_payplug_amount_rounds_zero(): void
    {
        self::assertSame(0, PayplugWoocommerceHelper::get_payplug_amount(0, 'EUR'));
    }

    public function test_get_payplug_amount_rounds_half_cent_up(): void
    {
        self::assertSame(2000, PayplugWoocommerceHelper::get_payplug_amount(19.995, 'EUR'));
    }

    private function seed_oauth_client_data(bool $live, string $oauth_client_data): void
    {
        update_option('woocommerce_payplug_settings', [
            'mode' => $live,
            'oauth_client_data' => $oauth_client_data,
        ]);
    }

    public function test_is_oauth2_connected_when_the_stored_mode_has_client_credentials(): void
    {
        $this->seed_oauth_client_data(true, '{"live":{"client_id":"id","client_secret":"secret"},"test":[]}');

        self::assertTrue(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_oauth2_connected_checks_the_requested_mode_only(): void
    {
        $this->seed_oauth_client_data(true, '{"live":[],"test":{"client_id":"id","client_secret":"secret"}}');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected(true));
        self::assertTrue(PayplugWoocommerceHelper::is_oauth2_connected(false));
    }

    public function test_is_not_oauth2_connected_with_the_classic_login_default(): void
    {
        $this->seed_oauth_client_data(false, '{}');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_not_oauth2_connected_when_the_client_secret_is_missing(): void
    {
        $this->seed_oauth_client_data(false, '{"test":{"client_id":"id","client_secret":""}}');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_not_oauth2_connected_when_the_oauth_client_data_is_malformed(): void
    {
        $this->seed_oauth_client_data(false, 'not json');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_not_oauth2_connected_without_settings(): void
    {
        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }
}
