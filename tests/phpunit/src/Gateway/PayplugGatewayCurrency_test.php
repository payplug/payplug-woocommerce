<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Payplug\PayplugWoocommerce\Gateway\Bancontact;
use Payplug\PayplugWoocommerce\Gateway\PayplugCreditCard;
use PHPUnit\Framework\TestCase;

/**
 * The Retail API path (PayplugGateway::process_payment()) is EUR-only: gateway availability
 * only checks the shop currency, so an order in another currency must be rejected before any
 * API call.
 */
class PayplugGatewayCurrency_test extends TestCase
{
    /** @var string[] */
    private array $http_calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_payplug_settings', [
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
        ]);

        $this->http_calls = [];
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            $this->http_calls[] = $url;

            return new \WP_Error('http_request_blocked', 'No HTTP call expected in this test.');
        }, 10, 3);
    }

    protected function tearDown(): void
    {
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_currency');
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function test_a_non_card_gateway_rejects_a_non_eur_order_on_an_eur_shop(): void
    {
        $order = $this->create_order('USD');

        try {
            (new Bancontact())->process_payment($order->get_id());
            $this->fail('A non-EUR order must be rejected by the Retail API path.');
        } catch (\Exception $e) {
            $this->assertSame(__('payplug_payment_currency_not_supported', 'payplug'), $e->getMessage());
        }

        $this->assertSame([], $this->http_calls);
        wp_delete_post($order->get_id(), true);
    }

    public function test_the_retail_card_rejects_a_non_eur_order_on_an_eur_shop(): void
    {
        $order = $this->create_order('JPY');

        try {
            (new PayplugCreditCard())->process_payment($order->get_id());
            $this->fail('A non-EUR order must be rejected by the Retail API path.');
        } catch (\Exception $e) {
            $this->assertSame(__('payplug_payment_currency_not_supported', 'payplug'), $e->getMessage());
        }

        $this->assertSame([], $this->http_calls);
        wp_delete_post($order->get_id(), true);
    }

    private function create_order(string $currency): \WC_Order
    {
        $order = wc_create_order();
        $order->set_currency($currency);
        $order->set_total(1000);
        $order->save();

        return $order;
    }
}
