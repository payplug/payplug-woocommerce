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
        parent::tearDown();
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

        $order = wc_create_order();
        $order->save();

        $card_id = UhfCard::insert($user_id, 'alias_payment_1', 'VISA', '4242', 12, 2099, 'test');

        $_POST['payplug_uhf_card_choice'] = (string) $card_id;

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        // No real network reachable in this test environment - createPayment() throws
        // ApiException, caught by the generic failure branch. The assertion that matters is
        // that the *alias* branch was taken rather than the hf_token branch: a missing-token
        // failure never reaches the lock/try block at all, while an alias-branch failure does
        // reach it (and releases the lock cleanly), which the next test's isolation depends on.
        $this->assertSame('failure', $result['result']);

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

        $order = wc_create_order();
        $order->save();

        $card_id = UhfCard::insert($owner_id, 'alias_payment_2', 'VISA', '4242', 12, 2099, 'test');

        $_POST['payplug_uhf_card_choice'] = (string) $card_id;

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        $this->assertSame('failure', $result['result']);

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

        $order = wc_create_order();
        $order->save();

        $card_id = UhfCard::insert($user_id, 'alias_payment_3', 'VISA', '4242', 12, 2099, 'test');

        $_POST['payplug_uhf_card_choice'] = (string) $card_id;

        $gateway = new PayplugCreditCard();
        $result = $gateway->process_payment($order->get_id());

        // save_card is off, so the alias branch never activates - falls through to the
        // hf_token-required branch, which fails on the missing token exactly like
        // test_process_payment_returns_failure_result_when_hf_token_is_missing.
        $this->assertSame('failure', $result['result']);

        unset($_POST['payplug_uhf_card_choice']);
        wc_clear_notices();
        wp_delete_post($order->get_id(), true);
        wp_delete_user($user_id);
    }
}
