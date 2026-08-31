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
        parent::tearDown();
    }

    public function test_gateway_disabled_on_wc_payments_page_when_oney_inactive(): void
    {
        // PayplugGateway::__construct() (the top of this class's parent chain) sets
        // $this->id = 'payplug' and calls init_settings() - which reads
        // woocommerce_payplug_settings - before PayplugGatewayOney3x::__construct() reassigns
        // $this->id to 'oney_x3_with_fees'. So $this->enabled is never sourced from a
        // per-gateway woocommerce_oney_x3_with_fees_settings option; only checkGateway()'s
        // read of payment_methods.configuration.oney.active (from woocommerce_payplug_settings)
        // controls it here - that's what this asserts (PRE-3597 review).
        $settings = $this->base_settings;
        $settings['payment_methods']['configuration']['oney']['active'] = false;
        update_option('woocommerce_payplug_settings', $settings);

        $gateway = new PayplugGatewayOney3x();

        self::assertSame('no', $gateway->enabled);
    }

    public function test_gateway_enabled_when_oney_active(): void
    {
        update_option('woocommerce_payplug_settings', $this->base_settings);

        $gateway = new PayplugGatewayOney3x();

        self::assertSame('yes', $gateway->enabled);
    }
}
