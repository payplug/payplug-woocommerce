<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front;

use Payplug\Exception\ConnectionException;
use Payplug\Exception\ForbiddenException;
use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Front\ApplePay;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Service\Api;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\FakeGateway;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\FakePayment;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\PayplugWoocommerceRequestDouble;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class ApplePay_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    private const UNKNOWN_ORDER_ID = 987654321;

    /**
     * @var ApplePayDouble
     */
    private $apple_pay;

    /**
     * @var GatewayLogSpy|null
     */
    private $log_spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        $this->apple_pay = new ApplePayDouble();
    }

    protected function tearDown(): void
    {
        if ($this->log_spy) {
            $this->log_spy->stop();
            $this->log_spy = null;
        }
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_place_order_with_dummy_data_remembers_the_order_after_emptying_the_cart(): void
    {
        WC()->cart->add_to_cart($this->make_product()->get_id());

        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        $cart_payment = $this->apple_pay->cart_payment;
        self::assertNotNull($cart_payment);
        $this->track_order($cart_payment['order_id']);
        self::assertTrue($cart_payment['cart_empty']);
        self::assertSame($cart_payment['order_id'], $cart_payment['session_order_id']);
        self::assertSame('apple_pay', wc_get_order($cart_payment['order_id'])->get_payment_method());
    }

    public function test_place_order_with_dummy_data_makes_the_guest_owner_of_the_order(): void
    {
        WC()->cart->add_to_cart($this->make_product()->get_id());

        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        $order_id = $this->apple_pay->cart_payment['order_id'];
        $this->track_order($order_id);
        self::assertSame(0, get_current_user_id());
        self::assertTrue($this->session_cookie_started());
        self::assertTrue(PayplugWoocommerceHelper::session_owns_order(wc_get_order($order_id)));
    }

    public function test_place_order_with_dummy_data_saves_the_session_before_the_response(): void
    {
        WC()->cart->add_to_cart($this->make_product()->get_id());

        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        $order_id = $this->apple_pay->cart_payment['order_id'];
        $this->track_order($order_id);
        self::assertSame($order_id, $this->persisted_session_value('order_awaiting_payment'));
    }

    public function test_place_order_with_dummy_data_with_empty_cart_creates_nothing(): void
    {
        $response = $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        self::assertSame(['status' => 200, 'body' => ['success' => false], 'died' => true], $response);
        self::assertNull($this->apple_pay->cart_payment);
        self::assertSame(0, $this->session_order_id());
    }

    /**
     * Each case returns [the posted order id, the id of the order that must stay untouched or null].
     */
    public function foreign_order_provider(): array
    {
        return [
            'unknown order' => [static fn (self $t): array => [(string) self::UNKNOWN_ORDER_ID, null]],
            'no order id' => [static fn (self $t): array => [null, null]],
            'zero order id' => [static fn (self $t): array => ['0', null]],
            'non numeric order id' => [static fn (self $t): array => ['abc', null]],
            'array order id' => [static fn (self $t): array => [['1'], null]],
            'order the visitor did not start' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);

                return [(string) $order->get_id(), $order->get_id()];
            }],
            'negative id of an order the visitor did not start' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);

                return ['-' . $order->get_id(), $order->get_id()];
            }],
            'session holds another order' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);
                $t->own_in_session($t->make_order(['transaction_id' => true]));

                return [(string) $order->get_id(), $order->get_id()];
            }],
            'logged-in user who is not the customer' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true, 'customer_id' => $t->make_customer()]);
                wp_set_current_user($t->make_customer());

                return [(string) $order->get_id(), $order->get_id()];
            }],
        ];
    }

    /**
     * @dataProvider foreign_order_provider
     */
    public function test_update_applepay_order_refuses_a_foreign_order_and_writes_nothing(callable $scenario): void
    {
        list($posted_order_id, $order_id) = $scenario($this);
        $_POST = $this->with_order_id($posted_order_id, [
            'shipping' => ['familyName' => 'Durand', 'addressLines' => ['6 rue des Lilas'], 'emailAddress' => 'durand@example.test'],
            'billing' => ['familyName' => 'Durand', 'country' => 'DE'],
            'shipping_method' => 'flat_rate_1',
        ]);
        $before = null === $order_id ? null : $this->order_snapshot($order_id);

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        self::assertSame($this->forbidden_response(), $response);
        if (null !== $order_id) {
            self::assertSame($before, $this->order_snapshot($order_id));
        }
    }

    public function garbage_contact_provider(): array
    {
        return [
            'string contacts' => [['shipping' => 'Durand', 'billing' => 'Durand']],
            'nested arrays' => [['shipping' => ['familyName' => ['Durand'], 'addressLines' => 'x'], 'billing' => [['x']]]],
        ];
    }

    /**
     * @dataProvider garbage_contact_provider
     */
    public function test_update_applepay_order_refuses_garbage_on_a_foreign_order_without_error(array $post): void
    {
        $order = $this->make_order();
        $_POST = ['order_id' => (string) $order->get_id()] + $post;
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
    }

    public function posted_order_id_format_provider(): array
    {
        return [
            'integer string' => [static fn (int $id): string => (string) $id],
            'leading zero' => [static fn (int $id): string => '0' . $id],
            'decimal' => [static fn (int $id): string => $id . '.0'],
        ];
    }

    /**
     * @dataProvider posted_order_id_format_provider
     */
    public function test_update_applepay_order_session_owner_updates_the_contact(callable $format): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = [
            'order_id' => $format($order->get_id()),
            'shipping' => ['familyName' => 'Martin', 'givenName' => 'Alice', 'locality' => 'Lyon', 'postalCode' => '69001', 'country' => 'FR', 'addressLines' => ['2 place Bellecour']],
        ];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame(200, $response['status']);
        self::assertTrue($response['died']);
        self::assertSame(['success' => true], $response['body']);
        self::assertSame('Martin', $saved->get_shipping_last_name());
        self::assertSame('Lyon', $saved->get_shipping_city());
    }

    public function test_update_applepay_order_logged_in_customer_updates_the_contact(): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['customer_id' => $user_id]);
        wp_set_current_user($user_id);
        $_POST = ['order_id' => (string) $order->get_id(), 'billing' => ['familyName' => 'Martin']];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        self::assertSame(200, $response['status']);
        self::assertSame('Martin', wc_get_order($order->get_id())->get_billing_last_name());
    }

    public function test_update_applepay_order_answers_success_only_and_never_the_order(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'shipping' => ['locality' => 'Lyon']];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        self::assertSame(['status' => 200, 'body' => ['success' => true], 'died' => true], $response);
        self::assertStringNotContainsString($order->get_order_key(), (string) wp_json_encode($response['body']));
    }

    public function test_update_applepay_order_stores_apostrophes_without_backslashes(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = [
            'order_id' => (string) $order->get_id(),
            'shipping' => wp_slash(['familyName' => "O'Brien", 'givenName' => "D'Arcy", 'addressLines' => ["1 rue de l'Eglise", "Bat. d'Or"]]),
            'billing' => wp_slash(['familyName' => "O'Brien", 'addressLines' => ["2 rue de l'Ouest"]]),
        ];

        $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame("O'Brien", $saved->get_shipping_last_name());
        self::assertSame("D'Arcy", $saved->get_shipping_first_name());
        self::assertSame("1 rue de l'Eglise", $saved->get_shipping_address_1());
        self::assertSame("Bat. d'Or", $saved->get_shipping_address_2());
        self::assertSame("O'Brien", $saved->get_billing_last_name());
        self::assertSame("2 rue de l'Ouest", $saved->get_billing_address_1());
    }

    public function garbage_owned_contact_provider(): array
    {
        return [
            'string contacts' => [['shipping' => 'Durand', 'billing' => 'Durand']],
            'address lines as a string' => [['shipping' => ['addressLines' => '2 place Bellecour'], 'billing' => ['addressLines' => 'x']]],
            'address lines without a valid line' => [['shipping' => ['addressLines' => [['x'], '']], 'billing' => ['addressLines' => []]]],
            'nested arrays' => [['shipping' => ['familyName' => ['Durand'], 'locality' => [['x']]], 'billing' => [['x']]]],
            'unexpected scalars' => [['shipping' => ['postalCode' => true, 'locality' => 12], 'billing' => 'x']],
        ];
    }

    /**
     * @dataProvider garbage_owned_contact_provider
     */
    public function test_update_applepay_order_ignores_garbage_contacts_and_keeps_the_address(array $post): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id()] + $post;

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame(['status' => 200, 'body' => ['success' => true], 'died' => true], $response);
        self::assertSame(['Doe', '1 rue de la Paix', 'Paris', '75002'], [$saved->get_shipping_last_name(), $saved->get_shipping_address_1(), $saved->get_shipping_city(), $saved->get_shipping_postcode()]);
        self::assertSame(['Doe', '1 rue de la Paix', 'Paris'], [$saved->get_billing_last_name(), $saved->get_billing_address_1(), $saved->get_billing_city()]);
    }

    public function test_update_applepay_order_with_only_some_contact_fields_updates_only_those(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'shipping' => ['familyName' => 'Martin'], 'billing' => ['givenName' => 'Alice']];

        $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame(['Martin', 'Jane', '1 rue de la Paix'], [$saved->get_shipping_last_name(), $saved->get_shipping_first_name(), $saved->get_shipping_address_1()]);
        self::assertSame(['Doe', 'Alice', '1 rue de la Paix'], [$saved->get_billing_last_name(), $saved->get_billing_first_name(), $saved->get_billing_address_1()]);
    }

    public function test_update_applepay_order_ignores_an_invalid_email_and_keeps_the_previous_one(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'shipping' => ['emailAddress' => 'not-an-email', 'locality' => 'Lyon']];

        $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame('jane.doe@example.test', $saved->get_billing_email());
        self::assertSame('Lyon', $saved->get_shipping_city());
    }

    public function test_update_applepay_order_never_takes_the_email_or_the_phone_of_the_billing_contact(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'billing' => ['emailAddress' => 'other@example.test', 'phoneNumber' => '0699999999']];

        $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame('jane.doe@example.test', $saved->get_billing_email());
        self::assertSame('', $saved->get_billing_phone());
    }

    public function test_update_applepay_order_stores_a_full_valid_payload_as_before(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = [
            'order_id' => (string) $order->get_id(),
            'shipping' => ['familyName' => 'Martin', 'givenName' => 'Alice', 'country' => 'FR', 'locality' => 'Lyon', 'phoneNumber' => '0612345678', 'postalCode' => '69001', 'addressLines' => ['2 place Bellecour', 'Appt 4'], 'emailAddress' => 'alice@example.test'],
            'billing' => ['familyName' => 'Durand', 'givenName' => 'Bob', 'addressLines' => ['3 rue X'], 'locality' => 'Nice', 'country' => 'FR', 'postalCode' => '06000'],
        ];

        $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertSame(
            ['Martin', 'Alice', 'FR', 'Lyon', '0612345678', '69001', '2 place Bellecour', 'Appt 4', 'alice@example.test'],
            [$saved->get_shipping_last_name(), $saved->get_shipping_first_name(), $saved->get_shipping_country(), $saved->get_shipping_city(), $saved->get_shipping_phone(), $saved->get_shipping_postcode(), $saved->get_shipping_address_1(), $saved->get_shipping_address_2(), $saved->get_billing_email()]
        );
        self::assertSame(
            ['Durand', 'Bob', '3 rue X', 'Nice', 'FR', '06000'],
            [$saved->get_billing_last_name(), $saved->get_billing_first_name(), $saved->get_billing_address_1(), $saved->get_billing_city(), $saved->get_billing_country(), $saved->get_billing_postcode()]
        );
    }

    public function test_place_order_with_dummy_data_refuses_beyond_the_limit_in_http_200_without_creating_an_order(): void
    {
        $this->limit_dummy_orders(1, 0);
        WC()->cart->add_to_cart($this->make_product()->get_id());
        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
        $this->track_order($this->apple_pay->cart_payment['order_id']);
        $this->apple_pay->cart_payment = null;
        WC()->cart->add_to_cart($this->make_product()->get_id());
        $orders_before = $this->pending_order_ids();

        $response = $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        $message = 'Too many Apple Pay attempts. Please wait a few minutes and try again.';
        self::assertSame(['status' => 200, 'body' => ['success' => false, 'data' => ['msg' => $message, 'message' => $message]], 'died' => true], $response);
        self::assertNull($this->apple_pay->cart_payment);
        self::assertSame($orders_before, $this->pending_order_ids());
        self::assertSame(1, WC()->cart->get_cart_contents_count());
    }

    public function test_place_order_with_dummy_data_refuses_another_session_beyond_the_ip_limit(): void
    {
        $this->limit_dummy_orders(0, 1);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        WC()->cart->add_to_cart($this->make_product()->get_id());
        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
        $this->track_order($this->apple_pay->cart_payment['order_id']);
        $this->apple_pay->cart_payment = null;
        $this->start_new_guest_session();
        WC()->cart->add_to_cart($this->make_product()->get_id());
        $orders_before = $this->pending_order_ids();

        $response = $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        self::assertFalse($response['body']['success']);
        self::assertNull($this->apple_pay->cart_payment);
        self::assertSame($orders_before, $this->pending_order_ids());
    }

    public function test_place_order_with_dummy_data_with_an_empty_cart_does_not_use_the_quota(): void
    {
        $this->limit_dummy_orders(1, 1);
        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
        WC()->cart->add_to_cart($this->make_product()->get_id());

        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        self::assertNotNull($this->apple_pay->cart_payment);
        $this->track_order($this->apple_pay->cart_payment['order_id']);
    }

    public function test_place_order_with_dummy_data_counts_a_failed_order_creation(): void
    {
        $this->limit_dummy_orders(1, 0);
        $fail_creation = static function (): void {
            throw new \Exception('creation failed');
        };
        add_action('woocommerce_order_before_calculate_taxes', $fail_creation);
        WC()->cart->add_to_cart($this->make_product()->get_id());
        $orders_before = $this->pending_order_ids();
        $failed = $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
        remove_action('woocommerce_order_before_calculate_taxes', $fail_creation);
        foreach (array_diff($this->pending_order_ids(), $orders_before) as $order_id) {
            $this->track_order($order_id);
        }

        $response = $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);

        self::assertSame(['success' => false, 'data' => ['message' => 'Your order was cancelled.']], $failed['body']);
        self::assertSame('Too many Apple Pay attempts. Please wait a few minutes and try again.', $response['body']['data']['msg']);
        self::assertNull($this->apple_pay->cart_payment);
    }

    public function test_place_order_with_dummy_data_lets_a_buyer_retry_up_to_the_default_limit(): void
    {
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            WC()->cart->add_to_cart($this->make_product()->get_id());
            $this->apple_pay->cart_payment = null;
            $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
            self::assertNotNull($this->apple_pay->cart_payment, 'attempt ' . $attempt);
            $this->track_order($this->apple_pay->cart_payment['order_id']);
        }
    }

    public function test_place_order_with_dummy_data_is_not_limited_when_the_filter_disables_the_limits(): void
    {
        add_filter('payplug_applepay_dummy_order_rate_limit', '__return_false');
        for ($attempt = 1; $attempt <= 12; ++$attempt) {
            WC()->cart->add_to_cart($this->make_product()->get_id());
            $this->apple_pay->cart_payment = null;
            $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
            self::assertNotNull($this->apple_pay->cart_payment, 'attempt ' . $attempt);
            $this->track_order($this->apple_pay->cart_payment['order_id']);
        }
    }

    /**
     * @dataProvider foreign_order_provider
     */
    public function test_update_applepay_payment_refuses_a_foreign_order_before_any_api_call(callable $scenario): void
    {
        list($posted_order_id, $order_id) = $scenario($this);
        $payment_id = null === $order_id ? 'pay_test_unknown' : wc_get_order($order_id)->get_transaction_id();
        $_POST = $this->with_order_id($posted_order_id, ['payment_id' => $payment_id, 'payment_token' => 'token', 'amount' => '1']);
        $before = null === $order_id ? null : $this->order_snapshot($order_id);

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        if (null !== $order_id) {
            self::assertSame($before, $this->order_snapshot($order_id));
        }
    }

    /**
     * Each case returns the posted payment id for an order whose transaction id is pay_test_<order id>.
     */
    public function wrong_payment_id_provider(): array
    {
        return [
            'missing payment id' => [static fn (\WC_Order $order): ?string => null],
            'empty payment id' => [static fn (\WC_Order $order): ?string => ''],
            'payment of another order' => [static fn (\WC_Order $order): ?string => 'pay_test_' . ($order->get_id() + 1)],
            'prefix of the transaction id' => [static fn (\WC_Order $order): ?string => 'pay_test_'],
            'transaction id in upper case' => [static fn (\WC_Order $order): ?string => strtoupper($order->get_transaction_id())],
            'array payment id' => [static fn (\WC_Order $order): array => [$order->get_transaction_id()]],
        ];
    }

    /**
     * @dataProvider wrong_payment_id_provider
     */
    public function test_update_applepay_payment_refuses_a_payment_id_that_is_not_the_order_transaction(callable $payment_id): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_token' => 'token'];
        if (null !== $payment_id($order)) {
            $_POST['payment_id'] = $payment_id($order);
        }

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
    }

    public function test_update_applepay_payment_refuses_an_order_without_transaction_id(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => 'pay_test_any', 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
    }

    public function payment_metadata_mismatch_provider(): array
    {
        return [
            'metadata of another order' => [static fn (int $order_id): ?int => $order_id + 1],
            'no order id in metadata' => [static fn (int $order_id): ?int => null],
        ];
    }

    /**
     * @dataProvider payment_metadata_mismatch_provider
     */
    public function test_update_applepay_payment_refuses_a_payment_whose_metadata_names_another_order(callable $metadata_order_id): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $metadata_order_id($order->get_id()));
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id(), 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame(['retrieve_payment'], $this->apple_pay->calls);
        self::assertSame([], $this->apple_pay->payment->updates);
    }

    public function test_update_applepay_payment_charges_the_order_total_and_ignores_the_browser_amount(): void
    {
        $order = $this->make_order(['transaction_id' => true, 'price' => '25.40']);
        $this->own_in_session($order);
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id(), 'payment_token' => 'token', 'amount' => '1'];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['success']);
        self::assertSame(2540, $response['body']['data']['amount']);
        self::assertCount(1, $this->apple_pay->payment->updates);
        $update = $this->apple_pay->payment->updates[0]['apple_pay'];
        self::assertSame(2540, $update['amount']);
        self::assertSame('token', $update['payment_token']);
        self::assertArrayNotHasKey('delivery_type', $update['billing']);
        self::assertArrayNotHasKey('delivery_type', $update['shipping']);
    }

    public function test_update_applepay_payment_amount_includes_the_shipping_line_of_the_order(): void
    {
        $order = $this->make_order(['transaction_id' => true, 'price' => '25.40']);
        $shipping = new \WC_Order_Item_Shipping();
        $shipping->set_method_title('Flat rate');
        $shipping->set_method_id('flat_rate');
        $shipping->set_total('4.60');
        $order->add_item($shipping);
        $order->calculate_totals(false);
        $order->save();
        $this->own_in_session($order);
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id(), 'payment_token' => 'token', 'amount' => '25.40'];

        $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame(3000, $this->apple_pay->payment->updates[0]['apple_pay']['amount']);
    }

    public function out_of_range_total_provider(): array
    {
        return [
            'below the minimum' => ['0.50'],
            'above the maximum' => ['20000.01'],
        ];
    }

    /**
     * @dataProvider out_of_range_total_provider
     */
    public function test_update_applepay_payment_answers_the_amount_error_without_updating_the_payment(string $price): void
    {
        $order = $this->make_order(['transaction_id' => true, 'price' => $price]);
        $this->own_in_session($order);
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id(), 'payment_token' => 'token', 'amount' => '10'];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertSame(200, $response['status']);
        self::assertFalse($response['body']['success']);
        self::assertStringContainsString('are not authorised with this payment gateway', $response['body']['data']['message']);
        self::assertSame([], $this->apple_pay->payment->updates);
    }

    public function settled_order_status_provider(): array
    {
        return [['processing'], ['completed'], ['on-hold'], ['cancelled'], ['refunded']];
    }

    /**
     * @dataProvider settled_order_status_provider
     */
    public function test_update_applepay_order_leaves_an_order_that_no_longer_needs_payment_untouched(string $status): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['customer_id' => $user_id, 'status' => $status]);
        wp_set_current_user($user_id);
        $total = $order->get_total();
        $_POST = [
            'order_id' => (string) $order->get_id(),
            'shipping' => ['locality' => 'Lyon'],
            'shipping_method' => 'flat_rate:1',
        ];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_order']);

        $saved = wc_get_order($order->get_id());
        self::assertFalse($response['body']['success']);
        self::assertSame('Paris', $saved->get_shipping_city());
        self::assertSame($total, $saved->get_total());
        self::assertSame($status, $saved->get_status());
    }

    /**
     * @dataProvider settled_order_status_provider
     */
    public function test_update_applepay_payment_does_not_touch_the_payment_of_an_order_that_no_longer_needs_payment(string $status): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['transaction_id' => true, 'customer_id' => $user_id, 'status' => $status]);
        wp_set_current_user($user_id);
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id(), 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertFalse($response['body']['success']);
        self::assertSame([], $this->apple_pay->payment->updates);
    }

    public function test_update_applepay_payment_logged_in_customer_of_the_order_is_allowed(): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['transaction_id' => true, 'customer_id' => $user_id]);
        wp_set_current_user($user_id);
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id(), 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->apple_pay, 'update_applepay_payment']);

        self::assertTrue($response['body']['success']);
        self::assertSame(1000, $this->apple_pay->payment->updates[0]['apple_pay']['amount']);
    }

    /**
     * @dataProvider foreign_order_provider
     */
    public function test_applepay_cancel_order_refuses_a_foreign_order_before_the_cart_or_the_api(callable $scenario): void
    {
        list($posted_order_id, $order_id) = $scenario($this);
        $payment_id = null === $order_id ? 'pay_test_unknown' : wc_get_order($order_id)->get_transaction_id();
        $this->apple_pay->merchant_client = new Payplug('sk_test_double');
        $this->apple_pay->payment = new FakePayment($payment_id, $order_id);
        $_POST = $this->with_order_id($posted_order_id, ['payment_id' => $payment_id]);
        $before = null === $order_id ? null : $this->order_snapshot($order_id);

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame(empty($posted_order_id) ? $this->cancelled_error_response() : $this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        if (null !== $order_id) {
            self::assertSame($before, $this->order_snapshot($order_id));
        }
    }

    /**
     * @dataProvider foreign_order_provider
     */
    public function test_applepay_cancel_order_without_payment_id_refuses_a_foreign_order(callable $scenario): void
    {
        list($posted_order_id, $order_id) = $scenario($this);
        $_POST = $this->with_order_id($posted_order_id, []);
        $before = null === $order_id ? null : $this->order_snapshot($order_id);

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame(empty($posted_order_id) ? $this->cancelled_error_response() : $this->forbidden_response(), $response);
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        if (null !== $order_id) {
            self::assertSame($before, $this->order_snapshot($order_id));
        }
    }

    public function cancel_wrong_payment_id_provider(): array
    {
        return [
            'payment of another order' => [static fn (\WC_Order $order) => 'pay_test_' . ($order->get_id() + 1)],
            'transaction id in upper case' => [static fn (\WC_Order $order) => strtoupper($order->get_transaction_id())],
            'array payment id' => [static fn (\WC_Order $order) => [$order->get_transaction_id()]],
            'nested array payment id' => [static fn (\WC_Order $order) => [[$order->get_transaction_id()]]],
        ];
    }

    /**
     * @dataProvider cancel_wrong_payment_id_provider
     */
    public function test_applepay_cancel_order_owner_with_a_wrong_payment_id_is_refused_and_keeps_everything(callable $payment_id): void
    {
        $order = $this->make_cancellable_order();
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $payment_id($order)];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    public function kept_order_status_provider(): array
    {
        return [
            'processing, with payment id' => ['processing', true],
            'processing, without payment id' => ['processing', false],
            'completed' => ['completed', true],
            'on-hold' => ['on-hold', true],
            'cancelled' => ['cancelled', false],
        ];
    }

    /**
     * @dataProvider kept_order_status_provider
     */
    public function test_applepay_cancel_order_never_deletes_or_aborts_an_order_that_is_not_pending_or_failed(string $status, bool $with_payment_id): void
    {
        $order = $this->make_cancellable_order(['status' => $status]);
        $_POST = ['order_id' => (string) $order->get_id()];
        if ($with_payment_id) {
            $_POST['payment_id'] = $order->get_transaction_id();
        }
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->order_kept_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
    }

    /**
     * @dataProvider payment_metadata_mismatch_provider
     */
    public function test_applepay_cancel_order_refuses_a_payment_whose_metadata_names_another_order(callable $metadata_order_id): void
    {
        $order = $this->make_cancellable_order();
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id(), $metadata_order_id($order->get_id()));
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id()];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
    }

    public function test_applepay_cancel_order_twice_on_a_retrieve_failure_keeps_the_order_and_the_cart(): void
    {
        $order = $this->make_cancellable_order();
        WC()->cart->add_to_cart($this->order_product_id($order), 1);
        $this->apple_pay->payment = new \RuntimeException('API unreachable');
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id()];

        $first = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);
        $second = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->not_cancelled_error_response(), $first);
        self::assertSame($this->not_cancelled_error_response(), $second);
        self::assertSame(1, WC()->cart->get_cart_contents_count());
        self::assertSame('pending', wc_get_order($order->get_id())->get_status());
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    public function test_applepay_cancel_order_without_merchant_key_keeps_the_order_and_the_cart(): void
    {
        $order = $this->make_cancellable_order();
        $this->apple_pay->merchant_client = null;
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->not_cancelled_error_response(), $response);
        self::assertSame(['get_merchant_client'], $this->apple_pay->calls);
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        self::assertSame('pending', wc_get_order($order->get_id())->get_status());
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    /**
     * Each case returns [the answers of the retrieve calls after the first one, which finds the unpaid payment, the expected abort calls].
     */
    public function payment_maybe_paid_provider(): array
    {
        $scenarios = [
            'payment already paid' => static function (FakePayment $payment): array {
                $payment->is_paid = true;

                return [[], 0];
            },
            'abort fails, payment paid meanwhile' => static function (FakePayment $payment): array {
                $payment->abort_exception = new \RuntimeException('abort refused');
                $paid = clone $payment;
                $paid->is_paid = true;

                return [[$paid], 1];
            },
            'abort fails, payment state unknown' => static function (FakePayment $payment): array {
                $payment->abort_exception = new \RuntimeException('abort refused');

                return [[new \RuntimeException('API unreachable')], 1];
            },
            'abort fails, payment neither paid nor failed' => static function (FakePayment $payment): array {
                $payment->abort_exception = new \RuntimeException('abort refused');

                return [[clone $payment], 1];
            },
            'abort fails, payment paid and carrying a failure' => static function (FakePayment $payment): array {
                $payment->abort_exception = new \RuntimeException('abort refused');
                $paid = clone $payment;
                $paid->is_paid = true;
                $paid->failure = (object) ['code' => 'aborted', 'message' => 'Payment aborted'];

                return [[$paid], 1];
            },
        ];

        return $this->with_and_without_posted_payment_id($scenarios);
    }

    /**
     * @dataProvider payment_maybe_paid_provider
     */
    public function test_applepay_cancel_order_keeps_the_order_and_the_cart_when_the_payment_may_be_paid(callable $scenario, bool $with_payment_id): void
    {
        $order = $this->make_cancellable_order();
        $payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        list($later_results, $expected_aborts) = $scenario($payment);
        $this->apple_pay->retrieve_results = array_merge([$payment], $later_results);
        $_POST = $this->cancel_post($order, $with_payment_id);

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->order_kept_response(), $response);
        self::assertSame($expected_aborts, $payment->aborts);
        $this->assert_api_called_with_the_merchant_client($payment);
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        self::assertSame('pending', wc_get_order($order->get_id())->get_status());
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    /**
     * Each case returns [the order status, the answers of the retrieve calls, the expected retrieve calls].
     */
    public function cancellable_order_provider(): array
    {
        $scenarios = [
            'payment aborted' => static fn (\WC_Order $order): array => ['pending', [new FakePayment($order->get_transaction_id(), $order->get_id())], 1],
            'failed order, payment aborted' => static fn (\WC_Order $order): array => ['failed', [new FakePayment($order->get_transaction_id(), $order->get_id())], 1],
            'abort fails, payment already aborted' => static function (\WC_Order $order): array {
                $payment = new FakePayment($order->get_transaction_id(), $order->get_id());
                $payment->abort_exception = new \RuntimeException('abort refused');
                $failed = clone $payment;
                $failed->failure = (object) ['code' => 'aborted', 'message' => 'Payment aborted'];

                return ['pending', [$payment, $failed], 2];
            },
            'abort fails, payment failed' => static function (\WC_Order $order): array {
                $payment = new FakePayment($order->get_transaction_id(), $order->get_id());
                $payment->abort_exception = new \RuntimeException('abort refused');
                $failed = clone $payment;
                $failed->failure = (object) ['code' => 'timeout', 'message' => 'The customer has not tried to pay and left the payment page.'];

                return ['pending', [$payment, $failed], 2];
            },
        ];

        return $this->with_and_without_posted_payment_id($scenarios);
    }

    /**
     * @dataProvider cancellable_order_provider
     */
    public function test_applepay_cancel_order_aborts_the_payment_of_the_order_then_deletes_it_and_restores_the_cart_once(callable $scenario, bool $with_payment_id): void
    {
        $order = $this->make_cancellable_order();
        list($status, $retrieve_results, $expected_retrieves) = $scenario($order);
        $payment = $retrieve_results[0];
        $order->set_status($status);
        $order->save();
        $this->apple_pay->retrieve_results = $retrieve_results;
        $_POST = $this->cancel_post($order, $with_payment_id);

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);
        $retry = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->cancelled_success_response(), $response);
        self::assertSame($this->forbidden_response(), $retry);
        self::assertSame(1, $payment->aborts);
        self::assertSame(array_merge(['get_merchant_client'], array_fill(0, $expected_retrieves, 'retrieve_payment')), $this->apple_pay->calls);
        self::assertSame(array_fill(0, $expected_retrieves, $order->get_transaction_id()), $this->apple_pay->retrieved_payment_ids);
        $this->assert_api_called_with_the_merchant_client($payment);
        self::assertFalse(wc_get_order($order->get_id()));
        self::assertSame(1, WC()->cart->get_cart_contents_count());
        self::assertSame(0, $this->session_order_id());
    }

    public function never_created_payment_provider(): array
    {
        return [
            'payment id omitted' => [null],
            'empty payment id, as jQuery sends null' => [''],
            'zero payment id' => ['0'],
        ];
    }

    /**
     * @dataProvider never_created_payment_provider
     */
    public function test_applepay_cancel_order_deletes_an_order_whose_payment_was_never_created_without_calling_the_api(?string $posted_payment_id): void
    {
        $order = $this->make_cancellable_order(['transaction_id' => false]);
        $_POST = ['order_id' => (string) $order->get_id()];
        if (null !== $posted_payment_id) {
            $_POST['payment_id'] = $posted_payment_id;
        }

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->cancelled_success_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertFalse(wc_get_order($order->get_id()));
        self::assertSame(1, WC()->cart->get_cart_contents_count());
        self::assertSame(0, $this->session_order_id());
    }

    public function test_applepay_cancel_order_refuses_a_payment_id_posted_for_an_order_without_payment(): void
    {
        $order = $this->make_cancellable_order(['transaction_id' => false]);
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => 'pay_test_' . $order->get_id()];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    public function other_payment_method_provider(): array
    {
        return [
            'card payment with a hosted payment page' => ['payplug', true],
            'bank transfer' => ['bacs', false],
            'no payment method' => ['', false],
        ];
    }

    /**
     * @dataProvider other_payment_method_provider
     */
    public function test_applepay_cancel_order_refuses_an_order_of_another_payment_method_and_touches_nothing(string $payment_method, bool $with_transaction_id): void
    {
        $order = $this->make_cancellable_order(['payment_method' => $payment_method, 'transaction_id' => $with_transaction_id]);
        $_POST = $this->cancel_post($order, $with_transaction_id);
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    public function transaction_id_provider(): array
    {
        return [
            'with a payment' => [true],
            'without a payment' => [false],
        ];
    }

    /**
     * Apple Pay order without the cart order mark, such as one placed from the checkout page.
     *
     * @dataProvider transaction_id_provider
     */
    public function test_applepay_cancel_order_refuses_an_apple_pay_order_not_created_by_the_cart_button(bool $with_transaction_id): void
    {
        $order = $this->make_cancellable_order(['cart_order' => false, 'transaction_id' => $with_transaction_id]);
        $_POST = $this->cancel_post($order, $with_transaction_id);
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
    }

    public function merchant_order_provider(): array
    {
        return [
            'card order' => ['payplug', 'payplug'],
            'order paid with Apple Pay on the order-pay page' => ['apple_pay', 'apple_pay'],
        ];
    }

    /**
     * @dataProvider merchant_order_provider
     */
    public function test_applepay_cancel_order_refuses_a_merchant_order_to_the_holder_of_its_order_pay_key(string $payment_method, string $gateway): void
    {
        $order = $this->make_order(['payment_method' => $payment_method]);
        $request = new PayplugWoocommerceRequestDouble();
        $request->gateway_double = new FakeGateway($gateway);
        $request->payment = new FakePayment('pay_test_intent', $order->get_id());
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => $gateway,
        ];
        $this->capture_ajax([$request, 'create_payment_intent']);
        self::assertTrue(PayplugWoocommerceHelper::session_owns_order(wc_get_order($order->get_id())));
        $this->apple_pay->merchant_client = new Payplug('sk_test_double');
        $this->apple_pay->payment = new FakePayment('pay_test_intent', $order->get_id());
        $_POST = ['order_id' => (string) $order->get_id()];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->apple_pay->calls);
        self::assertSame(0, $this->apple_pay->payment->aborts);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, WC()->cart->get_cart_contents_count());
    }

    /**
     * @dataProvider transaction_id_provider
     */
    public function test_applepay_cancel_order_cancels_an_order_of_the_cart_button(bool $with_payment): void
    {
        $product = $this->make_product();
        WC()->cart->add_to_cart($product->get_id(), 2);
        $this->capture_ajax([$this->apple_pay, 'place_order_with_dummy_data']);
        $order_id = $this->apple_pay->cart_payment['order_id'];
        $this->track_order($order_id);
        $payment = new FakePayment('pay_test_' . $order_id, $order_id);
        if ($with_payment) {
            $order = wc_get_order($order_id);
            $order->set_transaction_id('pay_test_' . $order_id);
            $order->save();
        }
        $this->apple_pay->merchant_client = new Payplug('sk_test_double');
        $this->apple_pay->payment = $payment;
        $_POST = ['order_id' => (string) $order_id, 'payment_id' => $with_payment ? 'pay_test_' . $order_id : ''];

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->cancelled_success_response(), $response);
        self::assertSame($with_payment ? 1 : 0, $payment->aborts);
        self::assertFalse(wc_get_order($order_id));
        self::assertSame(2, WC()->cart->get_cart_item_quantities()[$product->get_id()] ?? 0);
        self::assertSame(0, $this->session_order_id());
    }

    public function test_applepay_cancel_order_leaves_the_cart_untouched_when_the_order_cannot_be_deleted(): void
    {
        $order = $this->make_cancellable_order();
        $refuse_delete = '__return_false';
        add_filter('woocommerce_pre_delete_order', $refuse_delete);
        $_POST = ['order_id' => (string) $order->get_id()];

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);
        remove_filter('woocommerce_pre_delete_order', $refuse_delete);

        self::assertSame($this->not_cancelled_error_response(), $response);
        self::assertSame(1, $this->apple_pay->payment->aborts);
        self::assertSame(0, WC()->cart->get_cart_contents_count());
        self::assertSame('pending', wc_get_order($order->get_id())->get_status());
    }

    public function deleted_variation_provider(): array
    {
        return [
            'variation deleted before the cancel' => [static function (\WC_Product_Variation $variation): void {
                $variation->delete(true);
            }],
            'variation deleted once the order lines are read' => [static function (\WC_Product_Variation $variation): void {
                add_filter('woocommerce_order_get_items', static function (array $items) use ($variation): array {
                    if (get_post($variation->get_id())) {
                        $variation->delete(true);
                    }

                    return $items;
                });
            }],
            'variation deleted once the order lines are read, product type no longer cached' => [static function (\WC_Product_Variation $variation): void {
                $variation_id = $variation->get_id();
                add_filter('woocommerce_order_get_items', static function (array $items) use ($variation, $variation_id): array {
                    if (get_post($variation_id)) {
                        $variation->delete(true);
                        \WC_Cache_Helper::invalidate_cache_group('product_' . $variation_id);
                    }

                    return $items;
                });
            }],
            'variation detached from its variable product' => [static function (\WC_Product_Variation $variation): void {
                global $wpdb;

                $wpdb->update($wpdb->posts, ['post_parent' => 0], ['ID' => $variation->get_id()]);
                clean_post_cache($variation->get_id());
            }],
        ];
    }

    /**
     * @dataProvider deleted_variation_provider
     */
    public function test_applepay_cancel_order_with_a_deleted_variation_deletes_the_order_and_restores_the_other_lines(callable $delete_variation): void
    {
        $order = $this->make_cancellable_order();
        $simple_product_id = $this->order_product_id($order);
        $variations = $this->make_variations(['S', 'M']);
        $order->add_product($variations['S'], 2);
        $order->add_product($variations['M'], 3);
        $order->save();
        $delete_variation($variations['S']);
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id()];
        $deletions_before = did_action('woocommerce_delete_order');

        $response = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);
        $retry = $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        self::assertSame($this->cancelled_success_response(), $response);
        self::assertSame($this->forbidden_response(), $retry);
        self::assertSame(1, $this->apple_pay->payment->aborts);
        self::assertFalse(wc_get_order($order->get_id()));
        self::assertSame(1, did_action('woocommerce_delete_order') - $deletions_before);
        self::assertSame([
            [$simple_product_id, 0, 1, []],
            [$variations['M']->get_parent_id(), $variations['M']->get_id(), 3, ['attribute_size' => 'M']],
        ], array_map(static fn (array $item): array => [$item['product_id'], $item['variation_id'], $item['quantity'], $item['variation']], array_values(WC()->cart->get_cart())));
        self::assertSame(0, $this->session_order_id());
    }

    /**
     * Each case returns [the answers of the retrieve calls for the order, the expected warnings with %d for the order id].
     */
    public function api_failure_log_provider(): array
    {
        $unreachable = static fn (): ConnectionException => new ConnectionException('Connection to the API failed with the following message: timeout', 28);
        $refusing_abort = static function (\WC_Order $order): FakePayment {
            $payment = new FakePayment($order->get_transaction_id(), $order->get_id());
            $payment->abort_exception = new ForbiddenException('Forbidden error. You are not allowed to access this resource.', '{"object":"error","message":"api response body"}', 403);

            return $payment;
        };
        $retrieve_warning = 'Apple Pay: payment of order #%d could not be retrieved (Payplug\Exception\ConnectionException: Connection to the API failed with the following message: timeout)';
        $abort_warning = 'Apple Pay: payment of order #%d could not be aborted (Payplug\Exception\ForbiddenException: Forbidden error. You are not allowed to access this resource.)';

        return [
            'payment not retrieved' => [static fn (\WC_Order $order): array => [$unreachable()], [$retrieve_warning]],
            'abort refused' => [static function (\WC_Order $order) use ($refusing_abort): array {
                $payment = $refusing_abort($order);
                $failed = clone $payment;
                $failed->failure = (object) ['code' => 'aborted', 'message' => 'Payment aborted'];

                return [$payment, $failed];
            }, [$abort_warning]],
            'abort refused, payment not retrieved again' => [
                static fn (\WC_Order $order): array => [$refusing_abort($order), $unreachable()],
                [$abort_warning, $retrieve_warning],
            ],
        ];
    }

    /**
     * @dataProvider api_failure_log_provider
     *
     * @param string[] $expected_warnings
     */
    public function test_applepay_cancel_order_logs_the_class_and_message_of_the_api_exception(callable $retrieve_results, array $expected_warnings): void
    {
        $order = $this->make_cancellable_order();
        $this->apple_pay->retrieve_results = $retrieve_results($order);
        $_POST = ['order_id' => (string) $order->get_id(), 'payment_id' => $order->get_transaction_id()];
        $this->log_spy = GatewayLogSpy::start();

        $this->capture_ajax([$this->apple_pay, 'applepay_cancel_order']);

        $warnings = array_column(array_filter($this->log_spy->entries, static fn (array $entry): bool => 'warning' === $entry['level']), 'message');
        foreach ($expected_warnings as $expected) {
            self::assertContains(sprintf($expected, $order->get_id()), $warnings);
        }
        foreach ($this->log_spy->entries as $entry) {
            self::assertStringNotContainsString('sk_test_double', $entry['message']);
            self::assertStringNotContainsString('api response body', $entry['message']);
        }
    }

    public function merchant_key_provider(): array
    {
        $both = ['test' => 'sk_test_key', 'live' => 'sk_live_key'];
        $expires_date = time() + 3600;
        $oauth = [
            'jwt' => wp_json_encode([
                'test' => ['access_token' => 'jwt_test_token', 'expires_date' => $expires_date],
                'live' => ['access_token' => 'jwt_live_token', 'expires_date' => $expires_date],
            ]),
            'oauth_client_data' => wp_json_encode([
                'test' => ['client_id' => 'client_test', 'client_secret' => 'secret_test'],
                'live' => ['client_id' => 'client_live', 'client_secret' => 'secret_live'],
            ]),
        ];
        $stale_keys = ['test' => 'stale_test_token', 'live' => 'stale_live_token'];

        return [
            'test mode' => ['0', $both, [], 'sk_test_key', []],
            'live mode' => ['1', $both, [], 'sk_live_key', []],
            'live mode without live key' => ['1', ['test' => 'sk_test_key'], [], null, []],
            'test mode without test key' => ['0', ['live' => 'sk_live_key'], [], null, []],
            'no key' => ['1', [], [], null, []],
            'OAuth account, live mode' => ['1', $stale_keys, $oauth, 'jwt_live_token', ['client_live']],
            'OAuth account, test mode' => ['0', $stale_keys, $oauth, 'jwt_test_token', ['client_test']],
        ];
    }

    /**
     * @dataProvider merchant_key_provider
     *
     * @param string[] $validated_client_ids
     */
    public function test_merchant_client_uses_the_token_of_the_configured_mode(string $mode, array $api_key, array $oauth, ?string $expected_token, array $validated_client_ids): void
    {
        update_option('woocommerce_payplug_settings', ['mode' => $mode, 'api_key' => wp_json_encode($api_key)] + $oauth);
        $apple_pay = new ApplePayDouble();
        $apple_pay->api_service = new ApiDouble();

        $client = $apple_pay->configured_merchant_client();

        self::assertSame($expected_token, $client ? $client->getToken() : null);
        self::assertSame($client ? '2019-08-06' : null, $client ? $client->getApiVersion() : null);
        self::assertSame($validated_client_ids, $apple_pay->api_service->validated_client_ids);
    }

    /**
     * Pending order of the Apple Pay cart button started in this session, transaction id pay_test_<order id>,
     * whose payment the API finds unpaid.
     */
    private function make_cancellable_order(array $args = []): \WC_Order
    {
        $order = $this->make_order($args + ['transaction_id' => true, 'cart_order' => true]);
        $this->own_in_session($order);
        $this->apple_pay->merchant_client = new Payplug('sk_test_double');
        $this->apple_pay->payment = new FakePayment($order->get_transaction_id() ?: 'pay_test_' . $order->get_id(), $order->get_id());

        return $order;
    }

    /**
     * @param callable[] $scenarios
     */
    private function with_and_without_posted_payment_id(array $scenarios): array
    {
        $cases = [];
        foreach ($scenarios as $name => $scenario) {
            $cases[$name . ', payment id posted'] = [$scenario, true];
            $cases[$name . ', payment id omitted'] = [$scenario, false];
        }

        return $cases;
    }

    private function cancel_post(\WC_Order $order, bool $with_payment_id): array
    {
        $post = ['order_id' => (string) $order->get_id()];
        if ($with_payment_id) {
            $post['payment_id'] = $order->get_transaction_id();
        }

        return $post;
    }

    private function assert_api_called_with_the_merchant_client(FakePayment $payment): void
    {
        self::assertInstanceOf(Payplug::class, $this->apple_pay->merchant_client);
        self::assertNotEmpty($this->apple_pay->retrieve_clients);
        foreach (array_merge($this->apple_pay->retrieve_clients, $payment->abort_clients) as $client) {
            self::assertSame($this->apple_pay->merchant_client, $client);
        }
    }

    /**
     * Published variations of one variable product with a size attribute, deleted after the test.
     *
     * @param string[] $sizes
     *
     * @return \WC_Product_Variation[] keyed by size
     */
    private function make_variations(array $sizes): array
    {
        $attribute = new \WC_Product_Attribute();
        $attribute->set_name('size');
        $attribute->set_options($sizes);
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $parent = new \WC_Product_Variable();
        $parent->set_name('Variable product');
        $parent->set_attributes([$attribute]);
        $parent->set_status('publish');
        $parent->save();
        $this->fixture_product_ids[] = $parent->get_id();

        $variations = [];
        foreach ($sizes as $size) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($parent->get_id());
            $variation->set_attributes(['size' => $size]);
            $variation->set_regular_price('10');
            $variation->set_status('publish');
            $variation->save();
            $this->fixture_product_ids[] = $variation->get_id();
            $variations[$size] = $variation;
        }

        return $variations;
    }

    private function limit_dummy_orders(int $subject_limit, int $ip_limit): void
    {
        add_filter('payplug_applepay_dummy_order_rate_limit', static fn (): array => [
            'subject' => ['limit' => $subject_limit, 'window' => 600],
            'ip' => ['limit' => $ip_limit, 'window' => 600],
        ]);
    }

    /**
     * @return int[] sorted
     */
    private function pending_order_ids(): array
    {
        $ids = array_map('intval', wc_get_orders(['limit' => -1, 'return' => 'ids', 'status' => 'pending']));
        sort($ids);

        return $ids;
    }

    private function order_product_id(\WC_Order $order): int
    {
        $items = $order->get_items();

        return (int) reset($items)->get_product_id();
    }

    private function cancelled_error_response(): array
    {
        return ['status' => 200, 'body' => ['success' => false, 'data' => ['message' => 'Your order was cancelled.']], 'died' => true];
    }

    private function not_cancelled_error_response(): array
    {
        return ['status' => 200, 'body' => ['success' => false, 'data' => ['message' => 'Your order could not be cancelled.']], 'died' => true];
    }

    private function cancelled_success_response(): array
    {
        return ['status' => 200, 'body' => ['success' => true, 'data' => ['message' => 'Your order was cancelled.']], 'died' => true];
    }

    private function order_kept_response(): array
    {
        return ['status' => 200, 'body' => ['success' => false, 'data' => ['message' => 'Invalid order.']], 'died' => true];
    }

    /**
     * @param mixed $order_id null leaves order_id out of the payload
     */
    private function with_order_id($order_id, array $post): array
    {
        return null === $order_id ? $post : ['order_id' => $order_id] + $post;
    }
}

