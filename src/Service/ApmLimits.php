<?php

namespace Payplug\PayplugWoocommerce\Service;

/**
 * Reads the API limits cached for one payment method in payment_methods.permissions.<apm>.
 */
class ApmLimits
{
    public const CURRENCY = 'EUR';

    /**
     * @param array|null $permission payment_methods.permissions.<apm>: countries (JSON list, "ALL" for every
     *                               country) and amounts (JSON {"min":{"EUR":cents},"max":{"EUR":cents}})
     *
     * Null when the entry is missing, malformed, has no EUR bounds or is disabled on the account
     * (enabled === false; an absent flag is accepted). Countries null = no country restriction.
     *
     * @return array{countries: string[]|null, min: int, max: int, amounts_all: array}|null
     */
    public static function from_permission(?array $permission): ?array
    {
        if (empty($permission) || !isset($permission['countries'], $permission['amounts'])) {
            return null;
        }
        if (false === ($permission['enabled'] ?? null)) {
            return null;
        }

        $countries = self::decode($permission['countries']);
        $amounts = self::decode($permission['amounts']);
        if (null === $countries || null === $amounts) {
            return null;
        }

        $amounts_all = [];
        foreach (['min', 'max'] as $bound) {
            if (!isset($amounts[$bound]) || !is_array($amounts[$bound])) {
                return null;
            }
            foreach ($amounts[$bound] as $currency => $value) {
                if (!is_numeric($value) || $value < 0) {
                    return null;
                }
                $amounts_all[$bound][(string) $currency] = (int) $value;
            }
            if (!isset($amounts_all[$bound][self::CURRENCY])) {
                return null;
            }
        }

        return [
            'countries' => self::normalise_countries($countries),
            'min' => $amounts_all['min'][self::CURRENCY],
            'max' => $amounts_all['max'][self::CURRENCY],
            'amounts_all' => $amounts_all,
        ];
    }

    /**
     * @param array $countries
     *
     * @return string[]|null null = no country restriction
     */
    public static function normalise_countries(array $countries): ?array
    {
        $codes = [];
        foreach ($countries as $country) {
            if (!is_string($country) || '' === trim($country)) {
                continue;
            }
            $code = strtoupper(trim($country));
            if ('ALL' === $code) {
                return null;
            }
            $codes[$code] = $code;
        }

        return empty($codes) ? null : array_values($codes);
    }

    /**
     * @param mixed $value
     *
     * @return array|null
     */
    private static function decode($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
