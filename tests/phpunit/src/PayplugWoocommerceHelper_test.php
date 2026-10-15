<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use PHPUnit\Framework\TestCase;

class PayplugWoocommerceHelper_test extends TestCase
{
    protected function tearDown(): void
    {
        delete_option('woocommerce_currency');
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
}
