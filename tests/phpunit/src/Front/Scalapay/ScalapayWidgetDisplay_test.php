<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front\Scalapay;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Front\Scalapay\ScalapayWidgetDisplay;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScalapayWidgetFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\SubscriptionProductStub;
use PHPUnit\Framework\TestCase;

class ScalapayWidgetDisplay_test extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_default_country', 'FR');
        update_option('woocommerce_payplug_settings', $this->options());
    }

    protected function tearDown(): void
    {
        wp_dequeue_script(ScalapayWidgetDisplay::SCRIPT_HANDLE);
        wp_deregister_script(ScalapayWidgetDisplay::SCRIPT_HANDLE);
        wp_dequeue_script(ScalapayWidgetDisplay::REMOUNT_HANDLE);
        wp_deregister_script(ScalapayWidgetDisplay::REMOUNT_HANDLE);
        wp_deregister_style('woocommerce-smallscreen');
        remove_all_filters('payplug_scalapay_widget_should_render');
        remove_all_filters('payplug_scalapay_widget_locale');
        remove_all_filters('woocommerce_product_class');
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_currency');
        delete_option('woocommerce_default_country');
        parent::tearDown();
    }

    public function test_hooks_are_those_of_oney(): void
    {
        $display = new ScalapayWidgetDisplay();

        self::assertNotFalse(has_action('woocommerce_before_add_to_cart_form', [$display, 'show_on_product']));
        self::assertNotFalse(has_action('woocommerce_cart_totals_after_order_total', [$display, 'show_on_cart']));
        self::assertSame(20, has_action('wp_enqueue_scripts', [$display, 'enqueue_assets']));
        self::assertNotFalse(has_filter('script_loader_tag', [$display, 'module_script_tag']));

        remove_action('woocommerce_before_add_to_cart_form', [$display, 'show_on_product']);
        remove_action('woocommerce_cart_totals_after_order_total', [$display, 'show_on_cart']);
        remove_action('wp_enqueue_scripts', [$display, 'enqueue_assets'], 20);
        remove_filter('script_loader_tag', [$display, 'module_script_tag']);
    }

    public function test_enqueues_the_v5_script_without_version_on_a_configured_page(): void
    {
        $this->display()->enqueue_for('cart');

        self::assertTrue(wp_script_is(ScalapayWidgetDisplay::SCRIPT_HANDLE, 'enqueued'));
        $script = wp_scripts()->registered[ScalapayWidgetDisplay::SCRIPT_HANDLE];
        self::assertSame('https://cdn.scalapay.com/widget/v5/js/scalapay-widget.js', $script->src);
        self::assertNull($script->ver);
        self::assertFalse(wp_script_is(ScalapayWidgetDisplay::REMOUNT_HANDLE, 'enqueued'));
    }

    public function test_product_page_also_gets_the_remount_script(): void
    {
        $this->display()->enqueue_for('product');

        self::assertTrue(wp_script_is(ScalapayWidgetDisplay::SCRIPT_HANDLE, 'enqueued'));
        self::assertTrue(wp_script_is(ScalapayWidgetDisplay::REMOUNT_HANDLE, 'enqueued'));
    }

    /**
     * @dataProvider not_enqueued_provider
     */
    public function test_nothing_is_enqueued(?string $page, array $configuration, string $currency): void
    {
        update_option('woocommerce_currency', $currency);
        update_option('woocommerce_payplug_settings', $this->options($configuration));

        $this->display()->enqueue_for($page);

        self::assertFalse(wp_script_is(ScalapayWidgetDisplay::SCRIPT_HANDLE, 'enqueued'));
        self::assertFalse(wp_script_is(ScalapayWidgetDisplay::REMOUNT_HANDLE, 'enqueued'));
    }

    public function not_enqueued_provider(): array
    {
        return [
            'other page' => [null, [], 'EUR'],
            'page switched off' => ['checkout', ['widget_checkout' => false], 'EUR'],
            'scalapay inactive' => ['cart', ['active' => false], 'EUR'],
            'non euro shop' => ['cart', [], 'USD'],
        ];
    }

    public function test_module_type_is_added_to_the_widget_script_only(): void
    {
        $display = $this->display();
        $tag = "<script type='text/javascript' src='https://cdn.scalapay.com/widget/v5/js/scalapay-widget.js' id='payplug-scalapay-widget-js'></script>\n";

        self::assertSame(
            "<script type=\"module\" src='https://cdn.scalapay.com/widget/v5/js/scalapay-widget.js' id='payplug-scalapay-widget-js'></script>\n",
            $display->module_script_tag($tag, ScalapayWidgetDisplay::SCRIPT_HANDLE)
        );
        self::assertSame($tag, $display->module_script_tag($tag, 'jquery'));
    }

    public function test_module_type_is_added_once_without_a_type_attribute(): void
    {
        $display = $this->display();
        $tag = '<script src="https://cdn.scalapay.com/widget/v5/js/scalapay-widget.js" id="payplug-scalapay-widget-js"></script>';

        $module = $display->module_script_tag($tag, ScalapayWidgetDisplay::SCRIPT_HANDLE);

        self::assertSame('<script type="module" src="https://cdn.scalapay.com/widget/v5/js/scalapay-widget.js" id="payplug-scalapay-widget-js"></script>', $module);
        self::assertSame($module, $display->module_script_tag($module, ScalapayWidgetDisplay::SCRIPT_HANDLE));
    }

    public function test_product_widget_follows_the_displayed_price(): void
    {
        $html = ScalapayWidgetDisplay::product_markup($this->product('100'));

        self::assertStringStartsWith('<scalapay-widget merchant-token="tok_test_123" type="product" amount-selectors="', $html);
        self::assertStringEndsWith(' locale="en"></scalapay-widget>', $html);
    }

    /**
     * @dataProvider product_out_of_range_provider
     */
    public function test_no_product_widget_out_of_the_scalapay_range(string $price): void
    {
        self::assertSame('', ScalapayWidgetDisplay::product_markup($this->product($price)));
    }

    public function product_out_of_range_provider(): array
    {
        return [
            'below min' => ['4.99'],
            'above max' => ['2000.01'],
            'free' => ['0'],
        ];
    }

    /**
     * @dataProvider variable_product_provider
     */
    public function test_variable_product_widget_follows_its_price_range(array $prices, bool $renders): void
    {
        $html = ScalapayWidgetDisplay::product_markup($this->variable_product($prices));

        self::assertSame($renders, '' !== $html);
    }

    public function variable_product_provider(): array
    {
        return [
            'cheapest variation below min' => [['3', '100'], true],
            'dearest variation above max' => [['100', '2500'], true],
            'every variation below min' => [['2', '4.99'], false],
            'every variation above max' => [['2000.01', '2500'], false],
            'no variation' => [[], false],
        ];
    }

    /**
     * @dataProvider grouped_product_provider
     */
    public function test_grouped_product_widget_follows_the_price_range_of_its_children(array $prices, bool $renders): void
    {
        $children = array_map(function (string $price): int {
            $child = $this->product($price);
            $child->save();

            return $child->get_id();
        }, $prices);
        $grouped = new \WC_Product_Grouped();
        $grouped->set_children($children);
        $grouped->save();

        self::assertSame($renders, '' !== ScalapayWidgetDisplay::product_markup($grouped));
    }

    public function grouped_product_provider(): array
    {
        return [
            'one child inside the range' => [['3', '50'], true],
            'every child below min' => [['1', '4'], false],
            'every child above max' => [['2100', '3000'], false],
        ];
    }

    public function test_subscription_children_do_not_count_in_a_grouped_product(): void
    {
        $subscription = $this->product('100');
        $subscription->save();
        $subscription_id = $subscription->get_id();
        add_filter('woocommerce_product_class', static fn ($class, $type, $post_type, $id) => $subscription_id === (int) $id ? SubscriptionProductStub::class : $class, 10, 4);
        $grouped = new \WC_Product_Grouped();
        $grouped->set_children([$subscription_id]);
        $grouped->save();

        self::assertSame('', ScalapayWidgetDisplay::product_markup($grouped));
    }

    public function test_no_product_widget_for_a_subscription(): void
    {
        $product = \Mockery::mock(\WC_Product_Simple::class)->makePartial();
        $product->shouldReceive('get_type')->andReturn('subscription');
        $product->set_price('100');

        self::assertSame('', ScalapayWidgetDisplay::product_markup($product));
    }

    public function test_no_product_widget_for_a_shop_country_outside_scalapay(): void
    {
        update_option('woocommerce_default_country', 'US');

        self::assertSame('', ScalapayWidgetDisplay::product_markup($this->product('100')));
    }

    public function test_no_product_widget_without_a_token(): void
    {
        $options = $this->options();
        $options['payment_methods']['permissions']['scalapay']['merchant_token'] = '';
        update_option('woocommerce_payplug_settings', $options);

        self::assertSame('', ScalapayWidgetDisplay::product_markup($this->product('100')));
    }

    public function test_should_render_filter_can_hide_the_widget(): void
    {
        add_filter('payplug_scalapay_widget_should_render', static fn (bool $render, string $page): bool => 'product' !== $page, 10, 2);

        self::assertSame('', ScalapayWidgetDisplay::product_markup($this->product('100')));
        self::assertNotNull(ScalapayWidgetDisplay::configured_attributes('checkout'));
    }

    public function test_locale_filter_sets_the_widget_locale(): void
    {
        add_filter('payplug_scalapay_widget_locale', static fn (): string => 'it');

        self::assertStringContainsString(' locale="it"', ScalapayWidgetDisplay::product_markup($this->product('100')));
    }

    public function test_cart_widget_is_a_full_width_table_row(): void
    {
        self::assertSame('<tr><td colspan="2" class="payplug-scalapay-widget-cell"><scalapay-widget></scalapay-widget></td></tr>', ScalapayWidgetDisplay::cart_row('<scalapay-widget></scalapay-widget>'));
        self::assertSame('', ScalapayWidgetDisplay::cart_row(''));
    }

    public function test_cart_page_drops_the_small_screen_label_of_the_widget_cell(): void
    {
        wp_register_style('woocommerce-smallscreen', 'https://example.test/woocommerce-smallscreen.css');

        $this->display()->enqueue_for('cart');

        $css = implode('', (array) wp_styles()->get_data('woocommerce-smallscreen', 'after'));
        self::assertStringContainsString('td.payplug-scalapay-widget-cell::before{display:none}', $css);
        self::assertStringNotContainsString('scalapay-widget{', $css);
    }

    public function test_checkout_attributes_depend_on_the_settings_only(): void
    {
        $attributes = ScalapayWidgetDisplay::configured_attributes('checkout');

        self::assertSame('checkout', $attributes['type']);
        self::assertSame('tok_test_123', $attributes['merchant-token']);

        update_option('woocommerce_payplug_settings', $this->options(['widget_checkout' => false]));
        self::assertNull(ScalapayWidgetDisplay::configured_attributes('checkout'));
    }

    private function display(): ScalapayWidgetDisplay
    {
        return (new \ReflectionClass(ScalapayWidgetDisplay::class))->newInstanceWithoutConstructor();
    }

    private function product(string $price): \WC_Product_Simple
    {
        $product = new \WC_Product_Simple();
        $product->set_regular_price($price);
        $product->set_price($price);

        return $product;
    }

    /**
     * @param string[] $prices
     */
    private function variable_product(array $prices): \WC_Product_Variable
    {
        $product = new \WC_Product_Variable();
        $product->save();
        foreach ($prices as $price) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_regular_price($price);
            $variation->save();
        }
        \WC_Product_Variable::sync($product->get_id());

        return wc_get_product($product->get_id());
    }

    private function options(array $configuration = []): array
    {
        return ScalapayWidgetFixtures::options($configuration);
    }
}
