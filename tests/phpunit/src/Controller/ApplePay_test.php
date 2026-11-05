<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Controller;

use Payplug\PayplugWoocommerce\Controller\ApplePay;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use PHPUnit\Framework\TestCase;

class ApplePay_test extends TestCase
{
    private array $settings = [
        'enabled' => true,
        'mode' => false,
        'payment_methods' => [
            'configuration' => [
                'apple_pay' => [
                    'active' => true,
                    'display' => '{"checkout":true,"cart":true,"product":true}',
                    'carriers' => '[]',
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_payplug_settings', $this->settings);
        // Account permissions that grant Apple Pay on this site's domain, so checkApplePay()'s
        // outcome only depends on the shop currency.
        set_transient(PayplugWoocommerceHelper::get_transient_key($this->settings), [
            'payment_methods' => [
                'apple_pay' => [
                    'enabled' => true,
                    'allowed_domain_names' => [wp_parse_url(get_site_url(), PHP_URL_HOST)],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        delete_transient(PayplugWoocommerceHelper::get_transient_key($this->settings));
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_currency');
        parent::tearDown();
    }

    public function test_apple_pay_is_available_on_an_eur_shop(): void
    {
        update_option('woocommerce_currency', 'EUR');

        $this->assertTrue((new ApplePay())->checkApplePay());
    }

    public function test_apple_pay_is_unavailable_on_a_non_eur_shop_even_when_still_active(): void
    {
        update_option('woocommerce_currency', 'USD');

        $this->assertFalse((new ApplePay())->checkApplePay());
    }
}
