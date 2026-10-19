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

    public function apple_pay_contact_provider(): array
    {
        $long_local_part = str_repeat('a', 250) . '@example.test';

        return [
            'null' => [null, []],
            'string' => ['Durand', []],
            'int' => [12, []],
            'empty array' => [[], []],
            'unknown keys dropped' => [['familyName' => 'Doe', 'isAdmin' => '1', 'billing_company' => 'x'], ['familyName' => 'Doe']],
            'all fields' => [
                ['addressLines' => ['2 place Bellecour', 'Appt 4'], 'emailAddress' => 'alice@example.test', 'phoneNumber' => '+33612345678', 'postalCode' => '69001', 'country' => 'France', 'locality' => 'Lyon', 'givenName' => 'Alice', 'familyName' => 'Martin'],
                ['familyName' => 'Martin', 'givenName' => 'Alice', 'locality' => 'Lyon', 'country' => 'France', 'postalCode' => '69001', 'phoneNumber' => '+33612345678', 'emailAddress' => 'alice@example.test', 'addressLines' => ['2 place Bellecour', 'Appt 4']],
            ],
            'apostrophe unslashed' => [['familyName' => wp_slash("O'Brien")], ['familyName' => "O'Brien"]],
            'unicode kept' => [['givenName' => 'Zoë', 'locality' => 'Besançon'], ['givenName' => 'Zoë', 'locality' => 'Besançon']],
            'tags stripped, spaces collapsed' => [['locality' => " <b>Paris</b>\n  Nord "], ['locality' => 'Paris Nord']],
            'array in a scalar field ignored' => [['familyName' => ['Durand'], 'givenName' => 'Alice'], ['givenName' => 'Alice']],
            'int in a scalar field ignored' => [['postalCode' => 75002, 'locality' => 'Paris'], ['locality' => 'Paris']],
            'bool in a scalar field ignored' => [['phoneNumber' => true], []],
            'too long name truncated to 100 characters' => [['familyName' => str_repeat('é', 150)], ['familyName' => str_repeat('é', 100)]],
            'too long city truncated to 100' => [['locality' => str_repeat('b', 101)], ['locality' => str_repeat('b', 100)]],
            'postcode truncated to 20' => [['postalCode' => str_repeat('1', 30)], ['postalCode' => str_repeat('1', 20)]],
            'phone truncated to 30' => [['phoneNumber' => str_repeat('1', 40)], ['phoneNumber' => str_repeat('1', 30)]],
            'empty scalar kept' => [['phoneNumber' => ''], ['phoneNumber' => '']],
            'country name kept as is' => [['country' => 'France'], ['country' => 'France']],
            'valid email' => [['emailAddress' => 'jane.doe@example.test'], ['emailAddress' => 'jane.doe@example.test']],
            'invalid email ignored' => [['emailAddress' => 'not-an-email', 'givenName' => 'Alice'], ['givenName' => 'Alice']],
            'empty email ignored' => [['emailAddress' => ''], []],
            'array email ignored' => [['emailAddress' => ['a@b.test']], []],
            'email longer than 255 ignored' => [['emailAddress' => $long_local_part], []],
            'address lines absent' => [['familyName' => 'Doe'], ['familyName' => 'Doe']],
            'address lines as a string ignored' => [['addressLines' => '1 rue de la Paix'], []],
            'address lines two kept' => [['addressLines' => ['1 rue de la Paix', 'Bat B']], ['addressLines' => ['1 rue de la Paix', 'Bat B']]],
            'address lines beyond two dropped' => [['addressLines' => ['a', 'b', 'c']], ['addressLines' => ['a', 'b']]],
            'address lines non strings and empties dropped' => [['addressLines' => [['x'], '', ' ', 7, 'rue A']], ['addressLines' => ['rue A']]],
            'address lines keyed array reindexed' => [['addressLines' => ['x' => 'rue A']], ['addressLines' => ['rue A']]],
            'address lines without a valid line omitted' => [['addressLines' => ['', ['x']]], []],
            'address line apostrophe unslashed' => [['addressLines' => [wp_slash("1 rue de l'Eglise")]], ['addressLines' => ["1 rue de l'Eglise"]]],
            'address line truncated to 255' => [['addressLines' => [str_repeat('a', 300)]], ['addressLines' => [str_repeat('a', 255)]]],
        ];
    }

    /**
     * @dataProvider apple_pay_contact_provider
     *
     * @param mixed $contact
     */
    public function test_sanitize_apple_pay_contact($contact, array $expected): void
    {
        self::assertSame($expected, PayplugWoocommerceHelper::sanitize_apple_pay_contact($contact));
    }
}
