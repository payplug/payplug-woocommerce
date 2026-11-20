<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugScalapay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\ScalapayWidgetCartIntegration;
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
        wp_deregister_script(ScalapayWidgetCartIntegration::HANDLE);
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
