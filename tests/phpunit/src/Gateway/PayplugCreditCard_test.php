<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
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

        $order = wc_create_order();
        $order->save();

        $_POST['hf_token'] = '';

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

        $order = wc_create_order();
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
}
