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
        parent::tearDown();
    }

    public function test_gateway_enabled_when_module_enabled_as_boolean(): void
    {
        // AccountGateway::register() persists 'enabled' => true (bool), not 'yes'
        update_option('woocommerce_payplug_settings', $this->base_settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('yes', $gateway->enabled);
    }

    public function test_gateway_enabled_when_module_enabled_as_string_yes(): void
    {
        $settings = $this->base_settings;
        $settings['enabled'] = 'yes';
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('yes', $gateway->enabled);
    }

    public function test_gateway_disabled_when_cb_method_inactive(): void
    {
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['payplug']['active'] = false;
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_disabled_when_module_disabled(): void
    {
        $settings = $this->base_settings;
        $settings['enabled'] = false;
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_enabled_on_legacy_config_without_active_key(): void
    {
        // Legacy config pre-migration: 'payment_methods' key absent → null fallback → enabled
        $settings = $this->base_settings;
        unset($settings['payment_methods']);
        update_option('woocommerce_payplug_settings', $settings);
        $gateway = new PayplugCreditCard();
        self::assertSame('yes', $gateway->enabled);
    }
}
