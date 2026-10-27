<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\HostedFieldsPaymentProcessor;
use Payplug\PayplugWoocommerce\Upc\UpcTransactionMetadataRecorder;
use PayplugUnifiedCore\DataValues\PaymentOutcome;
use PHPUnit\Framework\TestCase;

class PayplugCreditCard_test extends TestCase
{
    private array $base_settings = [
        'enabled' => true,
        'mode' => false,
        'payment_methods' => [
            'configuration' => [
                'payplug' => [
                    'active' => true,
                    'title' => 'Credit card checkout',
                    'description' => '',
                    'save_card' => false,
                    'embedded_mode' => 'redirect',
                ],
            ],
        ],
    ];

    protected function tearDown(): void
    {
        if (UhfCard::check_table_exists()) {
            global $wpdb;
            $wpdb->query('TRUNCATE TABLE ' . $wpdb->base_prefix . 'woocommerce_payplug_uhf_cards');
        }
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_currency');
        delete_transient('payplug_config_test');
        remove_all_filters('pre_http_request');
        // WooCommerceConfigurationRepository::getClientId() returns '' in every test here (no
        // oauth_client_data option set), so every stubbed OAuth exchange below mints and caches
        // a token under the same transient key - clear it, or a later test that expects a real
        // network failure would instead silently reuse a token this class minted earlier.
        delete_transient('upc_token_upc_oauth_token:');
        parent::tearDown();
    }

