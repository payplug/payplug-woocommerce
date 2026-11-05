<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Upc;

use Payplug\PayplugWoocommerce\Upc\UpcOrderAmount;
use PHPUnit\Framework\TestCase;

class UpcOrderAmount_test extends TestCase
{
    /** @var \WC_Order|null */
    private $order;

    protected function tearDown(): void
    {
        if ($this->order instanceof \WC_Order) {
            $this->order->delete(true);
        }
        parent::tearDown();
    }

    private function create_order(float $total, string $currency): \WC_Order
    {
        $this->order = wc_create_order();
        $this->order->set_currency($currency);
        $this->order->set_total($total);
        $this->order->save();

        return $this->order;
    }

    public function test_two_and_zero_decimal_currencies_are_supported(): void
    {
        $this->assertTrue(UpcOrderAmount::is_supported_currency('EUR'));
        $this->assertTrue(UpcOrderAmount::is_supported_currency('USD'));
        $this->assertTrue(UpcOrderAmount::is_supported_currency('JPY'));
    }

    public function test_three_decimal_and_malformed_currencies_are_not_supported(): void
    {
        $this->assertFalse(UpcOrderAmount::is_supported_currency('KWD'));
        $this->assertFalse(UpcOrderAmount::is_supported_currency('BHD'));
        $this->assertFalse(UpcOrderAmount::is_supported_currency(''));
        $this->assertFalse(UpcOrderAmount::is_supported_currency('EURO'));
    }

    public function test_total_converts_a_two_decimal_order_total_to_cents(): void
    {
        $this->assertSame(1234, UpcOrderAmount::total($this->create_order(12.34, 'EUR')));
    }

    public function test_total_sends_a_zero_decimal_order_total_as_is(): void
    {
        $this->assertSame(1000, UpcOrderAmount::total($this->create_order(1000.0, 'JPY')));
    }

    public function test_total_returns_null_instead_of_throwing_for_a_three_decimal_currency(): void
    {
        $this->assertNull(UpcOrderAmount::total($this->create_order(12.345, 'KWD')));
    }
}
