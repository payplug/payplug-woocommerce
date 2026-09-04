<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
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
}
