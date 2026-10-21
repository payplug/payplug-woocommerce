<?php

namespace Payplug\PayplugWoocommerce\Service;

/**
 * Builds the ApmEligibility input of one payment method from the raw plugin options.
 */
class ApmSettings
{
    /**
     * @param array $options woocommerce_payplug_settings, read raw (keys may be missing)
     *
     * @return array{limits: array|null, custom_amounts: array, custom_countries: string[]|null}
     */
    public static function config_for(array $options, string $apm): array
    {
        $payment_methods = isset($options['payment_methods']) && is_array($options['payment_methods']) ? $options['payment_methods'] : [];
        $permission = $payment_methods['permissions'][$apm] ?? null;
        $configuration = $payment_methods['configuration'][$apm] ?? [];
        $configuration = is_array($configuration) ? $configuration : [];

        return [
            'limits' => ApmLimits::from_permission(is_array($permission) ? $permission : null),
            'custom_amounts' => self::custom_amounts($configuration['custom_amounts'] ?? null),
            'custom_countries' => self::custom_countries($configuration['custom_countries'] ?? null),
        ];
    }

    /**
     * @param mixed $stored JSON {"min":cents,"max":cents}
     */
    public static function custom_amounts($stored): array
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        if (!is_array($decoded)) {
            return [];
        }

        return array_intersect_key($decoded, ['min' => true, 'max' => true]);
    }

    /**
     * @param mixed $stored '' = nothing stored, '[]' = nobody, '["BE"]' = subset
     *
     * @return string[]|null
     */
    public static function custom_countries($stored): ?array
    {
        if (!is_string($stored) || '' === $stored) {
            return null;
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded) || array_values($decoded) !== $decoded) {
            return null;
        }

        $codes = [];
        foreach ($decoded as $country) {
            if (is_string($country) && '' !== trim($country)) {
                $code = strtoupper(trim($country));
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }
}
