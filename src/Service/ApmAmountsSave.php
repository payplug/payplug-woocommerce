<?php

namespace Payplug\PayplugWoocommerce\Service;

/**
 * Validates the min/max amounts (euros) posted from the BO for one PPRO payment method.
 */
class ApmAmountsSave
{
    /**
     * Error is one of invalid, out_of_bounds, max_gt_min. Store holds the cents to keep, a value
     * equal to its API bound is left out so that later API changes apply.
     *
     * @param mixed $min posted <apm>_min_amounts, null when absent
     * @param mixed $max posted <apm>_max_amounts, null when absent
     * @param int $api_min API bound, cents
     * @param int $api_max API bound, cents
     *
     * @return array{skip: bool, error: string|null, store: array}
     */
    public static function parse($min, $max, int $api_min, int $api_max): array
    {
        if (null === $min || null === $max) {
            return ['skip' => true, 'error' => null, 'store' => []];
        }

        $amounts = [];
        foreach (['min' => $min, 'max' => $max] as $bound => $value) {
            if (is_string($value) && '' === trim($value)) {
                continue;
            }
            if (!(is_int($value) || is_float($value) || is_string($value)) || !is_numeric($value) || $value < 0) {
                return self::error('invalid');
            }
            $amounts[$bound] = (int) round((float) $value * 100);
        }

        foreach ($amounts as $cents) {
            if ($cents < $api_min || $cents > $api_max) {
                return self::error('out_of_bounds');
            }
        }

        if (($amounts['max'] ?? $api_max) <= ($amounts['min'] ?? $api_min)) {
            return self::error('max_gt_min');
        }

        $store = [];
        if (isset($amounts['min']) && $amounts['min'] !== $api_min) {
            $store['min'] = $amounts['min'];
        }
        if (isset($amounts['max']) && $amounts['max'] !== $api_max) {
            $store['max'] = $amounts['max'];
        }

        return ['skip' => false, 'error' => null, 'store' => $store];
    }

    /**
     * Posted amount of a BO field: null when the key is absent, '' when the field was emptied
     * (posted as null) or had no value (posted as false).
     *
     * @return mixed
     */
    public static function posted(array $data, string $key)
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }

        return null === $data[$key] || false === $data[$key] ? '' : $data[$key];
    }

    /**
     * Amount shown in the merchant messages: "5", "0.30", "49.99".
     */
    public static function euros(int $cents): string
    {
        return 0 === $cents % 100 ? (string) intdiv($cents, 100) : number_format($cents / 100, 2, '.', '');
    }

    private static function error(string $error): array
    {
        return ['skip' => false, 'error' => $error, 'store' => []];
    }
}
