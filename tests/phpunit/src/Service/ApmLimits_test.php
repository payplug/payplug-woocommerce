<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmLimits;
use PHPUnit\Framework\TestCase;

class ApmLimits_test extends TestCase
{
    public function test_parses_countries_and_eur_amounts(): void
    {
        $limits = ApmLimits::from_permission([
            'enabled' => true,
            'countries' => '["it","FR"]',
            'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
        ]);

        self::assertSame(['IT', 'FR'], $limits['countries']);
        self::assertSame([30, 2000000], [$limits['min'], $limits['max']]);
    }

    public function test_disabled_on_the_account_means_no_limits(): void
    {
        self::assertNull(ApmLimits::from_permission([
            'enabled' => false,
            'countries' => '["ALL"]',
            'amounts' => '{"min":{"EUR":30},"max":{"EUR":2000000}}',
        ]));
    }

    public function test_enabled_or_missing_flag_keeps_the_limits(): void
    {
        $amounts = '{"min":{"EUR":30},"max":{"EUR":2000000}}';

        self::assertNotNull(ApmLimits::from_permission(['enabled' => true, 'countries' => '["ALL"]', 'amounts' => $amounts]));
        self::assertNotNull(ApmLimits::from_permission(['countries' => '["ALL"]', 'amounts' => $amounts]));
    }

    public function test_all_means_no_country_restriction(): void
    {
        $limits = ApmLimits::from_permission(['countries' => '["ALL"]', 'amounts' => '{"min":{"EUR":30},"max":{"EUR":99}}']);

        self::assertNull($limits['countries']);
    }

    public function test_empty_countries_means_no_restriction(): void
    {
        $limits = ApmLimits::from_permission(['countries' => '[]', 'amounts' => '{"min":{"EUR":30},"max":{"EUR":99}}']);

        self::assertNull($limits['countries']);
    }

    public function test_decoded_arrays_are_accepted(): void
    {
        $limits = ApmLimits::from_permission(['countries' => ['be', ' nl '], 'amounts' => ['min' => ['EUR' => '50'], 'max' => ['EUR' => 1000]]]);

        self::assertSame(['BE', 'NL'], $limits['countries']);
        self::assertSame([50, 1000], [$limits['min'], $limits['max']]);
    }

    public function test_missing_entry_is_null(): void
    {
        self::assertNull(ApmLimits::from_permission([]));
        self::assertNull(ApmLimits::from_permission(null));
    }

    public function test_malformed_json_is_null(): void
    {
        self::assertNull(ApmLimits::from_permission(['countries' => '{bad', 'amounts' => 'x']));
        self::assertNull(ApmLimits::from_permission(['countries' => '["FR"]', 'amounts' => 'x']));
        self::assertNull(ApmLimits::from_permission(['countries' => '{bad', 'amounts' => '{"min":{"EUR":30},"max":{"EUR":99}}']));
    }

    public function test_missing_eur_bound_is_null(): void
    {
        self::assertNull(ApmLimits::from_permission(['countries' => '["FR"]', 'amounts' => '{"min":{"USD":30},"max":{"USD":99}}']));
        self::assertNull(ApmLimits::from_permission(['countries' => '["FR"]', 'amounts' => '{"min":{"EUR":30},"max":{"USD":99}}']));
    }

    public function test_non_numeric_or_negative_bound_is_null(): void
    {
        self::assertNull(ApmLimits::from_permission(['countries' => '["FR"]', 'amounts' => '{"min":{"EUR":"abc"},"max":{"EUR":99}}']));
        self::assertNull(ApmLimits::from_permission(['countries' => '["FR"]', 'amounts' => '{"min":{"EUR":-1},"max":{"EUR":99}}']));
    }

    public function test_keeps_other_currencies(): void
    {
        $limits = ApmLimits::from_permission([
            'countries' => '["ALL"]',
            'amounts' => '{"min":{"EUR":30,"USD":40},"max":{"EUR":2000000,"USD":3000000}}',
        ]);

        self::assertSame(['min' => ['EUR' => 30, 'USD' => 40], 'max' => ['EUR' => 2000000, 'USD' => 3000000]], $limits['amounts_all']);
    }
}
