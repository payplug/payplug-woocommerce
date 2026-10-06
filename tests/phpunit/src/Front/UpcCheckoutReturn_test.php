<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\UpcCheckoutReturn;
use PHPUnit\Framework\TestCase;

class UpcCheckoutReturn_test extends TestCase
{
    protected function tearDown(): void
    {
        // trapWpDie() below registers this filter but this class (unlike WP_UnitTestCase) has no
        // built-in teardown for it - left in place, every later non-AJAX wp_die() call in the
        // whole suite would throw instead of exiting.
        remove_all_filters('wp_die_handler');
        parent::tearDown();
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
            (new UpcCheckoutReturn())->resolve_upc_3ds_html();
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
            (new UpcCheckoutReturn())->resolve_upc_3ds_html();
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

        $html = (new UpcCheckoutReturn())->resolve_upc_3ds_html();

        $this->assertSame('<html>3ds</html>', $html);
        $this->assertFalse(get_transient('payplug_upc_redirect_html_' . $order->get_id()));

        unset($_GET['order_id'], $_GET['order_key']);
        wp_delete_post($order->get_id(), true);
    }
}