    /**
     * Stubs both legs a UPC refund call makes: the OAuth2 token exchange (always a canned
     * success - its own behavior isn't what these tests are about) and the refund endpoint
     * itself, distinguished by URL. $captured_refund_request, if given, receives
     * ['url' => ..., 'body' => <decoded JSON body>] from the refund call specifically, or stays
     * null if the refund endpoint is never actually reached (e.g. a pre-flight guard rejected
     * first).
     */
    private function stub_upc_http_requests(array $refund_response, ?array &$captured_refund_request = null): void
    {
        $captured_refund_request = null;

        add_filter('pre_http_request', function ($preempt, $args, $url) use ($refund_response, &$captured_refund_request) {
            if (false !== strpos($url, '/oauth2/token')) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
                    'headers' => [],
                    'cookies' => [],
                ];
            }

            if (false !== strpos($url, '/refund')) {
                $captured_refund_request = ['url' => $url, 'body' => json_decode($args['body'], true)];

                return $refund_response;
            }

            return $preempt;
        }, 10, 3);
    }

    public function test_gateway_enabled_when_module_enabled_as_boolean(): void
    {
        // AccountGateway::register() persists 'enabled' => true (bool), not 'yes'
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('yes', $gateway->enabled);
    }

    public function test_gateway_enabled_when_module_enabled_as_string_yes(): void
    {
        update_option('woocommerce_currency', 'EUR');
        $settings = $this->base_settings;
        $settings['enabled'] = 'yes';
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('yes', $gateway->enabled);
    }

    public function test_gateway_disabled_when_cb_method_inactive(): void
    {
        update_option('woocommerce_currency', 'EUR');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['active'] = false;
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_disabled_when_module_disabled(): void
    {
        update_option('woocommerce_currency', 'EUR');
        $settings = $this->base_settings;
        $settings['enabled'] = false;
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_enabled_on_legacy_config_without_active_key(): void
    {
        // Legacy config pre-migration: 'payment_methods' key absent → null fallback → enabled
        update_option('woocommerce_currency', 'EUR');
        $settings = $this->base_settings;
        unset($settings['payment_methods']);
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('yes', $gateway->enabled);
    }

    public function test_has_fields_enabled_for_hosted_fields_mode(): void
    {
        // Non-EUR: embedded_mode normalizes to 'hosted_fields' regardless of what's
        // stored, so this is set explicitly to prove has_fields follows the normalized
        // value, not merely the config's own already-matching 'hosted_fields'.
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertTrue($gateway->has_fields);
    }

    public function test_embedded_mode_normalizes_to_redirect_on_eur_shop_with_stored_hosted_fields(): void
    {
        update_option('woocommerce_currency', 'EUR');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('redirect', $gateway->embedded_mode);
        self::assertFalse($gateway->has_fields);
    }

    public function test_embedded_mode_normalizes_to_hosted_fields_on_non_eur_shop_with_stored_popup(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'popup';
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('hosted_fields', $gateway->embedded_mode);
        self::assertTrue($gateway->has_fields);
    }

    public function test_process_payment_returns_failure_result_when_hf_token_is_missing(): void
    {
        // Non-EUR: embedded_mode normalizes to 'hosted_fields' regardless of what's
        // stored, matching the constructor's currency normalization (see the two tests
        // above) - set explicitly so this test doesn't depend on the test suite's
        // default currency.
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        // A valid total: otherwise the new amount-range check below would reject this order
        // (wc_create_order() defaults to a 0 total) before this test's own missing-token branch
        // is ever reached.
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $_POST['hf_token'] = '';

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);

        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_returns_failure_result_when_order_amount_is_below_the_minimum(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        // PayplugWoocommerceHelper::get_minimum_amount() is a fixed 99 cents (0.99).
        $order = wc_create_order();
        $order->set_total(0.50);
        $order->save();

        $_POST['hf_token'] = 'hf_token_123';

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);

        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_returns_failure_result_when_order_amount_is_above_the_maximum(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        // PayplugWoocommerceHelper::get_maximum_amount() is a fixed 2,000,000 cents (20,000.00).
        $order = wc_create_order();
        $order->set_total(20001.00);
        $order->save();

        $_POST['hf_token'] = 'hf_token_123';

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);

        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_short_circuits_without_creating_a_new_payment_when_order_already_processing(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        $order->set_status('processing');
        $order->save();

        // No hf_token at all: if the guard didn't short-circuit first, process_payment() would
        // fall through to the missing-token failure branch instead of this success one.
        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('success', $result['result']);
        $this->assertSame($order->get_checkout_order_received_url(), $result['redirect']);

        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_fails_without_calling_the_api_when_another_request_for_the_same_order_is_in_flight(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        // A valid total: otherwise the new amount-range check would reject this order before the
        // lock-contention branch this test actually targets is ever reached.
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $lock_key = 'payplug_upc_process_payment_' . $order->get_id();
        $lock = new WooCommerceLock();
        $lock->acquire($lock_key, 30);

        // A real hf_token would otherwise let this fall through to a genuine (and, in this test
        // environment, network-less) API call - the lock must reject it before that point is ever
        // reached.
        $_POST['hf_token'] = 'hf_token_123';

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);

        $lock->release($lock_key);
        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_gateway_is_unavailable_when_the_hosted_fields_form_cannot_render(): void
    {
        // HOSTED_FIELDS_SDK_URL is empty in the test environment and no company_ref is stored,
        // so the forced hosted_fields mode of a non-EUR shop has no card form to render.
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        $gateway = new PayplugCreditCard();

        $this->assertFalse($gateway->hosted_fields_available);
        $this->assertFalse($gateway->is_available());
    }

    private function hosted_fields_unavailable_cause(PayplugCreditCard $gateway): ?string
    {
        $method = (new \ReflectionClass(PayplugCreditCard::class))->getMethod('hosted_fields_unavailable_cause');
        $method->setAccessible(true);

        return $method->invoke($gateway);
    }

    private function create_hosted_fields_gateway(string $oauth_client_data, string $identifier = 'ACC-1', bool $with_company_ref = true): PayplugCreditCard
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['oauth_client_data'] = $oauth_client_data;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = $identifier;
        update_option('woocommerce_payplug_settings', $settings);
        // GET /account result, so that only the OAuth2 credentials, the identifier and the SDK URL are left to check
        if ($with_company_ref) {
            set_transient('payplug_config_test', ['company_ref' => 'cr_1']);
        }

        return new PayplugCreditCard();
    }

    public function test_hosted_fields_are_not_offered_without_oauth2_credentials(): void
    {
        $gateway = $this->create_hosted_fields_gateway('{}');

        $this->assertSame('OAuth2 connection is missing for the current mode', $this->hosted_fields_unavailable_cause($gateway));
        $this->assertFalse($gateway->hosted_fields_available);
        $this->assertFalse($gateway->is_available());
    }

    public function test_hosted_fields_are_not_offered_when_oauth2_credentials_are_for_the_other_mode(): void
    {
        $gateway = $this->create_hosted_fields_gateway('{"live":{"client_id":"id","client_secret":"secret"},"test":[]}');

        $this->assertSame('OAuth2 connection is missing for the current mode', $this->hosted_fields_unavailable_cause($gateway));
        $this->assertFalse($gateway->is_available());
    }

    public function test_hosted_fields_are_not_offered_without_an_identifier(): void
    {
        $gateway = $this->create_hosted_fields_gateway('{"test":{"client_id":"id","client_secret":"secret"},"live":[]}', '');

        $this->assertSame('hosted_fields identifier (account id) is not set', $this->hosted_fields_unavailable_cause($gateway));
        $this->assertFalse($gateway->hosted_fields_available);
        $this->assertFalse($gateway->is_available());
    }

    public function test_hosted_fields_are_not_offered_with_a_whitespace_identifier(): void
    {
        $gateway = $this->create_hosted_fields_gateway('{"test":{"client_id":"id","client_secret":"secret"},"live":[]}', "  \t ");

        $this->assertSame('hosted_fields identifier (account id) is not set', $this->hosted_fields_unavailable_cause($gateway));
        $this->assertFalse($gateway->is_available());
    }

    public function test_missing_identifier_is_reported_before_the_account_data_is_needed(): void
    {
        $gateway = $this->create_hosted_fields_gateway('{"test":{"client_id":"id","client_secret":"secret"},"live":[]}', '', false);

        $this->assertSame('hosted_fields identifier (account id) is not set', $this->hosted_fields_unavailable_cause($gateway));
    }

    public function test_oauth2_credentials_for_the_current_mode_and_an_identifier_pass_the_hosted_fields_check(): void
    {
        $gateway = $this->create_hosted_fields_gateway('{"test":{"client_id":"id","client_secret":"secret"},"live":[]}');

        // HOSTED_FIELDS_SDK_URL is empty in the test environment: it is the only cause left
        $this->assertSame('HOSTED_FIELDS_SDK_URL is not configured', $this->hosted_fields_unavailable_cause($gateway));
    }

    /**
     * Stubs the Unified API for a reconciliation of a previous 3DS attempt: the OAuth2 token
     * exchange, then getOperation() answering $payment_body (the order has no alt id, so the
     * reconciler fetches the operation by its primary id). Any other call (a new payment
     * creation in particular) is recorded in $other_requests and answered with a 500.
     */
    private function stub_reconciliation(array $payment_body, ?array &$other_requests = null): void
    {
        $other_requests = [];

        add_filter('pre_http_request', function ($preempt, $args, $url) use ($payment_body, &$other_requests) {
            if (false !== strpos($url, '/oauth2/token')) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
                    'headers' => [],
                    'cookies' => [],
                ];
            }

            if (false !== strpos($url, '/processing-operations/operations/public/op_in_flight')) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body' => json_encode($payment_body),
                    'headers' => [],
                    'cookies' => [],
                ];
            }

            $other_requests[] = $url;

            return ['response' => ['code' => 500, 'message' => 'Error'], 'body' => '', 'headers' => [], 'cookies' => []];
        }, 10, 3);
    }

    private function create_order_with_a_pending_3ds_attempt(int $created_at): \WC_Order
    {
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_in_flight');
        $order->update_meta_data('_payplug_upc_outcome', PaymentOutcome::THREE_DS_PENDING);
        $order->update_meta_data('_payplug_upc_created_at', $created_at);
        $order->save();

        return $order;
    }

    public function test_process_payment_refuses_a_new_attempt_while_a_recent_3ds_attempt_is_still_pending(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        $order = $this->create_order_with_a_pending_3ds_attempt(time() - 60);
        $this->stub_reconciliation(['execCode' => '0001', 'amount' => 5000], $other_requests);
        $_POST['hf_token'] = 'hf_token_123';

        $result = (new PayplugCreditCard())->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_payment_in_progress');
        $this->assertSame([], $other_requests, 'No new payment must be created.');
        $this->assertSame('op_in_flight', wc_get_order($order->get_id())->get_meta('_payplug_upc_operation_id'));

        wc_clear_notices();
        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_redirects_to_order_received_when_the_pending_3ds_attempt_is_now_paid(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        $order = $this->create_order_with_a_pending_3ds_attempt(time() - 60);
        $order->add_product(\WC_Helper_Product::create_simple_product(), 1);
        $order->set_total(50.00);
        $order->save();
        $this->stub_reconciliation(['execCode' => '0000', 'amount' => 5000], $other_requests);
        $_POST['hf_token'] = 'hf_token_123';

        $result = (new PayplugCreditCard())->process_payment($order->get_id());
        $order = wc_get_order($order->get_id());

        $this->assertSame('success', $result['result']);
        $this->assertSame($order->get_checkout_order_received_url(), $result['redirect']);
        $this->assertSame('processing', $order->get_status());
        $this->assertSame('op_in_flight', $order->get_meta('_payplug_upc_operation_id'));

        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_allows_a_new_attempt_once_the_pending_3ds_attempt_is_older_than_the_window(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        update_option('woocommerce_payplug_settings', $settings);

        $order = $this->create_order_with_a_pending_3ds_attempt(time() - 3600);
        $this->stub_reconciliation(['execCode' => '0001', 'amount' => 5000], $other_requests);
        // No hf_token: reaching the missing-token branch proves the in-flight guard let the
        // request through, without ever calling the API.
        $_POST['hf_token'] = '';

        $result = (new PayplugCreditCard())->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_missing_token');
        $this->assertSame([], $other_requests);

        wc_clear_notices();
        unset($_POST['hf_token']);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_payment_pays_with_a_saved_alias_when_a_card_choice_is_posted(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $settings['payment_methods']['configuration']['payplug']['save_card'] = true;
        update_option('woocommerce_payplug_settings', $settings);

        $user_id = wp_create_user('uhf_owner_' . wp_rand(), wp_generate_password());
        wp_set_current_user($user_id);

        // A valid total: otherwise the amount-range check rejects this order (wc_create_order()
        // defaults to a 0 total) before this test's own alias-choice branch is ever reached -
        // same gotcha as test_process_payment_returns_failure_result_when_hf_token_is_missing.
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $card_id = UhfCard::insert($user_id, 'alias_payment_1', 'VISA', '4242', 12, 2099, 'test');

        $_POST['payplug_uhf_card_choice'] = (string) $card_id;

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        // No real network reachable in this test environment - createPayment() throws
        // ApiException, caught by the generic failure branch. 'failure' alone doesn't prove
        // *which* branch produced it (missing-token and API failures both return the same
        // shape) - the notice text does: 'payplug_hosted_fields_missing_token' is only ever
        // added when the alias branch was never entered, while every failure inside it -
        // ownership rejection or API failure alike - uses
        // 'payplug_hosted_fields_tokenization_error'. This proves $use_alias correctly routed
        // to the alias branch rather than falling through to the hf_token-required one.
        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_tokenization_error');

        unset($_POST['payplug_uhf_card_choice']);
        wc_clear_notices();
        wp_delete_post($order->get_id(), true);
        wp_delete_user($user_id);
    }

    public function test_process_payment_fails_when_the_posted_card_choice_does_not_belong_to_the_current_customer(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $settings['payment_methods']['configuration']['payplug']['save_card'] = true;
        update_option('woocommerce_payplug_settings', $settings);

        $owner_id = wp_create_user('uhf_owner_' . wp_rand(), wp_generate_password());
        $attacker_id = wp_create_user('uhf_attacker_' . wp_rand(), wp_generate_password());
        wp_set_current_user($attacker_id);

        // Same valid-total gotcha as above.
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $card_id = UhfCard::insert($owner_id, 'alias_payment_2', 'VISA', '4242', 12, 2099, 'test');

        // Belt and braces: the gateway-level assertions below can't distinguish "the ownership
        // check correctly rejected this" from "the ownership check was removed and the request
        // failed later for an unrelated reason" - both produce an identical notice in this
        // no-network test environment, since AliasPaymentContextBuilder->build() has no
        // observable side effect before createPayment() throws. This asserts the real model
        // method the gateway calls directly; the model layer has its own dedicated coverage in
        // UhfCard_test::testFindForCustomerReturnsNullWhenTheCardBelongsToAnotherCustomer().
        $this->assertNull(UhfCard::find_for_customer((int) $card_id, $attacker_id, 'test'));

        $_POST['payplug_uhf_card_choice'] = (string) $card_id;

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_tokenization_error');

        unset($_POST['payplug_uhf_card_choice']);
        wc_clear_notices();
        wp_delete_post($order->get_id(), true);
        wp_delete_user($owner_id);
        wp_delete_user($attacker_id);
    }

    public function test_process_payment_ignores_the_card_choice_when_the_save_card_bo_flag_is_off(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $settings['payment_methods']['configuration']['payplug']['save_card'] = false;
        update_option('woocommerce_payplug_settings', $settings);

        $user_id = wp_create_user('uhf_owner_' . wp_rand(), wp_generate_password());
        wp_set_current_user($user_id);

        // Same valid-total gotcha as above.
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $card_id = UhfCard::insert($user_id, 'alias_payment_3', 'VISA', '4242', 12, 2099, 'test');

        $_POST['payplug_uhf_card_choice'] = (string) $card_id;

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        // save_card is off, so the alias branch never activates - falls through to the
        // hf_token-required branch, which fails on the missing token exactly like
        // test_process_payment_returns_failure_result_when_hf_token_is_missing. Asserting the
        // notice text (not just 'failure') is what actually proves this: the alias branch's
        // own failures all use 'payplug_hosted_fields_tokenization_error' instead.
        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_missing_token');

        unset($_POST['payplug_uhf_card_choice']);
        wc_clear_notices();
        wp_delete_post($order->get_id(), true);
        wp_delete_user($user_id);
    }

    public function test_process_refund_falls_back_to_retail_api_when_order_has_no_upc_operation_id(): void
    {
        update_option('woocommerce_currency', 'USD');
        update_option('woocommerce_payplug_settings', $this->base_settings);

        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_refund($order->get_id(), 50.00);

        // No _payplug_upc_operation_id meta -> falls back to the inherited Retail API
        // process_refund(), which fails with this exact message because no api_key/jwt is
        // configured in this test environment (PayplugGateway::user_logged_in() returns false).
        // Proves the fallback ran, as distinguished from the UPC path's own generic error below.
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('You must be logged in with your PayPlug account.', $result->get_error_message());

        wp_delete_post($order->get_id(), true);
    }

    public function test_process_refund_returns_error_without_calling_the_api_for_a_non_positive_amount(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acct_test';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        $order->set_total(50.00);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_123');
        $order->save();

        // 0.00 converts to 0 cents, rejected by UnifiedApiPaymentService::createRefund()'s own
        // Assert::positive() before any network call is attempted - deterministic without
        // stubbing pre_http_request, unlike the other UPC-path tests below.
        $result = (new PayplugCreditCard())->process_refund($order->get_id(), 0.00);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('process_refund_error', $result->get_error_code());
        // Distinguishes a genuine UPC-path rejection from the inherited Retail fallback's own
        // (differently-worded, but same error code) "must be logged in" message.
        $this->assertNotSame('You must be logged in with your PayPlug account.', $result->get_error_message());

        wp_delete_post($order->get_id(), true);
    }

    public function test_process_refund_returns_error_without_calling_the_api_when_account_id_is_not_configured(): void
    {
        update_option('woocommerce_currency', 'USD');
        // base_settings never sets hosted_fields.identifier - getPublicKeyId() returns ''.
        update_option('woocommerce_payplug_settings', $this->base_settings);

        $order = wc_create_order();
        $order->set_total(50.00);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_123');
        $order->save();

        $captured = null;
        $this->stub_upc_http_requests(['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"id":"refund_789"}', 'headers' => [], 'cookies' => []], $captured);

        $result = (new PayplugCreditCard())->process_refund($order->get_id(), 50.00);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('process_refund_error', $result->get_error_code());
        $this->assertNull($captured, 'No HTTP call should have been made with an unconfigured account id.');

        wp_delete_post($order->get_id(), true);
    }

    public function test_process_refund_calls_the_upc_service_when_order_has_an_upc_operation_id(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acct_test';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        $order->set_total(50.00);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_123');
        $order->save();

        // Deterministic failure via a stubbed 400 from the refund endpoint itself, rather than
        // relying on the OAuth host being unreachable: an unstubbed real network call is both an
        // unwanted side effect from the test suite and indistinguishable, on a host with egress,
        // from the success path also being reachable - the assertions below would then be
        // failing for the wrong reason (or not failing, for the wrong reason) instead of
        // testing that a genuine API rejection surfaces as this specific WP_Error.
        $this->stub_upc_http_requests(['response' => ['code' => 400, 'message' => 'Bad Request'], 'body' => '{"error":"boom"}', 'headers' => [], 'cookies' => []]);

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_refund($order->get_id(), 50.00);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('process_refund_error', $result->get_error_code());
        $this->assertNotSame('You must be logged in with your PayPlug account.', $result->get_error_message());

        wp_delete_post($order->get_id(), true);
    }

    public function test_process_refund_fails_without_calling_the_api_when_another_refund_request_for_the_same_order_is_in_flight(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acct_test';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        $order->set_total(50.00);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_123');
        $order->save();

        $lock_key = 'payplug_upc_process_refund_' . $order->get_id();
        $lock = new WooCommerceLock();
        $lock->acquire($lock_key, 30);

        $captured = null;
        $this->stub_upc_http_requests(['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"id":"refund_789"}', 'headers' => [], 'cookies' => []], $captured);

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_refund($order->get_id(), 50.00);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('process_refund_error', $result->get_error_code());
        // Distinguishes genuine lock contention from the inherited Retail fallback's own
        // (differently-worded, but same error code) "must be logged in" message.
        $this->assertNotSame('You must be logged in with your PayPlug account.', $result->get_error_message());
        $this->assertNull($captured, 'No HTTP call should have been made while the lock is held.');

        $lock->release($lock_key);
        wp_delete_post($order->get_id(), true);
    }

    public function test_process_refund_sends_the_correct_request_and_records_a_note_on_success(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acct_test';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        $order->set_total(50.00);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_123');
        $order->save();

        $captured = null;
        $this->stub_upc_http_requests([
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode(['id' => 'refund_789']),
            'headers' => [],
            'cookies' => [],
        ], $captured);

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_refund($order->get_id(), 50.00, 'customer request');

        $this->assertTrue($result);
        $this->assertNotNull($captured, 'Expected the refund endpoint to actually be called.');
        $this->assertStringContainsString('/api/payment-gateway/payments/op_123/refund', $captured['url']);
        $this->assertSame('acct_test', $captured['body']['account']['id']);
        $this->assertSame((string) $order->get_id(), $captured['body']['orderId']);
        // The gap this closes: an implementation that sent major units (50) instead of cents
        // (5000) would have passed every pre-existing test in this file.
        $this->assertSame(5000, $captured['body']['amount']);
        $this->assertSame('USD', $captured['body']['currency']);
        $this->assertArrayNotHasKey('submerchantExternalId', $captured['body']);

        $order = wc_get_order($order->get_id());
        $note_contents = array_map(static fn ($note) => $note->content, wc_get_order_notes(['order_id' => $order->get_id()]));
        $matching_notes = array_values(array_filter($note_contents, static fn ($content) => false !== strpos($content, 'refund_789')));
        $this->assertNotEmpty($matching_notes, 'Expected an order note referencing the refund id.');
        $this->assertStringContainsString('(customer request)', $matching_notes[0]);
        $this->assertSame('refund_789', $order->get_meta('_payplug_upc_refund_refund_789'));

        wp_delete_post($order->get_id(), true);
    }

    /**
     * PRE-3727: a partial refund is sent in the minor units of the ORDER's currency - 300 JPY is
     * sent as 300, not 30000.
     */
    public function test_process_refund_sends_a_zero_decimal_currency_amount_as_is(): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acct_test';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        $order->set_currency('JPY');
        $order->set_total(1000);
        $order->update_meta_data('_payplug_upc_operation_id', 'op_jpy');
        $order->save();

        $captured = null;
        $this->stub_upc_http_requests([
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode(['id' => 'op_jpy', 'operationIds' => ['refund_jpy']]),
            'headers' => [],
            'cookies' => [],
        ], $captured);

        $result = (new PayplugCreditCard())->process_refund($order->get_id(), 300, '');

        $this->assertTrue($result);
        $this->assertSame(300, $captured['body']['amount']);
        $this->assertSame('JPY', $captured['body']['currency']);

        wp_delete_post($order->get_id(), true);
    }

    public function test_extract_refund_id_returns_the_id_field_when_present(): void
    {
        $id = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->extract_refund_id(json_encode(['id' => 'refund_789']));

        $this->assertSame('refund_789', $id);
    }

    public function test_extract_refund_id_prefers_the_refund_operation_id_over_the_payment_id(): void
    {
        // Shape observed on staging: "id" is the refunded payment's, operationIds[0] the refund's.
        $id = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->extract_refund_id(json_encode(['id' => 'payment_123', 'operationIds' => ['refund_op_456']]));

        $this->assertSame('refund_op_456', $id);
    }

    public function test_extract_refund_id_takes_the_first_operation_id_when_several_are_returned(): void
    {
        // Never observed (staging always returns exactly one): pins the documented assumption
        // that operationIds[0] is the refund's own operation.
        $id = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->extract_refund_id(json_encode(['id' => 'payment_123', 'operationIds' => ['refund_op_456', 'other_op_789']]));

        $this->assertSame('refund_op_456', $id);
    }

    public function test_extract_refund_id_returns_null_for_malformed_or_missing_id(): void
    {
        $processor = new HostedFieldsPaymentProcessor(new PayplugCreditCard());

        $this->assertNull($processor->extract_refund_id('not json'));
        $this->assertNull($processor->extract_refund_id(json_encode(['status' => 'ok'])));
        $this->assertNull($processor->extract_refund_id(json_encode(['id' => ''])));
    }

    public function test_build_refund_note_for_a_full_refund(): void
    {
        $note = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->build_refund_note(null, 'USD', '');

        $this->assertSame(__('Refund: fully refunded', 'payplug'), $note);
    }

    public function test_build_refund_note_for_a_partial_refund_includes_the_formatted_amount(): void
    {
        $note = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->build_refund_note(500, 'USD', '');

        $this->assertStringContainsString(wc_price(5.00, ['currency' => 'USD']), $note);
        $this->assertStringStartsWith('Refund: refunded', $note);
    }

    public function test_build_refund_note_uses_the_order_currency_not_the_shop_currency(): void
    {
        update_option('woocommerce_currency', 'EUR');

        $note = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->build_refund_note(500, 'USD', '');

        $this->assertStringContainsString(wc_price(5.00, ['currency' => 'USD']), $note);
        $this->assertStringNotContainsString(wc_price(5.00, ['currency' => 'EUR']), $note);
    }

    public function test_build_refund_note_includes_the_refund_id_when_present(): void
    {
        $note = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->build_refund_note(null, 'USD', '', 'refund_789');

        $this->assertStringStartsWith('Refund refund_789:', $note);
    }

    public function test_build_refund_note_appends_an_escaped_reason(): void
    {
        $note = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->build_refund_note(null, 'USD', '<script>alert(1)</script>');

        $this->assertStringContainsString('(&lt;script&gt;alert(1)&lt;/script&gt;)', $note);
        $this->assertStringNotContainsString('<script>', $note);
    }

    public function test_build_refund_note_omits_the_parenthesized_reason_when_empty(): void
    {
        $note = (new HostedFieldsPaymentProcessor(new PayplugCreditCard()))->build_refund_note(null, 'USD', '');

        $this->assertStringNotContainsString('(', $note);
    }

    /**
     * @return string[][]
     */
    public function provide_brands_refused_by_uhf(): array
    {
        return [
            'CB, only accepted in EUR' => ['CB'],
            'lowercase cb' => ['cb'],
            'unknown brand' => ['AMEX'],
            'no brand posted' => [''],
        ];
    }

    /**
     * PRE-3746: UHF only handles non-EUR carts, where CB isn't accepted - a forged or stale POST
     * carrying a disallowed brand must be refused with the same message as the client-side
     * check, without ever calling the API, and without keeping the order locked.
     *
     * @dataProvider provide_brands_refused_by_uhf
     */
    public function test_process_payment_refuses_a_disallowed_brand_without_calling_the_api(string $brand): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $settings['debug'] = true;
        update_option('woocommerce_payplug_settings', $settings);

        // Same valid-total gotcha as above.
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        $http_calls = [];
        add_filter('pre_http_request', function ($preempt, $args, $url) use (&$http_calls) {
            $http_calls[] = $url;

            return new \WP_Error('http_request_blocked', 'No HTTP call expected in this test.');
        }, 10, 3);

        // Support only sees an order stuck in pending otherwise: the refusal must leave a trace.
        $logged = [];
        $capture_log = function ($message, $level, $context) use (&$logged) {
            if ('payplug_gateway' === ($context['source'] ?? '')) {
                $logged[] = [$level, $message];
            }

            return $message;
        };
        add_filter('woocommerce_logger_log_message', $capture_log, 10, 3);

        $_POST['hf_token'] = 'hf_token_123';
        $_POST['hf_selected_brand'] = $brand;

        $result = (new PayplugCreditCard())->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_unsupported_brand_error');
        $this->assertSame([], $http_calls, 'No payment must be created.');
        $this->assertSame('', wc_get_order($order->get_id())->get_meta('_payplug_upc_operation_id'));
        $this->assertContains(
            ['error', sprintf('UHF payment refused for order #%s: brand "%s" not allowed.', $order->get_id(), $brand)],
            $logged
        );

        $lock_key = 'payplug_upc_process_payment_' . $order->get_id();
        $lock = new WooCommerceLock();
        $this->assertTrue($lock->acquire($lock_key, 30), 'The refusal must release the order lock.');
        $lock->release($lock_key);

        remove_filter('woocommerce_logger_log_message', $capture_log, 10);
        wc_clear_notices();
        unset($_POST['hf_token'], $_POST['hf_selected_brand']);
        wp_delete_post($order->get_id(), true);
    }

    /**
     * @return string[][]
     */
    public function provide_brands_accepted_by_uhf(): array
    {
        return [
            'VISA' => ['VISA'],
            'MASTERCARD' => ['MASTERCARD'],
        ];
    }

    /**
     * PRE-3746: the nominal path of the brand check - an allowed brand must get past it and reach
     * the payment creation call, with the brand relayed as-is. The payment endpoint answers a 500
     * here, so the flow then ends in the generic API-failure branch.
     *
     * @dataProvider provide_brands_accepted_by_uhf
     */
    public function test_process_payment_sends_an_allowed_brand_to_the_payment_creation_call(string $brand): void
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['embedded_mode'] = 'hosted_fields';
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acc_test_123';
        update_option('woocommerce_payplug_settings', $settings);

        // Same valid-total gotcha as above.
        $order = wc_create_order();
        $order->set_currency('USD');
        $order->set_total(50.00);
        $order->save();

        $payment_requests = [];
        add_filter('pre_http_request', function ($preempt, $args, $url) use (&$payment_requests) {
            if (false !== strpos($url, '/oauth2/token')) {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
                    'headers' => [],
                    'cookies' => [],
                ];
            }

            $payment_requests[] = json_decode((string) ($args['body'] ?? ''), true);

            return ['response' => ['code' => 500, 'message' => 'Error'], 'body' => '', 'headers' => [], 'cookies' => []];
        }, 10, 3);

        $_POST['hf_token'] = 'hf_token_123';
        $_POST['hf_selected_brand'] = $brand;

        $result = (new PayplugCreditCard())->process_payment($order->get_id());

        $this->assertCount(1, $payment_requests, 'The payment creation call must be reached.');
        $this->assertSame($brand, $payment_requests[0]['paymentMethod']['details']['selectedBrand'] ?? null);
        $this->assertSame('failure', $result['result']);
        $this->assertLastErrorNotice('payplug_hosted_fields_tokenization_error');

        wc_clear_notices();
        unset($_POST['hf_token'], $_POST['hf_selected_brand']);
        wp_delete_post($order->get_id(), true);
    }

    private function assertLastErrorNotice(string $msgid): void
    {
        $notices = wc_get_notices('error');

        $this->assertNotEmpty($notices, 'Expected an error notice to have been added.');
        $this->assertSame(__($msgid, 'payplug'), end($notices)['notice']);
    }

    private function create_paid_uhf_order(string $operation_id, ?string $currency = null, float $total = 50.00): \WC_Order
    {
        update_option('woocommerce_currency', 'USD');
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['hosted_fields']['identifier'] = 'acct_test';
        update_option('woocommerce_payplug_settings', $settings);

        $order = wc_create_order();
        if (null !== $currency) {
            $order->set_currency($currency);
        }
        $order->set_total($total);
        $order->update_meta_data('_payplug_upc_operation_id', $operation_id);
        $order->save();

        $recorder = new UpcTransactionMetadataRecorder();
        $recorder->record_created($order, $operation_id, false);
        $recorder->record_paid($order);

        return $order;
    }

    private function stub_successful_refund(): void
    {
        $this->stub_upc_http_requests([
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode(['id' => 'op_refund_snapshot', 'operationIds' => ['refund_snapshot_1']]),
            'headers' => [],
            'cookies' => [],
        ]);
    }

    private function upc_snapshot(\WC_Order $order): array
    {
        $meta = wc_get_order($order->get_id())->get_meta(UpcTransactionMetadataRecorder::META_KEY);

        return is_array($meta) ? $meta : [];
    }

    public function test_a_partial_uhf_refund_marks_the_snapshot_partially_refunded(): void
    {
        $order = $this->create_paid_uhf_order('op_refund_snapshot');
        $this->stub_successful_refund();

        $result = (new PayplugCreditCard())->process_refund($order->get_id(), 10.00);

        $meta = $this->upc_snapshot($order);
        $this->assertTrue($result);
        $this->assertFalse($meta['refunded']);
        $this->assertSame(1000, $meta['amount_refunded']);
        $this->assertTrue($meta['paid']);

        $order->delete(true);
    }

    public function test_a_second_refund_completing_the_amount_marks_it_refunded(): void
    {
        $order = $this->create_paid_uhf_order('op_refund_snapshot');
        $this->stub_successful_refund();

        (new PayplugCreditCard())->process_refund($order->get_id(), 10.00);
        $result = (new PayplugCreditCard())->process_refund($order->get_id(), 40.00);

        $meta = $this->upc_snapshot($order);
        $this->assertTrue($result);
        $this->assertTrue($meta['refunded']);
        $this->assertSame(5000, $meta['amount_refunded']);

        $order->delete(true);
    }

    public function test_a_refund_without_amount_marks_it_refunded(): void
    {
        $order = $this->create_paid_uhf_order('op_refund_snapshot');
        $this->stub_successful_refund();

        $result = (new PayplugCreditCard())->process_refund($order->get_id(), null);

        $meta = $this->upc_snapshot($order);
        $this->assertTrue($result);
        $this->assertTrue($meta['refunded']);
        $this->assertSame(5000, $meta['amount_refunded']);

        $order->delete(true);
    }

    public function test_a_partial_refund_in_a_zero_decimal_currency_is_recorded_in_its_minor_units(): void
    {
        $order = $this->create_paid_uhf_order('op_refund_snapshot', 'JPY', 1000);
        $this->stub_successful_refund();

        (new PayplugCreditCard())->process_refund($order->get_id(), 300);

        $meta = $this->upc_snapshot($order);
        $this->assertSame(1000, $meta['amount']);
        $this->assertSame(300, $meta['amount_refunded']);
        $this->assertFalse($meta['refunded']);

        $order->delete(true);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: float}>
     */
    public function rejected_refund_provider(): array
    {
        return [
            'api error' => [['response' => ['code' => 400, 'message' => 'Bad Request'], 'body' => '{"error":"boom"}', 'headers' => [], 'cookies' => []], 10.00],
            'invalid amount' => [['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"id":"refund_789"}', 'headers' => [], 'cookies' => []], 0.00],
        ];
    }

    /**
     * @dataProvider rejected_refund_provider
     */
    public function test_a_rejected_refund_leaves_the_snapshot_unchanged(array $refund_response, float $amount): void
    {
        $order = $this->create_paid_uhf_order('op_refund_snapshot');
        $before = $this->upc_snapshot($order);
        $this->stub_upc_http_requests($refund_response);

        $result = (new PayplugCreditCard())->process_refund($order->get_id(), $amount);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame($before, $this->upc_snapshot($order));

        $order->delete(true);
    }

    public function test_a_retail_order_refund_never_writes_the_upc_snapshot(): void
    {
        update_option('woocommerce_currency', 'USD');
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $order = wc_create_order();
        $order->set_total(50.00);
        $order->save();

        (new PayplugCreditCard())->process_refund($order->get_id(), 50.00);

        $this->assertSame('', wc_get_order($order->get_id())->get_meta(UpcTransactionMetadataRecorder::META_KEY));

        $order->delete(true);
    }
}
