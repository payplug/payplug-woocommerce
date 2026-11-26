<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class PayplugGatewayAccountPage_test extends TestCase
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
     * @dataProvider cart_totals
     */
    public function test_account_page_keeps_payplug_whatever_the_cart_total(string $price): void
    {
        $this->cart($price);
        $this->on_account_page();
        $gateway = new PayplugCreditCard();

        self::assertArrayHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));
    }

    public function cart_totals(): array
    {
        return [
            'below the min' => ['0.10'],
            'one cent below the min' => ['0.29'],
            'exactly the min' => ['0.30'],
            'in range' => ['19.90'],
            'exactly the max' => ['20000'],
            'one cent above the max' => ['20000.01'],
        ];
    }

    public function test_account_page_keeps_payplug_with_an_empty_cart(): void
    {
        WC()->cart->empty_cart();
        $this->on_account_page();
        $gateway = new PayplugCreditCard();

        self::assertArrayHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));
    }

    public function test_account_page_keeps_payplug_with_an_empty_cart_in_another_currency(): void
    {
        update_option('woocommerce_currency', 'USD');
        WC()->cart->empty_cart();
        $this->on_account_page();
        $gateway = new PayplugCreditCard();

        self::assertArrayHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));
    }

    public function test_account_page_still_keeps_only_the_configured_oney_fees(): void
    {
        WC()->cart->empty_cart();
        $this->on_account_page();
        $gateway = new PayplugCreditCard();
        $oney = [
            'oney_x3_with_fees' => (object) ['id' => 'oney_x3_with_fees'],
            'oney_x3_without_fees' => (object) ['id' => 'oney_x3_without_fees'],
        ];

        $result = $gateway->check_gateway(['payplug' => $gateway] + $oney);

        self::assertArrayHasKey('oney_x3_with_fees', $result);
        self::assertArrayNotHasKey('oney_x3_without_fees', $result);
    }

    public function test_checkout_embedding_the_account_page_applies_the_min(): void
    {
        $this->cart('0.29');
        $this->on_account_page();
        add_filter('woocommerce_is_checkout', '__return_true');
        $gateway = new PayplugCreditCard();

        self::assertArrayNotHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));
    }

    /**
     * @dataProvider checkout_amounts
     */
    public function test_checkout_applies_the_min_and_max(string $price, bool $shown): void
    {
        $this->cart($price);
        $gateway = new PayplugCreditCard();

        self::assertSame($shown, array_key_exists('payplug', $gateway->check_gateway(['payplug' => $gateway])));
    }

    public function checkout_amounts(): array
    {
        return [
            'one cent below the min' => ['0.29', false],
            'exactly the min' => ['0.30', true],
            'in range' => ['19.90', true],
            'exactly the max' => ['20000', true],
            'one cent above the max' => ['20000.01', false],
        ];
    }

    public function test_checkout_hides_payplug_with_an_empty_cart(): void
    {
        WC()->cart->empty_cart();
        $gateway = new PayplugCreditCard();

        self::assertArrayNotHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));
    }

    public function test_order_pay_uses_the_order_total(): void
    {
        WC()->cart->empty_cart();
        $gateway = new PayplugCreditCard();

        set_query_var('order-pay', $this->make_order(['price' => '0.10', 'payment_method' => 'payplug'])->get_id());
        self::assertArrayNotHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));

        set_query_var('order-pay', $this->make_order(['price' => '10', 'payment_method' => 'payplug'])->get_id());
        self::assertArrayHasKey('payplug', $gateway->check_gateway(['payplug' => $gateway]));
    }

    private function on_account_page(): void
    {
        add_filter('woocommerce_is_account_page', '__return_true');
    }

    private function cart(string $price): void
    {
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->make_product($price)->get_id());
        WC()->cart->calculate_totals();
    }

    private function settings(): array
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
                    'payplug' => [
                        'enabled' => true,
                        'countries' => '["ALL"]',
                        'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
                    ],
                ],
                'configuration' => [
                    'payplug' => ['active' => true, 'custom_amounts' => '{}', 'custom_countries' => ''],
                    'oney' => ['with_fees' => true, 'custom_amounts' => '{"min":10000, "max":300000}'],
                ],
            ],
        ];
    }
}
