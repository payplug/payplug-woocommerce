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

    protected function tearDown(): void
    {
        // trapWpDie() below registers this filter but this class (unlike WP_UnitTestCase) has no
        // built-in teardown for it - left in place, every later non-AJAX wp_die() call in the
        // whole suite would throw instead of exiting.
        remove_all_filters('wp_die_handler');
        parent::tearDown();
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

    /**
     * WPDieException is the core test suite's own stand-in for wp_die() (see
     * tests/phpunit/includes/abstract-testcase.php's wp_die_handler) - registering the same
     * filter manually here works regardless of this class's TestCase base.
     */
    private function trapWpDie(): void
    {
        add_filter('wp_die_handler', static function () {
            return static function ($message): void {
                throw new \WPDieException(is_string($message) ? $message : '');
            };
        });
    }

    public function test_resolve_upc_3ds_html_dies_without_consuming_the_transient_when_the_order_key_is_wrong(): void
    {
        $this->trapWpDie();

        $order = wc_create_order();
        $order->save();
        set_transient('payplug_upc_redirect_html_' . $order->get_id(), '<html>3ds</html>', 600);

        $_GET['order_id'] = $order->get_id();
        $_GET['order_key'] = 'wrong_key';

        try {
            $this->expectException(\WPDieException::class);
            $this->payplug_woocommerce->resolve_upc_3ds_html();
        } finally {
            $this->assertSame('<html>3ds</html>', get_transient('payplug_upc_redirect_html_' . $order->get_id()));

            unset($_GET['order_id'], $_GET['order_key']);
            delete_transient('payplug_upc_redirect_html_' . $order->get_id());
            wp_delete_post($order->get_id(), true);
        }
    }

    public function test_resolve_upc_3ds_html_dies_when_no_challenge_html_was_stored(): void
    {
        $this->trapWpDie();

        $order = wc_create_order();
        $order->save();

        $_GET['order_id'] = $order->get_id();
        $_GET['order_key'] = $order->get_order_key();

        try {
            $this->expectException(\WPDieException::class);
            $this->payplug_woocommerce->resolve_upc_3ds_html();
        } finally {
            unset($_GET['order_id'], $_GET['order_key']);
            wp_delete_post($order->get_id(), true);
        }
    }

    public function test_resolve_upc_3ds_html_returns_and_consumes_the_stored_html_on_a_matching_key(): void
    {
        $order = wc_create_order();
        $order->save();
        set_transient('payplug_upc_redirect_html_' . $order->get_id(), '<html>3ds</html>', 600);

        $_GET['order_id'] = $order->get_id();
        $_GET['order_key'] = $order->get_order_key();

        $html = $this->payplug_woocommerce->resolve_upc_3ds_html();

        $this->assertSame('<html>3ds</html>', $html);
        $this->assertFalse(get_transient('payplug_upc_redirect_html_' . $order->get_id()));

        unset($_GET['order_id'], $_GET['order_key']);
        wp_delete_post($order->get_id(), true);
    }
}
