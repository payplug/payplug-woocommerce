<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\FakeGateway;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\FakePayment;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\PayplugWoocommerceRequestDouble;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use Payplug\Resource\PaymentPaymentFailure;
use PHPUnit\Framework\TestCase;

class PayplugWoocommerceRequest_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    private const UNKNOWN_ORDER_ID = 987654321;

    /**
     * @var PayplugWoocommerceRequestDouble
     */
    private $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        $this->request = new PayplugWoocommerceRequestDouble();
    }

    protected function tearDown(): void
    {
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    /**
     * Each case returns [the POST payload, the id of the order that must stay untouched or null].
     */
    public function foreign_payment_provider(): array
    {
        return [
            'unknown payment id' => [static fn (self $t): array => [['payment_id' => 'pay_test_unknown'], $t->make_order(['transaction_id' => true])->get_id()]],
            'payment of an order the visitor did not start' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);

                return [['payment_id' => $order->get_transaction_id()], $order->get_id()];
            }],
            'session holds another order' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);
                $t->own_in_session($t->make_order(['transaction_id' => true]));

                return [['payment_id' => $order->get_transaction_id()], $order->get_id()];
            }],
            'logged-in user who is not the customer' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true, 'customer_id' => $t->make_customer()]);
                wp_set_current_user($t->make_customer());

                return [['payment_id' => $order->get_transaction_id()], $order->get_id()];
            }],
            'posted order id of an owned order' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);
                $owned = $t->make_order(['transaction_id' => true]);
                $t->own_in_session($owned);

                return [['payment_id' => $order->get_transaction_id(), 'order_id' => (string) $owned->get_id()], $order->get_id()];
            }],
            'array payment id' => [static function (self $t): array {
                $order = $t->make_order(['transaction_id' => true]);

                return [['payment_id' => [$order->get_transaction_id()]], $order->get_id()];
            }],
        ];
    }

    /**
     * @dataProvider foreign_payment_provider
     */
    public function test_applepay_update_payment_refuses_a_foreign_payment_before_any_api_call(callable $scenario): void
    {
        list($post, $order_id) = $scenario($this);
        $_POST = $post + ['payment_token' => ['paymentData' => 'token']];
        $before = $this->order_snapshot($order_id);

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame($before, $this->order_snapshot($order_id));
    }

    public function missing_payment_id_provider(): array
    {
        return [
            'no payment id' => [[]],
            'empty payment id' => [['payment_id' => '']],
        ];
    }

    /**
     * @dataProvider missing_payment_id_provider
     */
    public function test_applepay_update_payment_without_payment_id_is_refused_before_any_api_call(array $post): void
    {
        $this->own_in_session($this->make_order(['transaction_id' => true]));
        $_POST = $post;

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
    }

    public function owner_non_string_payment_id_provider(): array
    {
        return [
            'array of the transaction id' => [static fn (\WC_Order $order): array => [$order->get_transaction_id()]],
            'nested array' => [static fn (\WC_Order $order): array => [[$order->get_transaction_id()]]],
        ];
    }

    /**
     * @dataProvider owner_non_string_payment_id_provider
     */
    public function test_applepay_update_payment_owner_with_a_non_string_payment_id_is_refused_before_the_lookup(callable $payment_id): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['payment_id' => $payment_id($order), 'payment_token' => 'token'];
        $lookups = $this->count_order_queries();

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame(0, $lookups->count);
        self::assertSame([], $this->request->payment->updates);
    }

    public function test_applepay_update_payment_owner_sets_the_payment_token(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['payment_id' => $order->get_transaction_id(), 'payment_token' => ['paymentData' => 'token']];

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertSame(['status' => 200, 'body' => ['success' => true, 'data' => ['result' => false]], 'died' => true], $response);
        self::assertSame(['init_api', 'retrieve_payment'], $this->request->calls);
        self::assertSame([['apple_pay' => ['payment_token' => ['paymentData' => 'token']]]], $this->request->payment->updates);
    }

    public function test_applepay_update_payment_logged_in_customer_of_the_order_is_allowed(): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['transaction_id' => true, 'customer_id' => $user_id]);
        wp_set_current_user($user_id);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->is_paid = true;
        $_POST = ['payment_id' => $order->get_transaction_id(), 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertSame(['success' => true, 'data' => ['result' => true]], $response['body']);
        self::assertCount(1, $this->request->payment->updates);
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
    public function test_applepay_update_payment_refuses_a_payment_whose_metadata_names_another_order(callable $metadata_order_id): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $metadata_order_id($order->get_id()));
        $_POST = ['payment_id' => $order->get_transaction_id(), 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->payment->updates);
    }

    public function test_applepay_update_payment_stops_when_the_api_cannot_be_initialised(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->init_api_result = false;
        $_POST = ['payment_id' => $order->get_transaction_id(), 'payment_token' => 'token'];

        $response = $this->capture_ajax([$this->request, 'applepay_update_payment']);

        self::assertFalse($response['died']);
        self::assertSame(['init_api'], $this->request->calls);
    }

    /**
     * @dataProvider foreign_payment_provider
     */
    public function test_check_payment_refuses_a_foreign_payment_before_any_api_call(callable $scenario): void
    {
        list($post, $order_id) = $scenario($this);
        $_POST = $post;
        $before = $this->order_snapshot($order_id);

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame($before, $this->order_snapshot($order_id));
        self::assertSame('pending', $before['status']);
    }

    /**
     * @dataProvider missing_payment_id_provider
     */
    public function test_check_payment_without_payment_id_keeps_the_invalid_request_answer(array $post): void
    {
        $_POST = $post;

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame(['status' => 200, 'body' => ['success' => false, 'data' => 'Invalid request.'], 'died' => true], $response);
        self::assertSame([], $this->request->calls);
    }

    /**
     * @dataProvider owner_non_string_payment_id_provider
     */
    public function test_check_payment_owner_with_a_non_string_payment_id_is_refused_before_the_lookup(callable $payment_id): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['payment_id' => $payment_id($order)];
        $before = $this->order_snapshot($order->get_id());
        $lookups = $this->count_order_queries();

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame(0, $lookups->count);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
    }

    public function test_check_payment_owner_with_unpaid_payment_fails_the_order(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame(200, $response['status']);
        self::assertFalse($response['body']['success']);
        self::assertSame(500, $response['body']['data']['code']);
        self::assertSame(esc_url_raw($order->get_cancel_order_url_raw()), $response['body']['data']['cancel_url']);
        self::assertSame(['load_payment'], $this->request->calls);
        self::assertSame('failed', wc_get_order($order->get_id())->get_status());
        self::assertFailureNote(sprintf('Payplug payment %s was not completed.', $order->get_transaction_id()), $order->get_id());
    }

    public function test_check_payment_owner_with_refused_payment_returns_the_failure(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->failure = (object) ['code' => 'card_declined', 'message' => 'The card was declined.'];
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame('card_declined', $response['body']['data']['code']);
        self::assertSame('The card was declined.', $response['body']['data']['message']);
        self::assertSame('failed', wc_get_order($order->get_id())->get_status());
        self::assertFailureNote(sprintf('Payplug payment %s failed (card_declined: The card was declined.).', $order->get_transaction_id()), $order->get_id());
    }

    public function test_check_payment_strips_markup_from_the_failure_in_the_order_note(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->failure = (object) ['code' => '<b>card_declined</b>', 'message' => '<script>alert(1)</script>The card was <a href="https://evil.test">declined</a>.'];
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $this->capture_ajax([$this->request, 'check_payment']);

        self::assertFailureNote(sprintf('Payplug payment %s failed (card_declined: The card was declined.).', $order->get_transaction_id()), $order->get_id());
    }

    public function test_check_payment_with_a_3ds_declined_payment_fails_the_order_and_returns_the_code(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->failure = (object) ['code' => '3ds_declined', 'message' => 'The 3DS authentication failed.'];
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertFalse($response['body']['success']);
        self::assertSame('3ds_declined', $response['body']['data']['code']);
        self::assertSame('The 3DS authentication failed.', $response['body']['data']['message']);
        self::assertSame(esc_url_raw($order->get_cancel_order_url_raw()), $response['body']['data']['cancel_url']);
        self::assertSame('failed', wc_get_order($order->get_id())->get_status());
        self::assertFailureNote(sprintf('Payplug payment %s failed (3ds_declined: The 3DS authentication failed.).', $order->get_transaction_id()), $order->get_id());
    }

    public function incomplete_failure_provider(): array
    {
        return [
            'without a code' => [(object) ['message' => 'The card was declined.']],
            'without a message' => [(object) ['code' => 'card_declined']],
            'with a null message' => [(object) ['code' => 'card_declined', 'message' => null]],
            'with a code that is only markup' => [(object) ['code' => '<b></b>', 'message' => 'The card was declined.']],
            'with no fields' => [(object) []],
            'SDK failure without a message' => [PaymentPaymentFailure::fromAttributes(['code' => 'card_declined'])],
            'SDK failure without a code' => [PaymentPaymentFailure::fromAttributes(['message' => 'The card was declined.'])],
        ];
    }

    /**
     * @dataProvider incomplete_failure_provider
     *
     * @param object $failure
     */
    public function test_check_payment_with_an_incomplete_failure_fails_the_order_with_the_generic_note($failure): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->failure = $failure;
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame(200, $response['status']);
        self::assertFalse($response['body']['success']);
        self::assertSame('failed', wc_get_order($order->get_id())->get_status());
        self::assertFailureNote(sprintf('Payplug payment %s was not completed.', $order->get_transaction_id()), $order->get_id());
    }

    public function paid_order_status_provider(): array
    {
        return [
            'still pending' => ['pending'],
            'already marked paid by the IPN' => ['processing'],
        ];
    }

    /**
     * @dataProvider paid_order_status_provider
     */
    public function test_check_payment_owner_with_paid_payment_gets_the_return_url(string $status): void
    {
        $order = $this->make_order(['transaction_id' => true, 'status' => $status]);
        $this->own_in_session($order);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->is_paid = true;
        $this->request->payment->paid_at = 1700000000;
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertSame([
            'status' => 200,
            'body' => ['success' => true, 'data' => [
                'payment_id' => $order->get_transaction_id(),
                'result' => 'success',
                'redirect' => esc_url_raw($order->get_checkout_order_received_url()),
                'cancel' => null,
            ]],
            'died' => true,
        ], $response);
        self::assertSame($status, wc_get_order($order->get_id())->get_status());
    }

    public function test_check_payment_logged_in_customer_of_the_order_is_allowed(): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['transaction_id' => true, 'customer_id' => $user_id]);
        wp_set_current_user($user_id);
        $this->request->payment = new FakePayment($order->get_transaction_id(), $order->get_id());
        $this->request->payment->is_paid = true;
        $this->request->payment->paid_at = 1700000000;
        $_POST = ['payment_id' => $order->get_transaction_id()];

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertTrue($response['body']['success']);
    }

    public function test_check_payment_leaves_the_order_untouched_when_the_payment_cannot_be_loaded(): void
    {
        $order = $this->make_order(['transaction_id' => true]);
        $this->own_in_session($order);
        $this->request->payment = null;
        $_POST = ['payment_id' => $order->get_transaction_id()];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->request, 'check_payment']);

        self::assertFalse($response['died']);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
    }

    public function test_create_payment_intent_refuses_an_invalid_nonce_before_anything_else(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $_POST = [
            'woocommerce-process-checkout-nonce' => 'not-a-nonce',
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => 'apple_pay',
        ];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
    }

    /**
     * Each case returns [the POST payload without nonce, the id of the order that must stay untouched or null].
     */
    public function foreign_intent_provider(): array
    {
        return [
            'unknown order' => [static fn (self $t): array => [['order_id' => (string) self::UNKNOWN_ORDER_ID, 'gateway' => 'apple_pay'], null]],
            'unknown order with a key' => [static fn (self $t): array => [['order_id' => (string) self::UNKNOWN_ORDER_ID, 'order_pay_key' => 'wc_order_x', 'gateway' => 'apple_pay'], null]],
            'no order id' => [static fn (self $t): array => [['gateway' => 'apple_pay'], null]],
            'zero order id' => [static fn (self $t): array => [['order_id' => '0', 'gateway' => 'apple_pay'], null]],
            'non numeric order id' => [static fn (self $t): array => [['order_id' => 'abc', 'gateway' => 'apple_pay'], null]],
            'array order id' => [static fn (self $t): array => [['order_id' => ['1'], 'gateway' => 'apple_pay'], null]],
            'order the visitor did not start, no key' => [static function (self $t): array {
                $order = $t->make_order();

                return [['order_id' => (string) $order->get_id(), 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'order the visitor did not start, wrong key' => [static function (self $t): array {
                $order = $t->make_order();

                return [['order_id' => (string) $order->get_id(), 'order_pay_key' => 'wc_order_wrong', 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'key of another order' => [static function (self $t): array {
                $order = $t->make_order();

                return [['order_id' => (string) $order->get_id(), 'order_pay_key' => $t->make_order()->get_order_key(), 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'session owner with a wrong key' => [static function (self $t): array {
                $order = $t->make_order();
                $t->own_in_session($order);

                return [['order_id' => (string) $order->get_id(), 'order_pay_key' => 'wc_order_wrong', 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'logged-in user who is not the customer' => [static function (self $t): array {
                $order = $t->make_order(['customer_id' => $t->make_customer()]);
                wp_set_current_user($t->make_customer());

                return [['order_id' => (string) $order->get_id(), 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'foreign order and unknown gateway' => [static function (self $t): array {
                $order = $t->make_order();

                return [['order_id' => (string) $order->get_id(), 'gateway' => 'unknown_gateway'], $order->get_id()];
            }],
            'foreign order and array gateway' => [static function (self $t): array {
                $order = $t->make_order();

                return [['order_id' => (string) $order->get_id(), 'gateway' => ['apple_pay']], $order->get_id()];
            }],
            'foreign paid order' => [static function (self $t): array {
                $order = $t->make_order(['status' => 'processing']);

                return [['order_id' => (string) $order->get_id(), 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'foreign order and array key' => [static function (self $t): array {
                $order = $t->make_order();

                return [['order_id' => (string) $order->get_id(), 'order_pay_key' => [$order->get_order_key()], 'gateway' => 'apple_pay'], $order->get_id()];
            }],
            'unknown order and array key' => [static fn (self $t): array => [['order_id' => (string) self::UNKNOWN_ORDER_ID, 'order_pay_key' => ['wc_order_x'], 'gateway' => 'apple_pay'], null]],
            'session owner with an array key' => [static function (self $t): array {
                $order = $t->make_order();
                $t->own_in_session($order);

                return [['order_id' => (string) $order->get_id(), 'order_pay_key' => [$order->get_order_key()], 'gateway' => 'apple_pay'], $order->get_id()];
            }],
        ];
    }

    /**
     * @dataProvider foreign_intent_provider
     */
    public function test_create_payment_intent_refuses_a_foreign_order_uniformly(callable $scenario): void
    {
        $this->request->gateway_double = new FakeGateway('apple_pay');
        list($post, $order_id) = $scenario($this);
        $_POST = $post + ['woocommerce-process-checkout-nonce' => $this->checkout_nonce()];
        $before = null === $order_id ? null : $this->order_snapshot($order_id);
        $session_before = WC()->session->get('order_awaiting_payment');

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertSame($this->forbidden_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame($session_before, WC()->session->get('order_awaiting_payment'));
        if (null !== $order_id) {
            self::assertSame($before, $this->order_snapshot($order_id));
        }
    }

    public function test_create_payment_intent_with_valid_key_creates_the_payment_and_remembers_the_order(): void
    {
        $order = $this->make_order();
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $this->request->payment = new FakePayment('pay_test_intent', $order->get_id());
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        $saved = wc_get_order($order->get_id());
        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['success']);
        self::assertSame('pay_test_intent', $response['body']['data']['payment_id']);
        self::assertSame(esc_url_raw($saved->get_checkout_payment_url()), $response['body']['data']['cancel']);
        self::assertSame(['create_payment'], $this->request->calls);
        self::assertSame($order->get_id(), $this->request->payment_data['metadata']['order_id']);
        self::assertSame('pay_test_intent', $saved->get_transaction_id());
        self::assertTrue(PayplugWoocommerceHelper::session_owns_order($saved));
        self::assertSame($order->get_id(), $this->persisted_session_value('order_awaiting_payment'));
    }

    public function test_create_payment_intent_session_owner_without_key_is_allowed(): void
    {
        $order = $this->make_order();
        $this->own_in_session($order);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $this->request->payment = new FakePayment('pay_test_intent', $order->get_id());
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertTrue($response['body']['success']);
        self::assertSame(esc_url_raw($order->get_cancel_order_url_raw()), $response['body']['data']['cancel']);
        self::assertSame(['create_payment'], $this->request->calls);
    }

    public function test_create_payment_intent_logged_in_customer_without_key_is_allowed(): void
    {
        $user_id = $this->make_customer();
        $order = $this->make_order(['customer_id' => $user_id]);
        wp_set_current_user($user_id);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $this->request->payment = new FakePayment('pay_test_intent', $order->get_id());
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertTrue($response['body']['success']);
        self::assertSame(['create_payment'], $this->request->calls);
    }

    public function test_create_payment_intent_owner_with_unknown_gateway_gets_the_gateway_error_not_403(): void
    {
        $order = $this->make_order();
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => 'unknown_gateway',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertSame(['status' => 200, 'body' => ['success' => false, 'data' => 'Invalid order.'], 'died' => true], $response);
        self::assertSame([], $this->request->calls);
        self::assertSame(0, $this->session_order_id());
    }

    public function non_payable_status_provider(): array
    {
        return [
            'processing' => ['processing'],
            'completed' => ['completed'],
            'refunded' => ['refunded'],
            'cancelled' => ['cancelled'],
            'on-hold' => ['on-hold'],
        ];
    }

    /**
     * @dataProvider non_payable_status_provider
     */
    public function test_create_payment_intent_refuses_an_order_that_no_longer_needs_payment(string $status): void
    {
        $order = $this->make_order(['status' => $status, 'transaction_id' => 'pay_test_previous']);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => 'apple_pay',
        ];
        $before = $this->order_snapshot($order->get_id());

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertSame(409, $response['status']);
        self::assertFalse($response['body']['success']);
        self::assertStringContainsString('cannot be paid for', $response['body']['data']);
        self::assertStringContainsString(wc_get_order_status_name($status), $response['body']['data']);
        // Blocks puts this string in a JS Error as is: it must be plain text, not HTML entities.
        self::assertNotRegExp('/&[a-z]+;|&#\d+;/i', $response['body']['data']);
        self::assertSame([], $this->request->calls);
        self::assertSame($before, $this->order_snapshot($order->get_id()));
        self::assertSame(0, $this->session_order_id());
    }

    public function test_create_payment_intent_refuses_a_zero_total_order(): void
    {
        $order = $this->make_order(['with_items' => false]);
        $this->own_in_session($order);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertSame(409, $response['status']);
        self::assertSame([], $this->request->calls);
    }

    public function test_create_payment_intent_refuses_a_paid_order_for_its_session_owner(): void
    {
        $order = $this->make_order(['status' => 'processing']);
        $this->own_in_session($order);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertSame(409, $response['status']);
        self::assertSame([], $this->request->calls);
    }

    public function payable_status_provider(): array
    {
        return [
            'pending' => ['pending'],
            'failed' => ['failed'],
        ];
    }

    /**
     * @dataProvider payable_status_provider
     */
    public function test_create_payment_intent_allows_a_retry_on_a_payable_order(string $status): void
    {
        $order = $this->make_order(['status' => $status, 'transaction_id' => 'pay_test_previous']);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $this->request->payment = new FakePayment('pay_test_retry', $order->get_id());
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertTrue($response['body']['success']);
        self::assertSame('pay_test_retry', wc_get_order($order->get_id())->get_transaction_id());
    }

    public function test_create_payment_intent_follows_the_woocommerce_payable_statuses_filter(): void
    {
        add_filter('woocommerce_valid_order_statuses_for_payment', static function (array $statuses): array {
            $statuses[] = 'on-hold';

            return $statuses;
        });
        $order = $this->make_order(['status' => 'on-hold']);
        $this->request->gateway_double = new FakeGateway('apple_pay');
        $this->request->payment = new FakePayment('pay_test_on_hold', $order->get_id());
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_pay_key' => $order->get_order_key(),
            'gateway' => 'apple_pay',
        ];

        $response = $this->capture_ajax([$this->request, 'create_payment_intent']);

        self::assertTrue($response['body']['success']);
        self::assertSame(['create_payment'], $this->request->calls);
    }

    public function test_apple_pay_create_order_pay_refuses_an_invalid_nonce(): void
    {
        $order = $this->make_order();
        $_POST = ['woocommerce-process-checkout-nonce' => 'not-a-nonce', 'order_id' => (string) $order->get_id(), 'order_key' => $order->get_order_key()];

        $response = $this->capture_ajax([$this->request, 'ajax_apple_pay_create_order_pay']);

        self::assertSame($this->order_pay_invalid_order_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame(0, $this->session_order_id());
    }

    /**
     * Each case returns the POST payload without nonce.
     */
    public function wrong_order_pay_key_provider(): array
    {
        return [
            'wrong key' => [static fn (self $t): array => ['order_id' => (string) $t->make_order()->get_id(), 'order_key' => 'wc_order_wrong']],
            'no key' => [static fn (self $t): array => ['order_id' => (string) $t->make_order()->get_id()]],
            'empty key' => [static fn (self $t): array => ['order_id' => (string) $t->make_order()->get_id(), 'order_key' => '']],
            'key of another order' => [static fn (self $t): array => ['order_id' => (string) $t->make_order()->get_id(), 'order_key' => $t->make_order()->get_order_key()]],
            'unknown order' => [static fn (self $t): array => ['order_id' => (string) self::UNKNOWN_ORDER_ID, 'order_key' => 'wc_order_x']],
            'array order id' => [static fn (self $t): array => ['order_id' => ['1'], 'order_key' => 'wc_order_x']],
            'array key' => [static function (self $t): array {
                $order = $t->make_order();

                return ['order_id' => (string) $order->get_id(), 'order_key' => [$order->get_order_key()]];
            }],
            'unknown order and array key' => [static fn (self $t): array => ['order_id' => (string) self::UNKNOWN_ORDER_ID, 'order_key' => ['wc_order_x']]],
        ];
    }

    /**
     * @dataProvider wrong_order_pay_key_provider
     */
    public function test_apple_pay_create_order_pay_refuses_a_wrong_key(callable $scenario): void
    {
        $_POST = $scenario($this) + ['woocommerce-process-checkout-nonce' => $this->checkout_nonce()];

        $response = $this->capture_ajax([$this->request, 'ajax_apple_pay_create_order_pay']);

        self::assertSame($this->order_pay_invalid_order_response(), $response);
        self::assertSame([], $this->request->calls);
        self::assertSame(0, $this->session_order_id());
    }

    /**
     * @dataProvider non_payable_status_provider
     */
    public function test_apple_pay_create_order_pay_refuses_an_order_that_no_longer_needs_payment(string $status): void
    {
        $order = $this->make_order(['status' => $status]);
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_key' => $order->get_order_key(),
        ];

        $response = $this->capture_ajax([$this->request, 'ajax_apple_pay_create_order_pay']);

        self::assertSame(200, $response['status']);
        self::assertSame('failure', $response['body']['result']);
        self::assertStringContainsString('<ul class="woocommerce-error"><li>', $response['body']['messages']);
        self::assertStringContainsString('cannot be paid for', $response['body']['messages']);
        self::assertStringContainsString(wc_get_order_status_name($status), $response['body']['messages']);
        self::assertSame([], $this->request->calls);
        self::assertSame(0, $this->session_order_id());
    }

    /**
     * @dataProvider payable_status_provider
     */
    public function test_apple_pay_create_order_pay_processes_a_payable_order_with_a_valid_key(string $status): void
    {
        $order = $this->make_order(['status' => $status]);
        $gateway = $this->make_available_gateway('apple_pay');
        $gateway->process_result = ['result' => 'success', 'redirect' => 'https://example.test/pay'];
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_key' => $order->get_order_key(),
        ];

        $response = $this->capture_ajax([$this->request, 'ajax_apple_pay_create_order_pay']);

        self::assertSame(['status' => 200, 'body' => ['result' => 'success', 'redirect' => 'https://example.test/pay'], 'died' => true], $response);
        self::assertSame([$order->get_id()], $gateway->processed);
        self::assertSame($order->get_id(), $this->session_order_id());
    }

    public function test_apple_pay_create_order_pay_remembers_the_order_after_the_availability_check_empties_the_cart(): void
    {
        $order = $this->make_order();
        WC()->cart->add_to_cart($this->make_product()->get_id());
        $gateway = $this->make_available_gateway('apple_pay');
        add_filter('woocommerce_available_payment_gateways', static function ($gateways) {
            WC()->cart->empty_cart();

            return $gateways;
        });
        $session_at_payment = null;
        $gateway->on_process = static function () use (&$session_at_payment): void {
            $session_at_payment = absint(WC()->session->get('order_awaiting_payment'));
        };
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_key' => $order->get_order_key(),
        ];

        $this->capture_ajax([$this->request, 'ajax_apple_pay_create_order_pay']);

        self::assertTrue(WC()->cart->is_empty());
        self::assertSame($order->get_id(), $session_at_payment);
        self::assertSame($order->get_id(), $this->session_order_id());
        self::assertSame($order->get_id(), $this->persisted_session_value('order_awaiting_payment'));
    }

    public function test_apple_pay_create_order_pay_does_not_remember_the_order_when_apple_pay_is_unavailable(): void
    {
        $order = $this->make_order();
        $_POST = [
            'woocommerce-process-checkout-nonce' => $this->checkout_nonce(),
            'order_id' => (string) $order->get_id(),
            'order_key' => $order->get_order_key(),
        ];

        $response = $this->capture_ajax([$this->request, 'ajax_apple_pay_create_order_pay']);

        self::assertSame('failure', $response['body']['result']);
        self::assertStringContainsString('Apple Pay not available.', $response['body']['messages']);
        self::assertSame(0, $this->session_order_id());
    }

    public function test_process_order_payment_remembers_the_order_in_a_saved_session(): void
    {
        $order = $this->make_order();
        $gateway = $this->make_available_gateway('payplug_test_gateway');

        $response = $this->capture_ajax(function () use ($order): void {
            $this->request->run_process_order_payment($order->get_id(), 'payplug_test_gateway');
        });

        self::assertFalse($response['died']);
        self::assertSame([$order->get_id()], $gateway->processed);
        self::assertSame($order->get_id(), $this->session_order_id());
        self::assertTrue($this->session_cookie_started());
        self::assertSame($order->get_id(), $this->persisted_session_value('order_awaiting_payment'));
    }

    public function test_process_order_payment_does_not_remember_the_order_for_an_unavailable_gateway(): void
    {
        $order = $this->make_order();

        $this->capture_ajax(function () use ($order): void {
            $this->request->run_process_order_payment($order->get_id(), 'payplug_test_gateway');
        });

        self::assertSame(0, $this->session_order_id());
    }

    private function order_pay_invalid_order_response(): array
    {
        return [
            'status' => 200,
            'body' => ['result' => 'failure', 'messages' => '<ul class="woocommerce-error"><li>Invalid order.</li></ul>'],
            'died' => true,
        ];
    }

    /**
     * Counts the wc_get_orders() queries run from now on.
     */
    private function count_order_queries(): \stdClass
    {
        $lookups = (object) ['count' => 0];
        add_filter('woocommerce_order_query_args', static function ($args) use ($lookups) {
            $lookups->count++;

            return $args;
        });

        return $lookups;
    }

    private static function assertFailureNote(string $expected, int $order_id): void
    {
        $notes = wc_get_order_notes(['order_id' => $order_id, 'limit' => 1]);

        self::assertCount(1, $notes);
        self::assertStringStartsWith($expected, $notes[0]->content);
        self::assertStringNotContainsString('Order cancelled by customer.', $notes[0]->content);
    }

    private function make_available_gateway(string $id): FakeGateway
    {
        $gateway = new FakeGateway($id);
        add_filter('woocommerce_available_payment_gateways', static function ($gateways) use ($gateway) {
            $gateways[$gateway->id] = $gateway;

            return $gateways;
        });

        return $gateway;
    }
}