/**
 * Replaces the PayPlug API call and the payment answer sent to the browser; everything else is the production code.
 */
class ApplePayDouble extends ApplePay
{
    /**
     * @var string[]
     */
    public $calls = [];

    /**
     * @var FakePayment|\Throwable|null answer of retrieve_payment() once the queued results are used up, thrown when a \Throwable
     */
    public $payment;

    /**
     * @var array<FakePayment|\Throwable|null> answers of the next retrieve_payment() calls, in order
     */
    public $retrieve_results = [];

    /**
     * @var Payplug|null
     */
    public $merchant_client;

    /**
     * @var array payment id passed to each retrieve_payment() call
     */
    public $retrieved_payment_ids = [];

    /**
     * @var array client passed to each retrieve_payment() call
     */
    public $retrieve_clients = [];

    /**
     * @var Api|null replaces the Api service when set
     */
    public $api_service;

    /**
     * @var array|null state seen when the payment would be sent to the browser
     */
    public $cart_payment;

    public function process_cart_payment($order, $gateway): void
    {
        $this->cart_payment = [
            'order_id' => $order->get_id(),
            'session_order_id' => absint(WC()->session->get('order_awaiting_payment')),
            'cart_empty' => WC()->cart->is_empty(),
        ];
    }

    public function configured_merchant_client(): ?Payplug
    {
        return parent::get_merchant_client();
    }

    protected function retrieve_payment($payment_id, $payplug = null)
    {
        $this->calls[] = 'retrieve_payment';
        $this->retrieved_payment_ids[] = $payment_id;
        $this->retrieve_clients[] = $payplug;
        $result = $this->retrieve_results ? array_shift($this->retrieve_results) : $this->payment;
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }

    protected function get_merchant_client(): ?Payplug
    {
        $this->calls[] = 'get_merchant_client';

        return $this->merchant_client;
    }

    public function get_api()
    {
        return $this->api_service ?? parent::get_api();
    }
}

/**
 * Api service whose JWT validation accepts the stored token without calling PayPlug.
 */
class ApiDouble extends Api
{
    /**
     * @var string[] client id of each validate_jwt() call
     */
    public $validated_client_ids = [];

    protected function validate_jwt($oauth_client_data = [], $jwt = [])
    {
        $this->validated_client_ids[] = $oauth_client_data['client_id'] ?? '';

        return ['result' => true, 'token' => $jwt, 'need_update' => false];
    }
}
