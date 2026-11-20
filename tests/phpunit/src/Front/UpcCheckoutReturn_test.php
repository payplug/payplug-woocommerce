<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\PayplugWoocommerce\Front\UpcCheckoutReturn;
use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Upc\PaymentReconciler;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;
use PHPUnit\Framework\TestCase;

class UpcCheckoutReturn_test extends TestCase
{
    /** @var \WC_Order[] */
    private $orders = [];

    protected function tearDown(): void
    {
        // trapWpDie() below registers this filter but this class (unlike WP_UnitTestCase) has no
        // built-in teardown for it - left in place, every later non-AJAX wp_die() call in the
        // whole suite would throw instead of exiting.
        remove_all_filters('wp_die_handler');
        remove_all_filters('pre_http_request');
        remove_all_filters('woocommerce_is_order_received_page');
        set_query_var('order-received', '');
        unset($_GET['key']);
        delete_transient('upc_token_upc_oauth_token:');
        delete_transient(PaymentReconciler::throttle_key('op_received_1'));
        delete_option('woocommerce_payplug_settings');
        foreach ($this->orders as $order) {
            $order->delete(true);
        }
        $this->orders = [];
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

    /**
     * A UHF order whose 3DS payment was still pending when the shopper left for the bank, as it
     * stands when the shopper lands back on order-received.
     */
    private function create_three_ds_pending_order(string $status): \WC_Order
    {
        $order = wc_create_order();
        $order->add_product(\WC_Helper_Product::create_simple_product(), 1);
        $order->set_currency('USD');
        $order->set_total(50.00);
        $order->set_status($status);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_received_1');
        $order->update_meta_data('_payplug_upc_outcome', PaymentOutcome::THREE_DS_PENDING);
        $order->save();
        $this->orders[] = $order;

        return $order;
    }

    /**
     * Answers every getOperation() with $exec_code for the order's full amount and returns the
     * ids fetched.
     *
     * @return \ArrayObject<int, string>
     */
    private function answer_operation_fetches(string $exec_code): \ArrayObject
    {
        (new Configuration())->initialize_option();
        $fetched = new \ArrayObject();

        add_filter('pre_http_request', static function ($preempt, $args, $url) use ($fetched, $exec_code) {
            if (false !== strpos($url, '/oauth2/token')) {
                return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 3600, 'token_type' => 'Bearer']), 'headers' => [], 'cookies' => []];
            }

            if (preg_match('#/processing-operations/operations/public/([^/?]+)#', $url, $matches)) {
                $fetched[] = $matches[1];

                return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode(['id' => $matches[1], 'execCode' => $exec_code, 'amount' => AmountHelper::toCents(50.00, 'USD')]), 'headers' => [], 'cookies' => []];
            }

