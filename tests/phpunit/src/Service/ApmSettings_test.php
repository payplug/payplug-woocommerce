<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmSettings;
use PHPUnit\Framework\TestCase;

class ApmSettings_test extends TestCase
{
    public function test_reads_limits_and_merchant_settings(): void
    {
        $options = $this->options([
            'custom_amounts' => '{"min":500,"max":10000}',
            'custom_countries' => '["be","FR"]',
        ]);

        $config = ApmSettings::config_for($options, 'bancontact');

        self::assertSame(['IT', 'BE', 'FR'], $config['limits']['countries']);
        self::assertSame([30, 2000000], [$config['limits']['min'], $config['limits']['max']]);
        self::assertSame(['min' => 500, 'max' => 10000], $config['custom_amounts']);
        self::assertSame(['BE', 'FR'], $config['custom_countries']);
    }

    /**
     * @dataProvider stored_countries_provider
     */
    public function test_custom_countries($stored, ?array $expected): void
    {
        $config = ApmSettings::config_for($this->options(['custom_countries' => $stored]), 'bancontact');

        self::assertSame($expected, $config['custom_countries']);
    }

    public function stored_countries_provider(): array
    {
        return [
            'empty string: nothing stored' => ['', null],
            'empty list: nobody' => ['[]', []],
            'subset' => ['["BE"]', ['BE']],
            'malformed json' => ['{bad', null],
            'json object' => ['{"a":"BE"}', null],
            'not a string' => [false, null],
        ];
    }

    public function test_malformed_custom_amounts_is_empty(): void
    {
        $config = ApmSettings::config_for($this->options(['custom_amounts' => 'nope']), 'bancontact');

        self::assertSame([], $config['custom_amounts']);
    }

    public function test_tolerates_missing_keys(): void
    {
        self::assertSame(['limits' => null, 'custom_amounts' => [], 'custom_countries' => null], ApmSettings::config_for([], 'wero'));
        self::assertSame(['limits' => null, 'custom_amounts' => [], 'custom_countries' => null], ApmSettings::config_for(['payment_methods' => 'x'], 'wero'));

        $options = $this->options([]);
        unset($options['payment_methods']['configuration']['bancontact']);
        $config = ApmSettings::config_for($options, 'bancontact');
        self::assertNotNull($config['limits']);
        self::assertSame([], $config['custom_amounts']);
        self::assertNull($config['custom_countries']);
    }

    public function test_missing_permission_entry_means_no_limits(): void
    {
        $options = $this->options([]);
        $options['payment_methods']['permissions']['bancontact'] = [];

        self::assertNull(ApmSettings::config_for($options, 'bancontact')['limits']);
    }

    private function options(array $configuration): array
    {
        return [
            'payment_methods' => [
                'permissions' => [
                    'bancontact' => [
                        'enabled' => true,
                        'countries' => '["it","BE","FR"]',
                        'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
                    ],
                ],
                'configuration' => [
                    'bancontact' => array_merge(['active' => true], $configuration),
                ],
            ],
        ];
    }
}
