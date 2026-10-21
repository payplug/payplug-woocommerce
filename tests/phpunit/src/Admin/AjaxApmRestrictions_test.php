<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Payplug\PayplugWoocommerce\Admin\Ajax;
use Payplug\PayplugWoocommerce\Service\ApmEligibility;
use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

class AjaxApmRestrictions_test extends TestCase
{
    use MockeryPHPUnitIntegration;
    use WooCommerceAjaxFixtures;

    private int $user_id = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        // A logged-in gateway otherwise asks the PayPlug API for the account permissions.
        set_transient('payplug_permission_live', ['use_live_mode' => true, 'can_save_cards' => false], DAY_IN_SECONDS);
        $this->user_id = wp_insert_user([
            'user_login' => uniqid('payplug_admin_'),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
        ]);
        wp_set_current_user($this->user_id);
        update_option('woocommerce_payplug_settings', $this->settings());
    }

    protected function tearDown(): void
    {
        delete_transient('payplug_permission_live');
        wp_set_current_user(0);
        wp_delete_user($this->user_id);
        remove_filter('wp_doing_ajax', '__return_true');
        remove_all_filters('wp_die_ajax_handler');
        delete_option('woocommerce_payplug_settings');
        delete_option('woocommerce_allowed_countries');
        delete_option('woocommerce_specific_allowed_countries');
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_save_does_not_refresh_when_every_apm_is_cached_for_the_saved_mode(): void
    {
        $account = $this->account();
        $account->shouldNotReceive('refresh_payment_permissions');

        $output = $this->save($account, $this->body());

        self::assertTrue($output['success']);
    }

    public function test_save_refreshes_with_the_posted_mode_on_a_mode_change(): void
    {
        $account = $this->account();
        $account->shouldReceive('refresh_payment_permissions')->with('test')->once()->andReturn(true);

        $this->save($account, $this->body(['payplug_sandbox' => 1]));
    }

    public function test_save_refreshes_when_an_apm_has_no_cached_limits(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['permissions']['wero'] = [];
        update_option('woocommerce_payplug_settings', $settings);
        $account = $this->account();
        $account->shouldReceive('refresh_payment_permissions')->with('live')->once()->andReturn(false);

        $this->save($account, $this->body());
    }

    public function test_failed_refresh_on_a_mode_change_still_saves_and_keeps_the_cache(): void
    {
        $account = $this->account();
        $account->shouldReceive('refresh_payment_permissions')->with('test')->once()->andReturn(false);

        $output = $this->save($account, $this->body(['payplug_sandbox' => 1, 'enable_debug' => 1]));

        $options = get_option('woocommerce_payplug_settings');
        self::assertTrue($output['success']);
        self::assertTrue($options['debug']);
        self::assertFalse($options['mode']);
        self::assertSame('live', $options['apm_cache_mode']);
        self::assertSame($this->settings()['payment_methods']['permissions'], $options['payment_methods']['permissions']);
    }

    public function test_refreshed_limits_are_not_overwritten_by_the_save(): void
    {
        $account = $this->account();
        $account->shouldReceive('refresh_payment_permissions')->with('test')->once()->andReturnUsing(function (): bool {
            $options = get_option('woocommerce_payplug_settings');
            $options['payment_methods']['permissions']['satispay']['countries'] = '["IT"]';
            $options['apm_cache_mode'] = 'test';
            update_option('woocommerce_payplug_settings', $options);

            return true;
        });

        $this->save($account, $this->body(['payplug_sandbox' => 1]));

        $options = get_option('woocommerce_payplug_settings');
        self::assertSame('["IT"]', $options['payment_methods']['permissions']['satispay']['countries']);
        self::assertSame('test', $options['apm_cache_mode']);
    }

    public function test_bo_opening_refreshes_the_limits_once(): void
    {
        $account = $this->account();
        $account->shouldReceive('refresh_payment_permissions')->withNoArgs()->once()->andReturn(true);
        $ajax = \Mockery::mock(Ajax::class)->makePartial();
        $ajax->shouldReceive('get_gateway')->with('account')->andReturn($account);

        $output = $this->capture_json_response(function () use ($ajax): void {
            $ajax->payplug_init();
        });

        self::assertTrue($output['success']);
    }

    public function test_bo_opening_logged_out_does_not_refresh(): void
    {
        $account = \Mockery::mock();
        $account->shouldReceive('is_logged')->andReturn(false);
        $account->shouldNotReceive('refresh_payment_permissions');
        $ajax = \Mockery::mock(Ajax::class)->makePartial();
        $ajax->shouldReceive('get_gateway')->with('account')->andReturn($account);

        $this->capture_json_response(function () use ($ajax): void {
            $ajax->payplug_init();
        });
    }

    public function test_amount_below_the_api_bound_is_refused_and_nothing_is_saved(): void
    {
        $before = get_option('woocommerce_payplug_settings');

        $output = $this->save($this->account(), $this->body(['bancontact_min_amounts' => '0.1', 'bancontact_max_amounts' => '100']));

        self::assertFalse($output['success']);
        self::assertSame(
            sprintf(__('payplug_apm_amount_out_of_bounds_error', 'payplug'), 'Bancontact', '0.30', '20000'),
            $output['data']['msg']
        );
        self::assertSame($before, get_option('woocommerce_payplug_settings'));
    }

    public function test_max_not_above_min_is_refused(): void
    {
        $output = $this->save($this->account(), $this->body(['satispay_min_amounts' => '10', 'satispay_max_amounts' => '10']));

        self::assertFalse($output['success']);
        self::assertSame(sprintf(__('payplug_apm_amount_max_gt_min_error', 'payplug'), 'Satispay'), $output['data']['msg']);
    }

    public function test_non_numeric_amount_is_refused(): void
    {
        $output = $this->save($this->account(), $this->body(['wero_min_amounts' => 'abc', 'wero_max_amounts' => '10']));

        self::assertFalse($output['success']);
        self::assertSame(sprintf(__('payplug_apm_amount_invalid_error', 'payplug'), 'Wero'), $output['data']['msg']);
    }

    public function test_amounts_are_stored_in_cents_without_the_values_equal_to_the_api_bounds(): void
    {
        $this->save($this->account(), $this->body([
            'scalapay_min_amounts' => '49.99',
            'scalapay_max_amounts' => '2000',
            'ideal_min_amounts' => '0.3',
            'ideal_max_amounts' => '',
        ]));

        $configuration = get_option('woocommerce_payplug_settings')['payment_methods']['configuration'];
        self::assertSame('{"min":4999}', $configuration['scalapay']['custom_amounts']);
        self::assertSame('{}', $configuration['ideal']['custom_amounts']);
    }

    public function test_emptied_amount_field_restores_the_api_bound(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['configuration']['bancontact']['custom_amounts'] = '{"min":1000,"max":50000}';
        update_option('woocommerce_payplug_settings', $settings);

        $this->save($this->account(), $this->body(['bancontact_min_amounts' => null, 'bancontact_max_amounts' => 500]));

        self::assertSame('{"max":50000}', get_option('woocommerce_payplug_settings')['payment_methods']['configuration']['bancontact']['custom_amounts']);
    }

    public function test_save_without_the_restriction_keys_keeps_the_stored_restrictions(): void
    {
        $settings = $this->settings();
        $settings['payment_methods']['configuration']['mybank']['custom_amounts'] = '{"min":1000}';
        $settings['payment_methods']['configuration']['mybank']['custom_countries'] = '["IT"]';
        update_option('woocommerce_payplug_settings', $settings);

        $output = $this->save($this->account(), $this->body(['enable_debug' => 1]));

        $configuration = get_option('woocommerce_payplug_settings')['payment_methods']['configuration'];
        self::assertTrue($output['success']);
        self::assertSame('{"min":1000}', $configuration['mybank']['custom_amounts']);
        self::assertSame('["IT"]', $configuration['mybank']['custom_countries']);
    }

    public function test_body_without_the_global_keys_still_gets_a_400(): void
    {
        $body = $this->body(['bancontact_min_amounts' => '0.1', 'bancontact_max_amounts' => '100']);
        unset($body['payplug_sandbox']);

        $output = $this->save($this->account(), $body);

        self::assertFalse($output['success']);
        self::assertSame('Invalid request body.', $output['data']['msg']);
    }

    public function test_country_outside_the_proposable_list_is_refused_and_nothing_is_saved(): void
    {
        update_option('woocommerce_allowed_countries', 'specific');
        update_option('woocommerce_specific_allowed_countries', ['BE', 'FR', 'IT']);
        $before = get_option('woocommerce_payplug_settings');

        $output = $this->save($this->account(), $this->body(['satispay_countries' => ['FR', 'DE']]));

        self::assertFalse($output['success']);
        self::assertSame(sprintf(__('payplug_apm_country_invalid_error', 'payplug'), 'Satispay'), $output['data']['msg']);
        self::assertSame($before, get_option('woocommerce_payplug_settings'));
    }

    public function test_untouched_selection_of_an_unrestricted_merchant_stores_nothing(): void
    {
        update_option('woocommerce_allowed_countries', 'specific');
        update_option('woocommerce_specific_allowed_countries', ['BE', 'FR', 'IT']);

        $this->save($this->account(), $this->body(['satispay_countries' => ['BE', 'FR', 'IT'], 'enable_debug' => 1]));

        self::assertSame('', get_option('woocommerce_payplug_settings')['payment_methods']['configuration']['satispay']['custom_countries']);
    }

    public function test_stored_restriction_survives_a_resubmit_of_the_same_subset(): void
    {
        update_option('woocommerce_allowed_countries', 'specific');
        update_option('woocommerce_specific_allowed_countries', ['BE', 'FR', 'IT']);
        $settings = $this->settings();
        $settings['payment_methods']['configuration']['satispay']['custom_countries'] = '["IT"]';
        update_option('woocommerce_payplug_settings', $settings);

        $this->save($this->account(), $this->body(['satispay_countries' => ['IT'], 'enable_debug' => 1]));

        self::assertSame('["IT"]', get_option('woocommerce_payplug_settings')['payment_methods']['configuration']['satispay']['custom_countries']);
    }

    public function test_empty_selection_is_stored_as_nobody(): void
    {
        $this->save($this->account(), $this->body(['bizum_countries' => []]));

        self::assertSame('[]', get_option('woocommerce_payplug_settings')['payment_methods']['configuration']['bizum']['custom_countries']);
    }

    /**
     * @return \Mockery\MockInterface
     */
    protected function account()
    {
        $account = \Mockery::mock();
        $account->shouldReceive('is_logged')->andReturn(true);

        return $account;
    }

    protected function save($account, array $body): array
    {
        $ajax = \Mockery::mock(Ajax::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $ajax->shouldReceive('get_gateway')->with('account')->andReturn($account);
        $ajax->shouldReceive('get_json_body')->andReturn($body);

        return $this->capture_json_response(function () use ($ajax): void {
            $ajax->save_action(new \WP_REST_Request('POST', '/payplug_api/save'));
        });
    }

    protected function settings(): array
    {
        $configuration = (new \ReflectionClass(Configuration::class))->newInstanceWithoutConstructor();
        $settings = $configuration->extract_options_from_fields();
        $settings['email'] = 'merchant@example.test';
        $settings['mode'] = true;
        $settings['apm_cache_mode'] = 'live';
        $settings['api_key'] = '{"live":"sk_live_x","test":"sk_test_x"}';
        $permissions = [
            'bancontact' => '["ALL"]',
            'ideal' => '["NL"]',
            'mybank' => '["IT"]',
            'satispay' => '["BE","FR","IT"]',
            'wero' => '["BE","DE"]',
            'bizum' => '["ES"]',
            'scalapay' => '["FR","IT"]',
        ];
        foreach ($permissions as $apm => $countries) {
            $settings['payment_methods']['permissions'][$apm] = [
                'enabled' => true,
                'countries' => $countries,
                'amounts' => 'scalapay' === $apm ? '{"min":{"EUR":500},"max":{"EUR":200000}}' : '{"min":{"EUR":30},"max":{"EUR":2000000}}',
            ];
        }

        return $settings;
    }

    protected function body(array $overrides = []): array
    {
        $body = [
            'payplug_enable' => 1,
            'enable_debug' => 0,
            'payplug_sandbox' => 0,
            'enable_standard' => 1,
            'standard_payment_title' => 'Card',
            'standard_payment_description' => '',
            'payplug_embeded' => 'redirect',
            'enable_one_click' => 0,
            'enable_applepay' => 0,
            'applepay_carriers' => [],
            'enable_applepay_cart' => 0,
            'enable_applepay_checkout' => 0,
            'enable_applepay_product' => 0,
            'enable_oney' => 0,
            'enable_oney_product_animation' => 0,
            'oney_min_amounts' => 100,
            'oney_max_amounts' => 3000,
            'payplug_oney' => 'with_fees',
        ];
        foreach (['oney', 'apple_pay', 'payplug', 'american_express'] as $name) {
            $body['enable_' . $name] = 0;
        }
        foreach (ApmEligibility::APMS as $apm) {
            $body['enable_' . $apm] = 1;
        }

        return array_merge($body, $overrides);
    }

    protected function capture_json_response(callable $callback): array
    {
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', static function (): callable {
            return static function (): void {
                throw new \RuntimeException('wp_die');
            };
        });

        // The test process has already printed output: http_response_code() can only warn here.
        set_error_handler(static fn (int $errno, string $errstr): bool => 0 === strpos($errstr, 'http_response_code(): Cannot set response code - headers already sent'), E_WARNING);
        ob_start();
        try {
            $callback();
            self::fail('wp_send_json was not called');
        } catch (\RuntimeException $e) {
            self::assertSame('wp_die', $e->getMessage());
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        return json_decode($output, true);
    }
}
