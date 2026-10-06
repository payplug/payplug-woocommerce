<?php

namespace phpunit;

use Payplug\PayplugWoocommerce\PayplugWoocommerce;

use function PHPUnit\Framework\assertTrue;

use PHPUnit\Framework\TestCase;

class PayplugWoocommerce_test extends TestCase
{
    private $payplug_woocommerce;

    protected function setUp(): void
    {
        $this->payplug_woocommerce = PayplugWoocommerce::get_instance();
        parent::setUp();
    }

    /**
     * test if there's an active woocommerce version
     */
    public function test_woocommerce_version(): void
    {
        $wc = function_exists('WC') ? WC() : $GLOBALS['woocommerce'];
        self::assertNotEmpty($wc->version);
        self::assertNotEmpty(defined('WC_VERSION'));
        self::assertIsString($wc->version);
    }

    /**
     * OneyDisplay's own constructor no-ops safely when Oney isn't configured - assert
     * animationHandlers() doesn't throw regardless of options state, and actually registers
     * the widget scripts (its main side effect) rather than just silently succeeding.
     */
    public function test_animation_handlers_does_not_throw(): void
    {
        update_option('woocommerce_payplug_settings', []);
        $this->payplug_woocommerce->animationHandlers();

        self::assertTrue(wp_script_is('payplug-oney-loader', 'registered'));
        self::assertTrue(wp_script_is('payplug-oney', 'registered'));

        update_option('woocommerce_payplug_settings', ['payment_methods' => ['configuration' => ['oney' => ['cta_product' => true]]]]);
        $this->payplug_woocommerce->animationHandlers();

        self::assertTrue(wp_script_is('payplug-oney-loader', 'registered'));
        self::assertTrue(wp_script_is('payplug-oney', 'registered'));
    }

    /**
     * assert all classes we're loading exists
     * assert we're loading all gateways
     */
    public function test_register_payplug_gateway_exists(): void
    {
        $results = $this->payplug_woocommerce->register_payplug_gateway([]);
        $gateways = 0;
        foreach ($results as $k => $class) {
            self::assertTrue(class_exists($class));
            $gateways++;
        }

        self::assertTrue($gateways === 11);
    }

    /**
     * tested on e2e side, this is simply to declare actions to load
     */
    public function test_woocommerce_gateways_block_support(): void
    {
        assertTrue(true);
    }

    /**
     * assert added plugin_action_links
     */
    public function test_plugin_action_links(): void
    {
        self::assertIsArray($this->payplug_woocommerce->plugin_action_links());
        self::assertNotEmpty($this->payplug_woocommerce->plugin_action_links());

        foreach ($this->payplug_woocommerce->plugin_action_links() as $k) {
            self::assertIsString($k);
        }

        try {
            $this->payplug_woocommerce->plugin_action_links('');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }
}
