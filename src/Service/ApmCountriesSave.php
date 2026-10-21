<?php

namespace Payplug\PayplugWoocommerce\Service;

/**
 * Billing-country selection of a PPRO payment method posted from the BO. The BO always posts the
 * displayed selection, so what is stored depends on what was stored before: a merchant without
 * restriction stays without one, and a deliberate exclusion is never widened silently.
 */
class ApmCountriesSave
{
    /**
     * Countries the merchant can pick: API countries enabled in the shop, every shop country for ALL.
     *
     * @param string[]|null $api_countries null = ALL
     * @param string[] $shop_countries
     *
     * @return string[]
     */
    public static function proposable(?array $api_countries, array $shop_countries): array
    {
        $shop = self::codes($shop_countries);
        if (null === $api_countries) {
            return $shop;
        }

        return array_values(array_intersect($shop, self::codes($api_countries)));
    }

    /**
     * Stored countries the BO can no longer offer (disabled in the shop or removed by the API).
     *
     * @param string[]|null $stored
     * @param string[] $proposable
     *
     * @return string[]
     */
    public static function removed(?array $stored, array $proposable): array
    {
        if (empty($stored)) {
            return [];
        }

        return array_values(array_diff(self::codes($stored), $proposable));
    }

    /**
     * Value null = store no restriction; error country_invalid when a posted country cannot be proposed.
     *
     * @param string[]|null $stored null = no restriction stored
     * @param array $submitted posted <apm>_countries
     * @param string[] $proposable self::proposable()
     * @param string[]|null $api_countries null = ALL
     * @param string[] $shop_countries
     *
     * @return array{skip: bool, error: string|null, value: string[]|null}
     */
    public static function normalise(?array $stored, array $submitted, array $proposable, ?array $api_countries, array $shop_countries): array
    {
        if (empty($proposable)) {
            return ['skip' => true, 'error' => null, 'value' => null];
        }

        $selection = [];
        foreach ($submitted as $country) {
            $code = is_string($country) ? strtoupper(trim($country)) : '';
            if (!in_array($code, $proposable, true)) {
                return ['skip' => false, 'error' => 'country_invalid', 'value' => null];
            }
            $selection[$code] = $code;
        }
        $selection = array_values($selection);

        if (null === $stored) {
            $value = self::same_set($selection, $proposable) ? null : $selection;
        } elseif (!empty($stored) && self::same_set($selection, null === $api_countries ? self::codes($shop_countries) : self::codes($api_countries))) {
            $value = null;
        } else {
            $value = $selection;
        }

        return ['skip' => false, 'error' => null, 'value' => $value];
    }

    /**
     * @param string[] $a
     * @param string[] $b
     */
    private static function same_set(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * @return string[]
     */
    private static function codes(array $countries): array
    {
        $codes = [];
        foreach ($countries as $country) {
            if (is_string($country) && '' !== trim($country)) {
                $code = strtoupper(trim($country));
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }
}
