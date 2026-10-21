<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ApmEligibility;
use PHPUnit\Framework\TestCase;

class ApmEligibility_test extends TestCase
{
    /**
     * @dataProvider boundaries_provider
     */
    public function test_amount_bounds_inclusive(int $amount, ?string $expected): void
    {
        self::assertSame($expected, ApmEligibility::reason_not_eligible($this->config(), $amount, 'EUR', 'FR'));
    }

    public function boundaries_provider(): array
    {
        return [
            'one cent below the min' => [29, 'below_min'],
            'exactly the min' => [30, null],
            'exactly the max' => [2000000, null],
            'one cent above the max' => [2000001, 'above_max'],
            'zero' => [0, 'below_min'],
        ];
    }

    public function test_no_limits_in_cache_is_not_eligible(): void
    {
        $config = $this->config(['limits' => null]);

        self::assertSame('no_limits', ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'FR'));
    }

    public function test_missing_config_keys_mean_no_limits(): void
    {
        self::assertSame('no_limits', ApmEligibility::reason_not_eligible([], 1000, 'EUR', 'FR'));
    }

    public function test_other_currency_is_not_eligible(): void
    {
        self::assertSame('currency', ApmEligibility::reason_not_eligible($this->config(), 1000, 'USD', 'FR'));
        self::assertSame('currency', ApmEligibility::reason_not_eligible($this->config(), 1000, '', 'FR'));
    }

    public function test_lower_case_currency_is_accepted(): void
    {
        self::assertNull(ApmEligibility::reason_not_eligible($this->config(), 1000, 'eur', 'FR'));
    }

    public function test_empty_billing_country_is_not_eligible(): void
    {
        self::assertSame('no_billing_address', ApmEligibility::reason_not_eligible($this->config(), 1000, 'EUR', ''));
        self::assertSame('no_billing_address', ApmEligibility::reason_not_eligible($this->config(), 1000, 'EUR', '  '));
    }

    public function test_billing_country_outside_api_list_is_not_eligible(): void
    {
        $config = $this->config(['limits' => ['countries' => ['IT'], 'min' => 30, 'max' => 2000000]]);

        self::assertSame('country', ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'FR'));
        self::assertNull(ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'IT'));
    }

    public function test_lower_case_billing_country_is_accepted(): void
    {
        $config = $this->config(['limits' => ['countries' => ['IT'], 'min' => 30, 'max' => 2000000]]);

        self::assertNull(ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'it'));
    }

    public function test_merchant_range_wins_over_api_bounds(): void
    {
        $config = $this->config(['custom_amounts' => ['min' => 500, 'max' => 10000]]);

        self::assertSame('below_min', ApmEligibility::reason_not_eligible($config, 499, 'EUR', 'FR'));
        self::assertNull(ApmEligibility::reason_not_eligible($config, 500, 'EUR', 'FR'));
        self::assertNull(ApmEligibility::reason_not_eligible($config, 10000, 'EUR', 'FR'));
        self::assertSame('above_max', ApmEligibility::reason_not_eligible($config, 10001, 'EUR', 'FR'));
    }

    public function test_merchant_values_outside_api_bounds_fall_back_to_api_bounds(): void
    {
        $limits = ['countries' => null, 'min' => 500, 'max' => 200000];

        self::assertSame(['min' => 500, 'max' => 200000], ApmEligibility::effective_amounts($limits, ['min' => 400, 'max' => 400000]));
        self::assertSame(['min' => 500, 'max' => 200000], ApmEligibility::effective_amounts($limits, ['min' => 300000, 'max' => 100]));
        self::assertSame(['min' => 1000, 'max' => 200000], ApmEligibility::effective_amounts($limits, ['min' => '1000']));
        self::assertSame(['min' => 500, 'max' => 200000], ApmEligibility::effective_amounts($limits, ['min' => 'abc', 'max' => null]));
    }

    public function test_effective_amounts_without_limits_is_null(): void
    {
        self::assertNull(ApmEligibility::effective_amounts(null, ['min' => 500]));
    }

    public function test_inconsistent_merchant_bounds_are_not_eligible(): void
    {
        $config = $this->config(['custom_amounts' => ['min' => 10000, 'max' => 5000]]);
        self::assertSame('inconsistent_bounds', ApmEligibility::reason_not_eligible($config, 7000, 'EUR', 'FR'));

        $config = $this->config(['custom_amounts' => ['min' => 5000, 'max' => 5000]]);
        self::assertSame('inconsistent_bounds', ApmEligibility::reason_not_eligible($config, 5000, 'EUR', 'FR'));
    }

    public function test_inconsistent_api_bounds_are_not_eligible(): void
    {
        $config = $this->config(['limits' => ['countries' => null, 'min' => 5000, 'max' => 100]]);

        self::assertSame('inconsistent_bounds', ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'FR'));
    }

    public function test_merchant_countries_narrow_the_api_list(): void
    {
        $config = $this->config([
            'limits' => ['countries' => ['BE', 'FR'], 'min' => 30, 'max' => 2000000],
            'custom_countries' => ['BE'],
        ]);

        self::assertSame('country', ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'FR'));
        self::assertNull(ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'BE'));
    }

    public function test_empty_merchant_selection_hides_for_everybody(): void
    {
        $config = $this->config(['custom_countries' => []]);

        self::assertSame('country', ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'FR'));
        self::assertSame([], ApmEligibility::effective_countries($config['limits'], []));
    }

    public function test_merchant_country_outside_api_list_is_ignored(): void
    {
        $limits = ['countries' => ['BE', 'FR'], 'min' => 30, 'max' => 2000000];

        self::assertSame(['FR'], ApmEligibility::effective_countries($limits, ['FR', 'IT']));
        self::assertSame([], ApmEligibility::effective_countries($limits, ['IT']));
    }

    public function test_effective_countries(): void
    {
        $all = ['countries' => null, 'min' => 30, 'max' => 2000000];
        $list = ['countries' => ['BE', 'FR'], 'min' => 30, 'max' => 2000000];

        self::assertNull(ApmEligibility::effective_countries($all, null));
        self::assertSame(['IT', 'ES'], ApmEligibility::effective_countries($all, ['it', 'ES']));
        self::assertSame(['BE', 'FR'], ApmEligibility::effective_countries($list, null));
        self::assertSame([], ApmEligibility::effective_countries(null, null));
    }

    public function test_api_all_with_merchant_selection(): void
    {
        $config = $this->config(['custom_countries' => ['IT']]);

        self::assertSame('country', ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'FR'));
        self::assertNull(ApmEligibility::reason_not_eligible($config, 1000, 'EUR', 'IT'));
    }

    public function test_restricted_apms(): void
    {
        foreach (['bancontact', 'ideal', 'mybank', 'satispay', 'wero', 'bizum', 'scalapay'] as $apm) {
            self::assertTrue(ApmEligibility::is_restricted_apm($apm), $apm);
        }
        foreach (['giropay', 'sofort', 'payplug', 'american_express', 'apple_pay', 'oney_x3_with_fees', ''] as $id) {
            self::assertFalse(ApmEligibility::is_restricted_apm($id), $id);
        }
    }

    private function config(array $overrides = []): array
    {
        return array_merge([
            'limits' => ['countries' => null, 'min' => 30, 'max' => 2000000],
            'custom_amounts' => [],
            'custom_countries' => null,
        ], $overrides);
    }
}
