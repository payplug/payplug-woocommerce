<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\Oney\OneyDisplay;
use PHPUnit\Framework\TestCase;

class OneyDisplay_test extends TestCase
{
    private OneyDisplay $display;

    /** @var mixed */
    private $original_currency;

    private \WC_Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original_currency = get_option('woocommerce_currency');

        update_option('woocommerce_payplug_settings', [
            'enabled' => true,
            'mode' => false,
            'payment_methods' => [
                'configuration' => [
                    'oney' => [
                        'active' => true,
                        'with_fees' => true,
                        'cta_product' => true,
                        'custom_amounts' => '{"min":10000,"max":300000}',
                    ],
                ],
            ],
        ]);
        set_transient('payplug_config_test', [
            'configuration' => [
                'oney' => [
                    'min_amounts' => ['EUR' => 10000],
                    'max_amounts' => ['EUR' => 300000],
                    'allowed_countries' => ['ALL'],
                    'merchant_guid' => 'guid_test_123',
                ],
            ],
        ]);

        $this->product = \WC_Helper_Product::create_simple_product(true, ['regular_price' => 150, 'price' => 150]);
        $this->display = new OneyDisplay();
        wp_scripts()->add_data('payplug-oney', 'data', '');
    }

    protected function tearDown(): void
    {
        remove_action('wp_enqueue_scripts', [$this->display, 'enqueue_assets']);
        remove_action('woocommerce_cart_totals_after_order_total', [$this->display, 'show_on_cart']);
        remove_action('woocommerce_before_add_to_cart_form', [$this->display, 'show_on_product']);
        remove_all_filters('woocommerce_is_cart');
        wp_dequeue_style('payplug-oney');
        wp_dequeue_script('payplug-oney');

        if (null !== WC()->cart) {
            WC()->cart->empty_cart();
        }
        $this->product->delete(true);
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
        unset($GLOBALS['product']);

        update_option('woocommerce_currency', $this->original_currency);
        delete_option('woocommerce_payplug_settings');
        delete_transient('payplug_config_test');
        wp_scripts()->add_data('payplug-oney', 'data', '');

        parent::tearDown();
    }

    private function renderCart(): string
    {
        add_filter('woocommerce_is_cart', '__return_true');
        if (null === WC()->cart) {
            wc_load_cart();
        }
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($this->product->get_id());
        WC()->cart->calculate_totals();

        ob_start();
        $this->display->show_on_cart();

        return (string) ob_get_clean();
    }

    private function renderProduct(): string
    {
        $GLOBALS['wp_query'] = new \WP_Query(['p' => $this->product->get_id(), 'post_type' => 'product']);
        $GLOBALS['product'] = $this->product;

        ob_start();
        $this->display->show_on_product();

        return (string) ob_get_clean();
    }

    public function test_cart_badge_is_shown_on_a_eur_shop(): void
    {
        update_option('woocommerce_currency', 'EUR');

        self::assertStringContainsString('class="payplug-oney"', $this->renderCart());
    }

    public function test_product_badge_is_shown_on_a_eur_shop(): void
    {
        update_option('woocommerce_currency', 'EUR');

        self::assertStringContainsString('class="payplug-oney"', $this->renderProduct());
    }

    public function test_cart_badge_is_hidden_on_a_non_eur_shop(): void
    {
        update_option('woocommerce_currency', 'USD');

        self::assertSame('', $this->renderCart());
        self::assertStringNotContainsString('payplug_oney_config', (string) wp_scripts()->get_data('payplug-oney', 'data'));
    }

    public function test_product_badge_is_hidden_on_a_non_eur_shop(): void
    {
        update_option('woocommerce_currency', 'USD');

        self::assertSame('', $this->renderProduct());
        self::assertStringNotContainsString('payplug_oney_config', (string) wp_scripts()->get_data('payplug-oney', 'data'));
    }

    public function test_badge_follows_a_currency_switch_between_two_renders(): void
    {
        update_option('woocommerce_currency', 'EUR');
        self::assertStringContainsString('class="payplug-oney"', $this->renderProduct());

        update_option('woocommerce_currency', 'USD');
        self::assertSame('', $this->renderProduct());
    }

    public function test_assets_are_enqueued_on_a_eur_shop(): void
    {
        update_option('woocommerce_currency', 'EUR');

        $this->display->enqueue_assets();

        self::assertTrue(wp_style_is('payplug-oney', 'enqueued'));
        self::assertTrue(wp_script_is('payplug-oney', 'enqueued'));
    }

    public function test_assets_are_not_enqueued_on_a_non_eur_shop(): void
    {
        update_option('woocommerce_currency', 'USD');

        $this->display->enqueue_assets();

        self::assertFalse(wp_style_is('payplug-oney', 'enqueued'));
        self::assertFalse(wp_script_is('payplug-oney', 'enqueued'));
    }
}
