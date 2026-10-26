<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Gateway\AmericanExpress;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Satispay;
use Payplug\PayplugWoocommerce\Gateway\PPRO\Scalapay;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class PproPaymentGuard_test extends TestCase
{
    use MockeryPHPUnitIntegration;
    use WooCommerceAjaxFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        // A logged-in gateway otherwise asks the PayPlug API for the account permissions.
        set_transient('payplug_permission_live', ['use_live_mode' => true, 'can_save_cards' => false], DAY_IN_SECONDS);
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_payplug_settings', $this->settings());
    }

    protected function tearDown(): void
    {
        delete_transient('payplug_permission_live');
        delete_option('woocommerce_currency');
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_order_below_the_min_is_refused_before_any_payment_is_created(): void
    {
        $order = $this->make_order(['payment_method' => 'satispay', 'price' => '0.29']);
        $order->set_billing_country('IT');
        $order->save();
        $gateway = $this->gateway(new Satispay(), $api);
        $log = GatewayLogSpy::start();

        try {
            $gateway->process_standard_payment($order, 29, 0);
            self::fail('A non-eligible order must be refused.');
        } catch (\Exception $e) {
            self::assertSame(__('payplug_apm_not_eligible_error', 'payplug'), $e->getMessage());
        } finally {
            $log->stop();
        }

        $api->shouldNotHaveReceived('payment_create');
        $warning = $log->entries[0];
        self::assertSame('warning', $warning['level']);
        self::assertStringContainsString('#' . $order->get_id(), $warning['message']);
        self::assertStringContainsString('satispay', $warning['message']);
        self::assertStringContainsString('below_min', $warning['message']);
        self::assertStringNotContainsString('Jane', $warning['message']);
        self::assertStringNotContainsString('Paris', $warning['message']);
        self::assertStringNotContainsString('IT', $warning['message']);
    }

    public function test_billing_country_outside_the_api_list_is_refused(): void
    {
        $order = $this->make_order(['payment_method' => 'satispay', 'price' => '10']);
        $gateway = $this->gateway(new Satispay(), $api);

        $this->expectExceptionMessage(__('payplug_apm_not_eligible_error', 'payplug'));
        $gateway->process_standard_payment($order, 1000, 0);
    }

    public function test_eligible_order_reaches_payment_creation(): void
    {
        $order = $this->make_order(['payment_method' => 'satispay', 'price' => '10']);
        $order->set_billing_country('IT');
        $order->save();
        $gateway = $this->gateway(new Satispay(), $api);
        $api->shouldReceive('payment_create')->once()->andThrow(new \Exception('stop after the guard'));

        try {
            $gateway->process_standard_payment($order, 1000, 0);
        } catch (\Exception $e) {
            self::assertNotSame(__('payplug_apm_not_eligible_error', 'payplug'), $e->getMessage());
        }
    }

    public function test_scalapay_guard_runs_before_its_own_checks(): void
    {
        $order = $this->make_order(['payment_method' => 'scalapay', 'price' => '4.99']);
        $order->set_billing_country('IT');
        $order->save();
        $gateway = $this->gateway(new Scalapay(), $api);

        $this->expectExceptionMessage(__('payplug_apm_not_eligible_error', 'payplug'));
        $gateway->process_standard_payment($order, 499, 0);
    }

    public function test_classic_checkout_validation_rejects_a_posted_billing_country_that_is_not_eligible(): void
    {
        WC()->cart->add_to_cart($this->make_product('10')->get_id());
        WC()->cart->calculate_totals();
        $gateway = new Satispay();
        $errors = new \WP_Error();

        $gateway->validate_apm_checkout(['payment_method' => 'satispay', 'billing_country' => 'FR'], $errors);

        self::assertSame([__('payplug_apm_not_eligible_error', 'payplug')], $errors->get_error_messages());
    }

    public function test_classic_checkout_validation_ignores_other_payment_methods(): void
    {
        WC()->cart->add_to_cart($this->make_product('10')->get_id());
        WC()->cart->calculate_totals();
        $gateway = new Satispay();
        $errors = new \WP_Error();

        $gateway->validate_apm_checkout(['payment_method' => 'payplug', 'billing_country' => 'FR'], $errors);
        $gateway->validate_apm_checkout(['payment_method' => 'satispay', 'billing_country' => 'IT'], $errors);

        self::assertFalse($errors->has_errors());
    }

    public function test_non_restricted_gateway_is_not_guarded(): void
    {
        // Account data the American Express gateway otherwise fetches from the PayPlug API when built.
        set_transient('payplug_config_live', ['payment_methods' => ['american_express' => ['enabled' => true, 'allowed_countries' => ['ALL']]]]);
        $order = $this->make_order(['payment_method' => 'american_express', 'price' => '0.10']);
        $gateway = new AmericanExpress();
        delete_transient('payplug_config_live');

        \Closure::bind(static function () use ($gateway, $order): void {
            $gateway->assert_apm_order_eligible($order);
        }, null, AmericanExpress::class)();

        $this->addToAssertionCount(1);
    }

    /**
     * @param \Mockery\MockInterface|null $api set to the payplug_api mock
     */
    private function gateway($gateway, &$api)
    {
        $api = \Mockery::spy();
        $gateway->payplug_api = $api;

        return $gateway;
    }

    private function settings(): array
    {
        $settings = [
            'enabled' => true,
            'mode' => true,
            'debug' => true,
            'email' => 'merchant@example.test',
            'apm_cache_mode' => 'live',
            'api_key' => '{"live":"sk_live_x","test":"sk_test_x"}',
            'jwt' => '{}',
            'payment_methods' => ['permissions' => [], 'configuration' => []],
        ];
        foreach (['satispay' => '{"min":{"EUR":30},"max":{"EUR":2000000}}', 'scalapay' => '{"min":{"EUR":500},"max":{"EUR":200000}}'] as $apm => $amounts) {
            $settings['payment_methods']['permissions'][$apm] = ['enabled' => true, 'countries' => '["IT"]', 'amounts' => $amounts];
            $settings['payment_methods']['configuration'][$apm] = ['active' => true, 'custom_amounts' => '{}', 'custom_countries' => ''];
        }
        $settings['payment_methods']['configuration']['american_express'] = ['active' => true];
        $settings['payment_methods']['configuration']['payplug'] = ['embedded_mode' => 'redirect'];
        $settings['payment_methods']['configuration']['oney'] = ['with_fees' => true, 'custom_amounts' => '{"min":10000, "max":300000}'];

        return $settings;
    }
}