            return ['response' => ['code' => 404, 'message' => 'Not Found'], 'body' => '', 'headers' => [], 'cookies' => []];
        }, 10, 3);

        return $fetched;
    }

    private function land_on_order_received(int $order_id, ?string $key): void
    {
        add_filter('woocommerce_is_order_received_page', '__return_true');
        set_query_var('order-received', (string) $order_id);

        if (null !== $key) {
            $_GET['key'] = $key;
        }
    }

    private function render_thankyou(int $order_id): string
    {
        return wc_get_template_html('checkout/thankyou.php', ['order' => wc_get_order($order_id)]);
    }

    public function test_the_order_received_reconciliation_runs_on_template_redirect_and_the_thankyou_hook_is_kept(): void
    {
        $handler = new UpcCheckoutReturn();

        $this->assertSame(10, has_action('template_redirect', [$handler, 'maybe_reconcile_on_order_received']));
        $this->assertNotFalse(has_action('woocommerce_thankyou', [$handler, 'maybe_reconcile_pending_upc_payment']));
    }

    public function test_a_paid_repay_of_a_failed_order_is_reconciled_before_the_thankyou_template_renders(): void
    {
        $order = $this->create_three_ds_pending_order('failed');
        $fetched = $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), $order->get_order_key());

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();
        $html = $this->render_thankyou($order->get_id());

        $reloaded = wc_get_order($order->get_id());
        $this->assertSame(['op_received_1'], $fetched->getArrayCopy());
        $this->assertSame('processing', $reloaded->get_status());
        $this->assertSame(PaymentOutcome::PAID, $reloaded->get_meta('_payplug_upc_outcome'));
        $this->assertStringContainsString('woocommerce-thankyou-order-received', $html);
        $this->assertStringNotContainsString('woocommerce-thankyou-order-failed', $html);
    }

    public function test_a_paid_first_attempt_still_pending_on_landing_is_reconciled_to_processing(): void
    {
        $order = $this->create_three_ds_pending_order('pending');
        $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), $order->get_order_key());

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame('processing', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_declined_payment_is_reconciled_to_failed_before_the_thankyou_template_renders(): void
    {
        $order = $this->create_three_ds_pending_order('pending');
        $this->answer_operation_fetches('0300');
        $this->land_on_order_received($order->get_id(), $order->get_order_key());

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();
        $html = $this->render_thankyou($order->get_id());

        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
        $this->assertStringContainsString('woocommerce-thankyou-order-failed', $html);
    }

    public function test_a_wrong_order_key_reconciles_nothing(): void
    {
        $order = $this->create_three_ds_pending_order('failed');
        $fetched = $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), 'wc_order_wrong');

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame([], $fetched->getArrayCopy());
        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_missing_order_key_reconciles_nothing(): void
    {
        $order = $this->create_three_ds_pending_order('failed');
        $fetched = $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), null);

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame([], $fetched->getArrayCopy());
        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_refund_id_or_an_unknown_id_reconciles_nothing_without_a_fatal(): void
    {
        $order = $this->create_three_ds_pending_order('processing');
        $refund = wc_create_refund(['order_id' => $order->get_id(), 'amount' => 1]);
        $fetched = $this->answer_operation_fetches('0000');

        $this->land_on_order_received($refund->get_id(), 'wc_order_guess');
        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->land_on_order_received(PHP_INT_MAX, 'wc_order_guess');
        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame([], $fetched->getArrayCopy());
    }

    public function test_a_page_other_than_order_received_reconciles_nothing(): void
    {
        $order = $this->create_three_ds_pending_order('failed');
        $fetched = $this->answer_operation_fetches('0000');
        set_query_var('order-received', (string) $order->get_id());
        $_GET['key'] = $order->get_order_key();
        add_filter('woocommerce_is_order_received_page', '__return_false');

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame([], $fetched->getArrayCopy());
    }

    public function test_an_operation_reconciled_within_the_throttle_window_is_not_fetched_again(): void
    {
        $order = $this->create_three_ds_pending_order('failed');
        $fetched = $this->answer_operation_fetches('0000');
        set_transient(PaymentReconciler::throttle_key('op_received_1'), '1', 60);
        $this->land_on_order_received($order->get_id(), $order->get_order_key());

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame([], $fetched->getArrayCopy());
        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }

    public function test_the_thankyou_fallback_does_not_fetch_an_operation_already_reconciled_on_template_redirect(): void
    {
        $order = $this->create_three_ds_pending_order('pending');
        $fetched = $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), $order->get_order_key());
        $handler = new UpcCheckoutReturn();

        $handler->maybe_reconcile_on_order_received();
        $handler->maybe_reconcile_pending_upc_payment($order->get_id());

        $this->assertSame(['op_received_1'], $fetched->getArrayCopy());
    }

    public function test_an_error_thrown_while_applying_the_outcome_is_logged_instead_of_escaping(): void
    {
        $order = $this->create_three_ds_pending_order('pending');
        $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), $order->get_order_key());

        // WC_Order::payment_complete() only catches \Exception: an \Error from a third-party hook escapes it.
        $throwing_hook = static function (): void {
            throw new \TypeError('third-party hook failure');
        };
        add_action('woocommerce_payment_complete', $throwing_hook);
        $logged = [];
        $capture_log = static function ($message, $level, $context) use (&$logged) {
            if ('payplug-upc' === ($context['source'] ?? '')) {
                $logged[] = [$level, $message];
            }

            return $message;
        };
        add_filter('woocommerce_logger_log_message', $capture_log, 10, 3);

        try {
            (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();
        } finally {
            remove_action('woocommerce_payment_complete', $throwing_hook);
            remove_filter('woocommerce_logger_log_message', $capture_log, 10);
        }

        $this->assertContains(
            ['error', sprintf('UPC order-received reconciliation failed for order #%s: third-party hook failure', $order->get_id())],
            $logged
        );
    }

    public function test_a_non_uhf_order_reconciles_nothing(): void
    {
        $order = wc_create_order();
        $order->set_status('failed');
        $order->save();
        $this->orders[] = $order;
        $fetched = $this->answer_operation_fetches('0000');
        $this->land_on_order_received($order->get_id(), $order->get_order_key());

        (new UpcCheckoutReturn())->maybe_reconcile_on_order_received();

        $this->assertSame([], $fetched->getArrayCopy());
        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }
}
