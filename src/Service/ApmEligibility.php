<?php

namespace Payplug\PayplugWoocommerce\Service;

/**
 * Display eligibility of the PPRO payment methods: cart amount (cents) and billing country,
 * from the cached API limits narrowed by the merchant settings.
 */
class ApmEligibility
{
    public const APMS = ['bancontact', 'ideal', 'mybank', 'satispay', 'wero', 'bizum', 'scalapay'];
    public const CURRENCY = ApmLimits::CURRENCY;

    public const BRANDS = [
        'bancontact' => 'Bancontact',
        'ideal' => 'iDEAL',
        'mybank' => 'MyBank',
        'satispay' => 'Satispay',
        'wero' => 'Wero',
        'bizum' => 'Bizum',
        'scalapay' => 'Scalapay',
    ];

    public static function is_restricted_apm(string $apm): bool
    {
        return in_array($apm, self::APMS, true);
    }

    public static function brand(string $apm): string
    {
        return self::BRANDS[$apm] ?? $apm;
    }

    /**
     * Merchant value when it lies within the API bounds, API bound otherwise.
     *
     * @param array|null $api_limits ApmLimits::from_permission() result
     * @param array $custom_amounts merchant {"min":cents,"max":cents}, either key optional
     *
     * @return array{min: int, max: int}|null
     */
    public static function effective_amounts(?array $api_limits, array $custom_amounts): ?array
    {
        if (null === $api_limits) {
            return null;
        }

        $api_min = (int) $api_limits['min'];
        $api_max = (int) $api_limits['max'];
        $effective = ['min' => $api_min, 'max' => $api_max];
        foreach (['min', 'max'] as $bound) {
            $value = $custom_amounts[$bound] ?? null;
            if (is_numeric($value) && (int) $value >= $api_min && (int) $value <= $api_max) {
                $effective[$bound] = (int) $value;
            }
        }

        return $effective;
    }

    /**
     * @param array|null $api_limits ApmLimits::from_permission() result
     * @param string[]|null $custom_countries merchant selection, null = none stored
     *
     * @return string[]|null null = no restriction, [] = nobody
     */
    public static function effective_countries(?array $api_limits, ?array $custom_countries): ?array
    {
        if (null === $api_limits) {
            return [];
        }

        $api_countries = $api_limits['countries'];
        if (null === $custom_countries) {
            return $api_countries;
        }

        $selection = [];
        foreach ($custom_countries as $country) {
            if (!is_string($country)) {
                continue;
            }
            $code = strtoupper(trim($country));
            if ('' !== $code && (null === $api_countries || in_array($code, $api_countries, true))) {
                $selection[$code] = $code;
            }
        }

        return array_values($selection);
    }

    /**
     * @param array $apm_config limits (ApmLimits::from_permission() result or null), custom_amounts, custom_countries
     *
     * @return string|null null = eligible, else no_limits, currency, no_billing_address, inconsistent_bounds,
     *                     below_min, above_max or country
     */
    public static function reason_not_eligible(array $apm_config, int $amount_cents, string $currency, string $billing_country): ?string
    {
        $limits = $apm_config['limits'] ?? null;
        if (empty($limits)) {
            return 'no_limits';
        }

        if (self::CURRENCY !== strtoupper(trim($currency))) {
            return 'currency';
        }

        $billing_country = strtoupper(trim($billing_country));
        if ('' === $billing_country) {
            return 'no_billing_address';
        }

        $amounts = self::effective_amounts($limits, $apm_config['custom_amounts'] ?? []);
        if ($amounts['min'] >= $amounts['max']) {
            return 'inconsistent_bounds';
        }
        if ($amount_cents < $amounts['min']) {
            return 'below_min';
        }
        if ($amount_cents > $amounts['max']) {
            return 'above_max';
        }

        $countries = self::effective_countries($limits, $apm_config['custom_countries'] ?? null);
        if (null !== $countries && !in_array($billing_country, $countries, true)) {
            return 'country';
        }

        return null;
    }
}
