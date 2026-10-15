<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

require_once __DIR__ . '/json_status_spy.php';

/**
 * Front-end WooCommerce state (session, cart, customer) for wc-ajax handlers, the JSON
 * response and HTTP status they send, and order fixtures cleaned up after each test.
 */
trait WooCommerceAjaxFixtures
{
    /**
     * @var array
     */
    private $hooks_backup = [];

    /**
     * @var array
     */
    private $original_wc_state = [];

    /**
     * @var int[]
     */
    private $fixture_order_ids = [];

    /**
     * @var int[]
     */
    private $fixture_product_ids = [];

    /**
     * @var int[]
     */
    private $fixture_user_ids = [];

    /**
     * @var mixed
     */
    private $original_payplug_settings;

    /**
     * @var string[] names of the cookies the code under test tried to set
     */
    protected $cookies_set = [];

    protected function set_up_wc_frontend(): void
    {
        global $wp_filter;

        self::boot_woocommerce();

        $this->hooks_backup = [];
        foreach ($wp_filter as $hook_name => $hook) {
            $this->hooks_backup[$hook_name] = clone $hook;
        }

        $this->original_payplug_settings = get_option('woocommerce_payplug_settings', null);
        delete_option('woocommerce_payplug_settings');

        $_POST = [];
        wp_set_current_user(0);
        JsonStatusSpy::reset();
        $this->cookies_set = [];

        add_filter('woocommerce_set_cookie_enabled', function ($enabled, $name) {
            $this->cookies_set[] = $name;

            return false;
        }, 10, 2);

        $this->original_wc_state = [WC()->session, WC()->cart, WC()->customer];
        WC()->session = new \WC_Session_Handler();
        WC()->session->init();
        WC()->customer = null;
        WC()->cart = null;
        WC()->initialize_cart();
    }

