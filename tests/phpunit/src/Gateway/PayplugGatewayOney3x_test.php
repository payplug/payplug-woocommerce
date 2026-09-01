<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use PHPUnit\Framework\TestCase;

class PayplugGatewayOney3x_test extends TestCase
{
    private array $base_settings = [
        'enabled' => true,
        'mode' => false,
        'payment_methods' => [
            'configuration' => [
                'oney' => [
                    'active' => true,
                    'with_fees' => true,
                    'min_amounts' => ['EUR' => 100],
                    'max_amounts' => ['EUR' => 300000],
                    'allowed_countries' => ['FR'],
                    'custom_amounts' => '{"min":100,"max":300000}',
                ],
            ],
        ],
    ];

    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_oney_x3_with_fees_settings');
        parent::tearDown();
    }

    public function test_gateway_disabled_on_wc_payments_page_when_oney_inactive(): void
    {
        // Merchant previously toggled Oney on natively; the raw WC gateway option stays 'yes'
        // until something re-checks it against the Payplug config.
        update_option('woocommerce_oney_x3_with_fees_settings', ['enabled' => 'yes']);

        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['oney']['active'] = false;
        update_option('woocommerce_payplug_settings', $settings);

        $gateway = new PayplugGatewayOney3x();

        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_enabled_when_oney_active(): void
    {
        update_option('woocommerce_oney_x3_with_fees_settings', ['enabled' => 'yes']);
        update_option('woocommerce_payplug_settings', $this->base_settings);

        $gateway = new PayplugGatewayOney3x();

        self::assertSame('yes', $gateway->enabled);
    }
}
