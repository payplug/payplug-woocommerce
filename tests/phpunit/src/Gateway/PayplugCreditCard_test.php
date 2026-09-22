<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
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

    public function test_extract_refund_id_returns_the_id_field_when_present(): void
    {
        $id = (new PayplugCreditCard())->extract_refund_id(json_encode(['id' => 'refund_789']));

        $this->assertSame('refund_789', $id);
    }

    public function test_extract_refund_id_returns_null_for_malformed_or_missing_id(): void
    {
        $gateway = new PayplugCreditCard();

        $this->assertNull($gateway->extract_refund_id('not json'));
        $this->assertNull($gateway->extract_refund_id(json_encode(['status' => 'ok'])));
        $this->assertNull($gateway->extract_refund_id(json_encode(['id' => ''])));
    }

    public function test_build_refund_note_for_a_full_refund(): void
    {
        $note = (new PayplugCreditCard())->build_refund_note(null, 'USD', '');

        $this->assertSame(__('Refund: fully refunded', 'payplug'), $note);
    }

    public function test_build_refund_note_for_a_partial_refund_includes_the_formatted_amount(): void
    {
        $note = (new PayplugCreditCard())->build_refund_note(500, 'USD', '');

        $this->assertStringContainsString(wc_price(5.00, ['currency' => 'USD']), $note);
        $this->assertStringStartsWith('Refund: refunded', $note);
    }

    public function test_build_refund_note_uses_the_order_currency_not_the_shop_currency(): void
    {
        update_option('woocommerce_currency', 'EUR');

        $note = (new PayplugCreditCard())->build_refund_note(500, 'USD', '');

        $this->assertStringContainsString(wc_price(5.00, ['currency' => 'USD']), $note);
        $this->assertStringNotContainsString(wc_price(5.00, ['currency' => 'EUR']), $note);
    }

    public function test_build_refund_note_includes_the_refund_id_when_present(): void
    {
        $note = (new PayplugCreditCard())->build_refund_note(null, 'USD', '', 'refund_789');

        $this->assertStringStartsWith('Refund refund_789:', $note);
    }

    public function test_build_refund_note_appends_an_escaped_reason(): void
    {
        $note = (new PayplugCreditCard())->build_refund_note(null, 'USD', '<script>alert(1)</script>');

        $this->assertStringContainsString('(&lt;script&gt;alert(1)&lt;/script&gt;)', $note);
        $this->assertStringNotContainsString('<script>', $note);
    }

    public function test_build_refund_note_omits_the_parenthesized_reason_when_empty(): void
    {
        $note = (new PayplugCreditCard())->build_refund_note(null, 'USD', '');

        $this->assertStringNotContainsString('(', $note);
    }

    private function assertLastErrorNotice(string $msgid): void
    {
        $notices = wc_get_notices('error');

        $this->assertNotEmpty($notices, 'Expected an error notice to have been added.');
        $this->assertSame(__($msgid, 'payplug'), end($notices)['notice']);
    }
}
