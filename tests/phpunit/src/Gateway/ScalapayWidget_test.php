<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Front\Scalapay\ScalapayWidgetDisplay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugScalapay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\ScalapayWidgetCartIntegration;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Satispay;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Scalapay;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScalapayWidgetFixtures;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Scalapay installment widget inside the Scalapay payment method (classic and Blocks checkout)
 * and in the Blocks cart.
 */
class ScalapayWidget_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    private const CHECKOUT_BLOCKS_HANDLE = 'wc-payplug-scalapay-blocks';

    /**
     * @var \WP_Query|null
     */
    private $original_wp_query;

    /**
     * @var int
     */
    private $cart_page_id = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        set_transient('payplug_permission_live', ['use_live_mode' => true, 'can_save_cards' => false], DAY_IN_SECONDS);
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_default_country', 'FR');
        update_option('woocommerce_payplug_settings', $this->settings());
    }

    protected function tearDown(): void
    {
        delete_transient('payplug_permission_live');
        remove_all_filters('woocommerce_is_checkout');
        remove_all_filters('payplug_scalapay_widget_should_render');
        unset($GLOBALS['wp']->query_vars['order-pay']);
        wp_deregister_script(ScalapayWidgetCartIntegration::HANDLE);
        wp_deregister_script(self::CHECKOUT_BLOCKS_HANDLE);
        wp_dequeue_script(ScalapayWidgetDisplay::SCRIPT_HANDLE);
        wp_deregister_script(ScalapayWidgetDisplay::SCRIPT_HANDLE);
        if ($this->cart_page_id > 0) {
            $GLOBALS['wp_query'] = $this->original_wp_query;
            wp_delete_post($this->cart_page_id, true);
            delete_option('woocommerce_cart_page_id');
        }
        delete_option('woocommerce_currency');
        delete_option('woocommerce_default_country');
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_classic_checkout_prints_the_widget_in_the_payment_method(): void
    {
        $gateway = new Scalapay();

        ob_start();
        $gateway->payment_fields();
        $html = (string) ob_get_clean();

        self::assertTrue($gateway->has_fields());
        self::assertStringStartsWith('<scalapay-widget merchant-token="tok_test_123" type="checkout" amount-selectors="', $html);
        self::assertStringEndsWith('</scalapay-widget>', $html);
        self::assertTrue(wp_script_is(ScalapayWidgetDisplay::SCRIPT_HANDLE, 'enqueued'));
    }

    public function test_classic_checkout_without_widget_has_no_payment_fields(): void
    {
        update_option('woocommerce_payplug_settings', $this->settings(['widget_checkout' => false]));
        $gateway = new Scalapay();

        ob_start();
        $gateway->payment_fields();
        $html = (string) ob_get_clean();

        self::assertFalse($gateway->has_fields());
        self::assertSame('', $html);
        self::assertFalse(wp_script_is(ScalapayWidgetDisplay::SCRIPT_HANDLE, 'enqueued'));
    }

    public function test_order_pay_page_has_no_widget(): void
    {
        add_filter('woocommerce_is_checkout', '__return_true');
        $GLOBALS['wp']->query_vars['order-pay'] = '42';
        $gateway = new Scalapay();

        ob_start();
        $gateway->payment_fields();
        $html = (string) ob_get_clean();

        self::assertFalse($gateway->has_fields());
        self::assertStringNotContainsString('scalapay-widget', $html);
        self::assertFalse(wp_script_is(ScalapayWidgetDisplay::SCRIPT_HANDLE, 'enqueued'));
    }

    public function test_classic_checkout_builds_the_widget_once_per_gateway(): void
    {
        $builds = 0;
        add_filter('payplug_scalapay_widget_should_render', static function (bool $renderable) use (&$builds): bool {
            ++$builds;

            return $renderable;
        });
        $gateway = new Scalapay();

        ob_start();
        $gateway->has_fields();
        $gateway->payment_fields();
        ob_end_clean();

        self::assertSame(1, $builds);
    }

    public function test_classic_checkout_icon_carries_the_class_kept_by_the_widget_title_rewrite(): void
    {
        $icon = (new Scalapay())->get_icon();

        self::assertStringContainsString('class="payplug-payment-icon scalapay-checkout-label__icon"', $icon);
        self::assertStringContainsString('/assets/images/checkout/scalapay.svg"', $icon);
    }

    public function test_other_gateway_icons_have_no_scalapay_label_class(): void
    {
        $icon = (new Satispay())->get_icon();

        self::assertStringContainsString('class="payplug-payment-icon"', $icon);
        self::assertStringNotContainsString('scalapay-checkout-label__icon', $icon);
    }

    public function test_classic_cart_prints_the_widget_row_for_an_eligible_cart(): void
    {
        $this->cart_page_with_a_product_at('100');

        $html = $this->classic_cart_totals_row();

        self::assertStringStartsWith('<tr><td colspan="2" class="payplug-scalapay-widget-cell"><scalapay-widget merchant-token="tok_test_123" type="cart" amount-selectors="', $html);
        self::assertStringEndsWith('</scalapay-widget></td></tr>', $html);
    }

    /**
     * @dataProvider classic_cart_without_widget_provider
     */
    public function test_classic_cart_prints_no_widget_row(string $price, array $configuration): void
    {
        update_option('woocommerce_payplug_settings', $this->settings($configuration));
        $this->cart_page_with_a_product_at($price);

        self::assertSame('', $this->classic_cart_totals_row());
    }

    public function classic_cart_without_widget_provider(): array
    {
        return [
            'cart above the scalapay maximum' => ['2000.01', []],
            'cart below the scalapay minimum' => ['4.99', []],
            'cart switched off' => ['100', ['widget_cart' => false]],
        ];
    }

    public function test_blocks_checkout_data_carries_the_widget_attributes(): void
    {
        $widget = $this->block()->get_payment_method_data()['widget'];

        self::assertSame(['merchant-token', 'type', 'amount-selectors', 'locale'], array_keys($widget));
        self::assertSame('tok_test_123', $widget['merchant-token']);
        self::assertSame('checkout', $widget['type']);
    }

    /**
     * @dataProvider no_widget_provider
     */
    public function test_blocks_checkout_data_has_no_widget(array $configuration, string $token): void
    {
        update_option('woocommerce_payplug_settings', $this->settings($configuration, $token));

        self::assertNull($this->block()->get_payment_method_data()['widget']);
    }

    public function no_widget_provider(): array
    {
        return [
            'checkout switched off' => [['widget_checkout' => false], 'tok_test_123'],
            'no token' => [[], ''],
        ];
    }

    public function test_blocks_cart_data_carries_the_widget_and_the_restrictions(): void
    {
        wp_register_script(ScalapayWidgetCartIntegration::HANDLE, 'https://example.test/cart-blocks.js');
        $integration = new ScalapayWidgetCartIntegration();

        $data = $integration->get_script_data();

        self::assertSame('payplug_scalapay_widget_cart', $integration->get_name());
        self::assertSame('cart', $data['widget']['type']);
        self::assertSame(['min' => 500, 'max' => 200000, 'countries' => ['FR', 'IT'], 'currency' => 'EUR', 'ok' => true], $data['restrictions']);
        self::assertSame('FR', $data['country']);
        self::assertSame([ScalapayWidgetCartIntegration::HANDLE], $integration->get_script_handles());
        self::assertSame([], $integration->get_editor_script_handles());
    }

    public function test_blocks_cart_loads_no_unregistered_script(): void
    {
        wp_deregister_script(ScalapayWidgetCartIntegration::HANDLE);

        self::assertSame([], (new ScalapayWidgetCartIntegration())->get_script_handles());
    }

    public function test_blocks_cart_loads_nothing_when_the_cart_widget_is_off(): void
    {
        wp_register_script(ScalapayWidgetCartIntegration::HANDLE, 'https://example.test/cart-blocks.js');
        update_option('woocommerce_payplug_settings', $this->settings(['widget_cart' => false]));
        $integration = new ScalapayWidgetCartIntegration();

        self::assertNull($integration->get_script_data()['widget']);
        self::assertSame([], $integration->get_script_handles());
    }

    public function test_blocks_checkout_registers_the_shipped_bundle_with_its_dependencies(): void
    {
        $asset = require PAYPLUG_GATEWAY_PLUGIN_DIR . 'assets/js/blocks/wc-payplug-scalapay-blocks.asset.php';

        self::assertSame([self::CHECKOUT_BLOCKS_HANDLE], $this->block()->get_payment_method_script_handles());

        $script = wp_scripts()->registered[self::CHECKOUT_BLOCKS_HANDLE];
        self::assertSame($asset['dependencies'], $script->deps);
        self::assertSame($asset['version'], $script->ver);
        self::assertStringContainsString('scalapay-widget', (string) file_get_contents(PAYPLUG_GATEWAY_PLUGIN_DIR . 'assets/js/blocks/wc-payplug-scalapay-blocks.js'));
    }

    public function test_blocks_cart_registers_the_shipped_bundle(): void
    {
        wp_deregister_script(ScalapayWidgetCartIntegration::HANDLE);
        $integration = new ScalapayWidgetCartIntegration();

        $integration->initialize();

        self::assertSame([ScalapayWidgetCartIntegration::HANDLE], $integration->get_script_handles());
        $deps = wp_scripts()->registered[ScalapayWidgetCartIntegration::HANDLE]->deps;
        self::assertContains('wc-blocks-checkout', $deps);
        self::assertContains('wp-plugins', $deps);
        self::assertContains('wc-settings', $deps);
        self::assertStringContainsString('scalapay-widget', (string) file_get_contents(PAYPLUG_GATEWAY_PLUGIN_DIR . 'assets/js/blocks/wc-payplug-scalapay_widget_cart-blocks.js'));
    }

    private function cart_page_with_a_product_at(string $price): void
    {
        WC()->cart->add_to_cart($this->make_product($price)->get_id());
        WC()->customer->set_billing_country('FR');
        WC()->cart->calculate_totals();

        $this->cart_page_id = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Cart']);
        update_option('woocommerce_cart_page_id', $this->cart_page_id);
        $this->original_wp_query = $GLOBALS['wp_query'];
        $GLOBALS['wp_query'] = new \WP_Query(['page_id' => $this->cart_page_id]);
    }

    private function classic_cart_totals_row(): string
    {
        $display = (new \ReflectionClass(ScalapayWidgetDisplay::class))->newInstanceWithoutConstructor();

        ob_start();
        $display->show_on_cart();

        return (string) ob_get_clean();
    }

    private function block(): PayplugScalapay
    {
        $block = new PayplugScalapay();
        $gateway = new Scalapay();
        \Closure::bind(static function () use ($block, $gateway): void {
            $block->gateway = $gateway;
        }, null, PayplugScalapay::class)();

        return $block;
    }

    private function settings(array $configuration = [], string $token = ScalapayWidgetFixtures::TOKEN): array
    {
        $options = ScalapayWidgetFixtures::options($configuration, $token);
        $options['email'] = 'merchant@example.test';
        $options['apm_cache_mode'] = 'live';
        $options['api_key'] = '{"live":"sk_live_x","test":"sk_test_x"}';
        $options['jwt'] = '{}';
        $options['payment_methods']['configuration']['payplug'] = ['save_card' => false];
        $options['payment_methods']['configuration']['oney'] = ['with_fees' => true, 'custom_amounts' => '{"min":10000, "max":300000}'];

        return $options;
    }
}