    protected function tear_down_wc_frontend(): void
    {
        global $wp_filter, $wp_query, $wpdb;

        foreach ($this->fixture_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete(true);
            }
        }
        foreach ($this->fixture_product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->delete(true);
            }
        }

        $session_keys = array_map('strval', $this->fixture_user_ids);
        if (WC()->session) {
            $session_keys[] = (string) WC()->session->get_customer_id();
        }
        foreach ($session_keys as $session_key) {
            $wpdb->delete($wpdb->prefix . 'woocommerce_sessions', ['session_key' => $session_key]);
        }

        if ($this->fixture_user_ids) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($this->fixture_user_ids as $user_id) {
                wp_delete_user($user_id);
            }
        }

        list(WC()->session, WC()->cart, WC()->customer) = $this->original_wc_state;
        $wp_filter = $this->hooks_backup;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->set('order-pay', '');
        }

        if (null === $this->original_payplug_settings) {
            delete_option('woocommerce_payplug_settings');
        } else {
            update_option('woocommerce_payplug_settings', $this->original_payplug_settings);
        }

        $_POST = [];
        wp_set_current_user(0);
        JsonStatusSpy::reset();
        $this->fixture_order_ids = [];
        $this->fixture_product_ids = [];
        $this->fixture_user_ids = [];
    }

    /**
     * The test bootstrap activates WooCommerce after `init` has fired, so its post types,
     * order statuses and WooCommerce::init() (factories, countries) are set up here, once.
     */
    private static function boot_woocommerce(): void
    {
        if (did_action('woocommerce_init')) {
            return;
        }

        \WC_Post_Types::register_taxonomies();
        \WC_Post_Types::register_post_types();
        \WC_Post_Types::register_post_status();
        WC()->init();
    }

    /**
     * Run a wc-ajax handler and return what it sent.
     *
     * @return array{status: int|null, body: mixed, died: bool}
     */
    protected function capture_ajax(callable $handler): array
    {
        JsonStatusSpy::reset();
        $doing_ajax = '__return_true';
        $die_handler = static function () {
            return static function (): void {
                throw new AjaxDieException('wp_die');
            };
        };
        add_filter('wp_doing_ajax', $doing_ajax);
        add_filter('wp_die_ajax_handler', $die_handler);

        $request_backup = $_REQUEST;
        $_REQUEST = array_merge($_GET, $_POST);
        $died = false;
        ob_start();
        try {
            $handler();
        } catch (AjaxDieException $e) {
            $died = true;
        } finally {
            $output = (string) ob_get_clean();
            $_REQUEST = $request_backup;
            remove_filter('wp_doing_ajax', $doing_ajax);
            remove_filter('wp_die_ajax_handler', $die_handler);
        }

        return [
            'status' => JsonStatusSpy::$status,
            'body' => '' === $output ? null : json_decode($output, true),
            'died' => $died,
        ];
    }

    protected function forbidden_response(): array
    {
        return ['status' => 403, 'body' => ['success' => false, 'data' => 'Invalid order.'], 'died' => true];
    }

    /**
     * @param string $price
     */
    protected function make_product($price = '10'): \WC_Product_Simple
    {
        $product = new \WC_Product_Simple();
        $product->set_name('Test product');
        $product->set_regular_price($price);
        $product->set_status('publish');
        $product->save();
        $this->fixture_product_ids[] = $product->get_id();

        return $product;
    }

    /**
     * @param array $args status, customer_id, price, with_items, payment_method (apple_pay by default),
     *                    transaction_id (true for pay_test_<order id>), cart_order (marked as created by the Apple Pay cart button)
     */
    protected function make_order(array $args = []): \WC_Order
    {
        $order = new \WC_Order();
        if (!array_key_exists('with_items', $args) || $args['with_items']) {
            $order->add_product($this->make_product($args['price'] ?? '10'), 1);
        }
        $order->set_address([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_1' => '1 rue de la Paix',
            'city' => 'Paris',
            'postcode' => '75002',
            'country' => 'FR',
            'email' => 'jane.doe@example.test',
        ], 'billing');
        $order->set_address([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_1' => '1 rue de la Paix',
            'city' => 'Paris',
            'postcode' => '75002',
            'country' => 'FR',
        ], 'shipping');
        $order->set_payment_method($args['payment_method'] ?? 'apple_pay');
        if (!empty($args['cart_order'])) {
            $order->update_meta_data('_payplug_applepay_cart_order', 'yes');
        }
        $order->set_customer_id($args['customer_id'] ?? 0);
        $order->calculate_totals(false);
        $order->save();
        $this->fixture_order_ids[] = $order->get_id();

        if (!empty($args['transaction_id'])) {
            $order->set_transaction_id(true === $args['transaction_id'] ? 'pay_test_' . $order->get_id() : $args['transaction_id']);
        }
        if (isset($args['status']) && 'pending' !== $args['status']) {
            $order->set_status($args['status']);
        }
        $order->save();

        return $order;
    }

    /**
     * Delete this order after the test, for orders created by the code under test.
     */
    protected function track_order(int $order_id): void
    {
        $this->fixture_order_ids[] = $order_id;
    }

    protected function make_customer(): int
    {
        $user_id = wp_insert_user([
            'user_login' => 'test_customer_' . (count($this->fixture_user_ids) + 1),
            'user_pass' => 'not-a-real-password',
            'user_email' => 'test_customer_' . (count($this->fixture_user_ids) + 1) . '@example.test',
            'role' => 'customer',
        ]);
        $this->fixture_user_ids[] = $user_id;

        return $user_id;
    }

    protected function own_in_session(\WC_Order $order): void
    {
        WC()->session->set('order_awaiting_payment', $order->get_id());
    }

    protected function session_order_id(): int
    {
        return absint(WC()->session->get('order_awaiting_payment'));
    }

    /**
     * Value of a session key as saved in the sessions table, the copy the next request reads.
     *
     * @param string $key
     */
    protected function persisted_session_value($key)
    {
        global $wpdb;

        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_key = %s",
            WC()->session->get_customer_id()
        ));
        $data = $raw ? maybe_unserialize($raw) : [];

        return is_array($data) && isset($data[$key]) ? $data[$key] : null;
    }

    protected function session_cookie_started(): bool
    {
        foreach ($this->cookies_set as $name) {
            if (0 === strpos($name, 'wp_woocommerce_session_')) {
                return true;
            }
        }

        return false;
    }

    protected function order_snapshot(int $order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return ['exists' => false];
        }

        return [
            'exists' => true,
            'status' => $order->get_status(),
            'transaction_id' => $order->get_transaction_id(),
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'total' => $order->get_total(),
            'shipping_lines' => count($order->get_items('shipping')),
            'payplug_metadata' => $order->get_meta('_payplug_metadata'),
        ];
    }

    protected function checkout_nonce(): string
    {
        return wp_create_nonce('woocommerce-process_checkout');
    }
}
