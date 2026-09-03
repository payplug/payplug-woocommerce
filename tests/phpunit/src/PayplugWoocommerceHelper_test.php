<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use PHPUnit\Framework\TestCase;

class PayplugWoocommerceHelper_test extends TestCase
{
    /**
     * get_payplug_amount() delegates to AmountHelper::toCents() (PRE-3634: UPC AmountHelper
     * migration) - covers the null pass-through kept from the original implementation, plus
     * the standard and half-cent-boundary rounding cases.
     */
    public function test_get_payplug_amount_converts_euros_to_cents(): void
    {
        self::assertSame(4999, PayplugWoocommerceHelper::get_payplug_amount(49.99));
    }

    public function test_get_payplug_amount_returns_null_unchanged(): void
    {
        self::assertNull(PayplugWoocommerceHelper::get_payplug_amount(null));
    }

    public function test_get_payplug_amount_rounds_zero(): void
    {
        self::assertSame(0, PayplugWoocommerceHelper::get_payplug_amount(0));
    }

    public function test_get_payplug_amount_rounds_half_cent_up(): void
    {
        self::assertSame(2000, PayplugWoocommerceHelper::get_payplug_amount(19.995));
    }
}
