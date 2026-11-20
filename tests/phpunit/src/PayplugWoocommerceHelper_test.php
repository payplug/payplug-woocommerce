<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\FakePayment;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class PayplugWoocommerceHelper_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_currency');
        delete_option('woocommerce_payplug_settings');
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function non_order_provider(): array
    {
        return [
            'false' => [false],
            'null' => [null],
            'integer id' => [1],
            'stdClass' => [new \stdClass()],
            'array' => [['id' => 1]],
        ];
    }

    /**
     * @dataProvider non_order_provider
     *
     * @param mixed $value
     */
    public function test_session_owns_order_false_for_non_order($value): void
    {
        WC()->session->set('order_awaiting_payment', 1);

        self::assertFalse(PayplugWoocommerceHelper::session_owns_order($value));
    }

    public function test_session_owns_order_false_for_unsaved_order_with_empty_session(): void
    {
        // An unsaved order has id 0 and absint(null) is 0: the id guard keeps 0 === 0 from matching.
        self::assertFalse(PayplugWoocommerceHelper::session_owns_order(new \WC_Order()));
    }

    public function test_session_owns_order_true_when_order_awaits_payment_in_session(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);

        self::assertTrue(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_session_owns_order_accepts_session_id_stored_as_string(): void
    {
        $order = $this->make_order();
        WC()->session->set('order_awaiting_payment', (string) $order->get_id());

        self::assertTrue(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_session_owns_order_false_when_session_holds_another_order(): void
    {
        $order = $this->make_order();
        $other = $this->make_order();
        $this->own_in_session($other);

        self::assertFalse(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_session_owns_order_false_for_guest_on_guest_order_with_empty_session(): void
    {
        self::assertFalse(PayplugWoocommerceHelper::session_owns_order($this->make_order()));
    }

    public function test_session_owns_order_true_for_logged_in_customer_of_the_order(): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['customer_id' => $user_id]);
        wp_set_current_user($user_id);

        self::assertTrue(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_session_owns_order_false_for_another_logged_in_customer(): void
    {
        $owner_id = $this->make_customer();
        $order = $this->make_order(['customer_id' => $owner_id]);
        wp_set_current_user($this->make_customer());

        self::assertFalse(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_session_owns_order_false_for_logged_in_user_on_guest_order(): void
    {
        $order = $this->make_order(['customer_id' => 0]);
        wp_set_current_user($this->make_customer());

        self::assertFalse(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_session_owns_order_false_without_wc_session(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        WC()->session = null;

        self::assertFalse(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_remember_order_awaiting_payment_stores_the_order_id(): void
    {
        $order = $this->make_order();

        PayplugWoocommerceHelper::remember_order_awaiting_payment((string) $order->get_id());

        self::assertSame($order->get_id(), WC()->session->get('order_awaiting_payment'));
        self::assertTrue(PayplugWoocommerceHelper::session_owns_order($order));
    }

    public function test_remember_order_awaiting_payment_starts_the_session_cookie_for_a_new_guest(): void
    {
        self::assertFalse(WC()->session->has_session());

        PayplugWoocommerceHelper::remember_order_awaiting_payment($this->make_order()->get_id());

        self::assertTrue($this->session_cookie_started());
        self::assertTrue(WC()->session->has_session());
    }

    public function test_remember_order_awaiting_payment_keeps_an_existing_session_cookie(): void
    {
        WC()->session->set_customer_session_cookie(true);
        $this->cookies_set = [];

        PayplugWoocommerceHelper::remember_order_awaiting_payment($this->make_order()->get_id());

        self::assertFalse($this->session_cookie_started());
    }

    public function test_remember_order_awaiting_payment_saves_the_session(): void
    {
        $order = $this->make_order();

        PayplugWoocommerceHelper::remember_order_awaiting_payment($order->get_id());

        self::assertSame($order->get_id(), $this->persisted_session_value('order_awaiting_payment'));
    }

    public function test_remember_order_awaiting_payment_without_wc_session_does_nothing(): void
    {
        WC()->session = null;

        PayplugWoocommerceHelper::remember_order_awaiting_payment(12);

        self::assertNull(WC()->session);
        self::assertSame([], $this->cookies_set);
    }

    public function test_send_order_forbidden_answers_403_invalid_order(): void
    {
        $response = $this->capture_ajax(static function (): void {
            PayplugWoocommerceHelper::send_order_forbidden();
        });

        self::assertSame($this->forbidden_response(), $response);
    }

    public function payment_metadata_provider(): array
    {
        return [
            'no metadata attribute' => [new \stdClass(), 0],
            'null metadata' => [new FakePayment('pay_test_1'), 0],
            'string metadata' => [(object) ['metadata' => 'order_id=12'], 0],
            'metadata without order_id' => [(object) ['metadata' => ['customer_id' => 4]], 0],
            'numeric string order_id' => [(object) ['metadata' => ['order_id' => '12']], 12],
            'integer order_id' => [new FakePayment('pay_test_1', 12), 12],
            'non numeric order_id' => [(object) ['metadata' => ['order_id' => 'abc']], 0],
            'not an object' => [null, 0],
        ];
    }

    /**
     * @dataProvider payment_metadata_provider
     *
     * @param mixed $payment
     */
    public function test_get_payment_order_id($payment, int $expected): void
    {
        self::assertSame($expected, PayplugWoocommerceHelper::get_payment_order_id($payment));
    }

    /**
     * get_payplug_amount() delegates to AmountHelper::toCents() (PRE-3634: UPC AmountHelper
     * migration; PRE-3727: currency-aware) - covers the null pass-through kept from the original
     * implementation, the standard and half-cent-boundary rounding cases, a zero-decimal currency
     * and the shop-currency default.
     */
    public function test_get_payplug_amount_converts_euros_to_cents(): void
    {
        self::assertSame(4999, PayplugWoocommerceHelper::get_payplug_amount(49.99, 'EUR'));
    }

    public function test_get_payplug_amount_sends_a_zero_decimal_currency_as_is(): void
    {
        self::assertSame(1000, PayplugWoocommerceHelper::get_payplug_amount(1000, 'JPY'));
    }

    public function test_get_payplug_amount_defaults_to_the_shop_currency(): void
    {
        update_option('woocommerce_currency', 'JPY');
        self::assertSame(1000, PayplugWoocommerceHelper::get_payplug_amount(1000));

        update_option('woocommerce_currency', 'USD');
        self::assertSame(100000, PayplugWoocommerceHelper::get_payplug_amount(1000));
    }

    public function test_get_payplug_amount_returns_null_unchanged(): void
    {
        self::assertNull(PayplugWoocommerceHelper::get_payplug_amount(null, 'EUR'));
    }

    public function test_get_payplug_amount_rounds_zero(): void
    {
        self::assertSame(0, PayplugWoocommerceHelper::get_payplug_amount(0, 'EUR'));
    }

    public function test_get_payplug_amount_rounds_half_cent_up(): void
    {
        self::assertSame(2000, PayplugWoocommerceHelper::get_payplug_amount(19.995, 'EUR'));
    }

    private function seed_oauth_client_data(bool $live, string $oauth_client_data): void
    {
        update_option('woocommerce_payplug_settings', [
            'mode' => $live,
            'oauth_client_data' => $oauth_client_data,
        ]);
    }

    public function test_is_oauth2_connected_when_the_stored_mode_has_client_credentials(): void
    {
        $this->seed_oauth_client_data(true, '{"live":{"client_id":"id","client_secret":"secret"},"test":[]}');

        self::assertTrue(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_oauth2_connected_checks_the_requested_mode_only(): void
    {
        $this->seed_oauth_client_data(true, '{"live":[],"test":{"client_id":"id","client_secret":"secret"}}');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected(true));
        self::assertTrue(PayplugWoocommerceHelper::is_oauth2_connected(false));
    }

    public function test_is_not_oauth2_connected_with_the_classic_login_default(): void
    {
        $this->seed_oauth_client_data(false, '{}');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_not_oauth2_connected_when_the_client_secret_is_missing(): void
    {
        $this->seed_oauth_client_data(false, '{"test":{"client_id":"id","client_secret":""}}');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_not_oauth2_connected_when_the_oauth_client_data_is_malformed(): void
    {
        $this->seed_oauth_client_data(false, 'not json');

        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }

    public function test_is_not_oauth2_connected_without_settings(): void
    {
        self::assertFalse(PayplugWoocommerceHelper::is_oauth2_connected());
    }
}
