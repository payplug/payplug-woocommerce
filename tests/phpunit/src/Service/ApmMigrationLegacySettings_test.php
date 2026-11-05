<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Admin\Vue\PaymentMethods;
use Payplug\PayplugWoocommerce\Service\ApmEligibility;
use Payplug\PayplugWoocommerce\Service\ApmMigration;
use Payplug\PayplugWoocommerce\Service\ApmSettings;
use Payplug\PayplugWoocommerce\Service\Configuration;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\WooCommerceAjaxFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Stored settings holding a Scalapay default_amounts key that is not in the schema, and a custom
 * range outside the account bounds.
 */
class ApmMigrationLegacySettings_test extends TestCase
{
    use WooCommerceAjaxFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->set_up_wc_frontend();
        update_option('woocommerce_payplug_settings', $this->legacy_settings());
    }

    protected function tearDown(): void
    {
        $this->tear_down_wc_frontend();
        parent::tearDown();
    }

    public function test_update_normalises_the_range_and_keeps_every_other_stored_setting(): void
    {
        $before = get_option('woocommerce_payplug_settings');

        self::assertTrue(ApmMigration::create()->run());

        $after = get_option('woocommerce_payplug_settings');
        self::assertSame('{}', $after['payment_methods']['configuration']['scalapay']['custom_amounts']);
        self::assertSame(ApmMigration::VERSION, $after['apm_restrictions_version']);

        $expected = $before;
        $expected['payment_methods']['configuration']['scalapay']['custom_amounts'] = '{}';
        $expected['apm_restrictions_version'] = ApmMigration::VERSION;
        self::assertSame($expected, $after);
    }

    public function test_stored_key_unknown_to_the_schema_is_read_and_written_back_unchanged(): void
    {
        $configuration = new Configuration();

        self::assertSame('{"min":500, "max":400000}', $configuration->get_option('payment_methods.configuration.scalapay.default_amounts'));

        $configuration->update_options(['debug' => true]);

        $stored = get_option('woocommerce_payplug_settings');
        self::assertTrue($stored['debug']);
        self::assertSame('{"min":500, "max":400000}', $stored['payment_methods']['configuration']['scalapay']['default_amounts']);
    }

    public function test_limits_and_bo_payload_use_the_api_bounds(): void
    {
        ApmMigration::create()->run();
        $options = (new Configuration())->get_options();

        $config = ApmSettings::config_for($options, 'scalapay');
        self::assertSame(['min' => 500, 'max' => 200000], ApmEligibility::effective_amounts($config['limits'], $config['custom_amounts']));

        $payload = PaymentMethods::payment_method_scalapay(true, $options);
        $thresholds = $payload['advanced_options'][0]['descriptions'];
        self::assertSame(5, $thresholds['min_amount']['value']);
        self::assertSame(2000, $thresholds['max_amount']['value']);
        self::assertSame('scalapay_countries', $payload['countries']['name']);
    }

    private function legacy_settings(): array
    {
        $settings = (new \ReflectionClass(Configuration::class))->newInstanceWithoutConstructor()->extract_options_from_fields();
        $settings['email'] = 'merchant@example.test';
        $settings['mode'] = true;
        $settings['apm_cache_mode'] = 'live';
        $settings['payment_methods']['permissions']['scalapay'] = [
            'enabled' => true,
            'countries' => '["FR","IT"]',
            'amounts' => '{"min":{"EUR":500},"max":{"EUR":200000}}',
        ];
        $settings['payment_methods']['configuration']['scalapay'] = [
            'active' => true,
            'default_amounts' => '{"min":500, "max":400000}',
            'custom_amounts' => '{"min":400,"max":900000}',
        ];

        return $settings;
    }
}
