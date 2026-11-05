<?php

namespace Payplug\PayplugWoocommerce\Admin\Vue;

use Payplug\PayplugWoocommerce\Service\ApmAmountsSave;
use Payplug\PayplugWoocommerce\Service\ApmCountriesSave;
use Payplug\PayplugWoocommerce\Service\ApmEligibility;

/**
 * BO blocks of a PPRO payment method: cart amount range and billing countries.
 */
class ApmRestrictionsBlock
{
    /**
     * @var callable(string): string
     */
    private $translate;

    /**
     * @param callable(string): string $translate translation of a payplug text domain key
     */
    public function __construct(callable $translate)
    {
        $this->translate = $translate;
    }

    /**
     * @param array{min: int, max: int} $effective cents
     * @param array{min: int, max: int} $api_bounds cents
     */
    public function thresholds(string $apm, array $effective, array $api_bounds): array
    {
        return [
            'name' => 'thresholds',
            'title' => $this->t('payplug_apm_thresholds_title'),
            'descriptions' => [
                'description' => $this->t('payplug_apm_thresholds_description'),
                'decimal' => true,
                'strict_range' => true,
                'min_amount' => [
                    'name' => $apm . '_min_amounts',
                    'label' => $this->t('payplug_apm_thresholds_min'),
                    'value' => $effective['min'] / 100,
                    'placeholder' => $api_bounds['min'] / 100,
                    'default' => $api_bounds['min'] / 100,
                ],
                'inter' => $this->t('and'),
                'max_amount' => [
                    'name' => $apm . '_max_amounts',
                    'label' => $this->t('payplug_apm_thresholds_max'),
                    'value' => $effective['max'] / 100,
                    'placeholder' => $api_bounds['max'] / 100,
                    'default' => $api_bounds['max'] / 100,
                ],
                'error' => [
                    'text' => sprintf($this->t('payplug_apm_thresholds_error_text'), ApmAmountsSave::euros($api_bounds['min']), ApmAmountsSave::euros($api_bounds['max'])),
                    'maxtext' => $this->t('payplug_apm_thresholds_error_maxtext'),
                    'mintext' => $this->t('payplug_apm_thresholds_error_mintext'),
                ],
            ],
            'switch' => false,
        ];
    }

    /**
     * @param string[]|null $api_countries null = ALL
     * @param string[]|null $stored merchant selection, null = none stored
     * @param string[] $shop_countries countries enabled in the shop
     * @param array<string, string> $country_names ISO code => name in the BO language
     */
    public function countries(string $apm, ?array $api_countries, ?array $stored, array $shop_countries, array $country_names): array
    {
        $brand = ApmEligibility::brand($apm);
        $proposable = ApmCountriesSave::proposable($api_countries, $shop_countries);

        $options = [];
        foreach ($proposable as $code) {
            $options[] = ['value' => $code, 'label' => $country_names[$code] ?? $code];
        }

        $block = [
            'name' => $apm . '_countries',
            'title' => $this->t('payplug_apm_countries_title'),
            'description' => $this->t('payplug_apm_countries_description'),
            'help' => sprintf($this->t('payplug_apm_countries_help'), $brand),
            'options' => $options,
            'value' => null === $stored ? $proposable : array_values(array_intersect($proposable, $stored)),
            'default' => $proposable,
            'labels' => [
                'search' => $this->t('payplug_apm_countries_search'),
                'select_all' => $this->t('payplug_apm_countries_select_all'),
                'unselect_all' => $this->t('payplug_apm_countries_unselect_all'),
                'all' => $this->t('payplug_apm_countries_all'),
                'selected' => $this->t('payplug_apm_countries_selected'),
                'none' => $this->t('payplug_apm_countries_none'),
                'no_match' => $this->t('payplug_apm_countries_no_match'),
                'remove' => $this->t('payplug_apm_countries_remove'),
            ],
            'warning' => $this->t('payplug_apm_countries_warning'),
        ];

        if (empty($proposable) && null !== $api_countries) {
            $block['empty'] = sprintf($this->t('payplug_apm_countries_empty'), $brand, $this->names($api_countries, $country_names));
        }

        $removed = ApmCountriesSave::removed($stored, $proposable);
        if (!empty($removed)) {
            $block['removed'] = sprintf($this->t('payplug_apm_countries_removed'), $this->names($removed, $country_names));
        }

        return $block;
    }

    /**
     * @param string[] $codes
     * @param array<string, string> $country_names
     */
    private function names(array $codes, array $country_names): string
    {
        $names = [];
        foreach ($codes as $code) {
            $names[] = $country_names[$code] ?? $code;
        }

        return implode(', ', $names);
    }

    private function t(string $key): string
    {
        return (string) ($this->translate)($key);
    }
}
