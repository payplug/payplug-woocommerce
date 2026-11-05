<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Admin\Vue;

use Payplug\PayplugWoocommerce\Admin\Vue\ApmRestrictionsBlock;
use PHPUnit\Framework\TestCase;

class ApmRestrictionsBlock_test extends TestCase
{
    private const NAMES = [
        'BE' => 'Belgium',
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
        'NL' => 'Netherlands',
        'US' => 'United States (US)',
    ];

    public function test_thresholds(): void
    {
        $block = $this->builder()->thresholds('bancontact', ['min' => 50, 'max' => 2000000], ['min' => 30, 'max' => 2000000]);

        self::assertSame('thresholds', $block['name']);
        self::assertFalse($block['switch']);
        self::assertSame('[payplug_apm_thresholds_title]', $block['title']);
        self::assertSame('[payplug_apm_thresholds_description]', $block['descriptions']['description']);
        self::assertSame('[and]', $block['descriptions']['inter']);
        self::assertTrue($block['descriptions']['decimal']);
        self::assertTrue($block['descriptions']['strict_range']);
        self::assertSame([
            'name' => 'bancontact_min_amounts',
            'label' => '[payplug_apm_thresholds_min]',
            'value' => 0.5,
            'placeholder' => 0.3,
            'default' => 0.3,
        ], $block['descriptions']['min_amount']);
        self::assertSame([
            'name' => 'bancontact_max_amounts',
            'label' => '[payplug_apm_thresholds_max]',
            'value' => 20000,
            'placeholder' => 20000,
            'default' => 20000,
        ], $block['descriptions']['max_amount']);
        self::assertSame([
            'text' => '[payplug_apm_thresholds_error_text 0.30 20000]',
            'maxtext' => '[payplug_apm_thresholds_error_maxtext]',
            'mintext' => '[payplug_apm_thresholds_error_mintext]',
        ], $block['descriptions']['error']);
        self::assertArrayNotHasKey('image_url', $block);
    }

    public function test_countries_without_stored_restriction_selects_every_proposable_country(): void
    {
        $block = $this->builder()->countries('satispay', ['IT', 'FR', 'NL'], null, ['BE', 'FR', 'IT'], self::NAMES);

        self::assertSame('satispay_countries', $block['name']);
        self::assertSame('[payplug_apm_countries_title]', $block['title']);
        self::assertSame('[payplug_apm_countries_description]', $block['description']);
        self::assertSame('[payplug_apm_countries_help Satispay]', $block['help']);
        self::assertSame('[payplug_apm_countries_warning]', $block['warning']);
        self::assertSame([['value' => 'FR', 'label' => 'France'], ['value' => 'IT', 'label' => 'Italy']], $block['options']);
        self::assertSame(['FR', 'IT'], $block['value']);
        self::assertSame(['FR', 'IT'], $block['default']);
        self::assertArrayNotHasKey('empty', $block);
        self::assertArrayNotHasKey('removed', $block);
    }

    public function test_countries_labels(): void
    {
        $labels = $this->builder()->countries('wero', null, null, ['BE'], self::NAMES)['labels'];

        self::assertSame([
            'search' => '[payplug_apm_countries_search]',
            'select_all' => '[payplug_apm_countries_select_all]',
            'unselect_all' => '[payplug_apm_countries_unselect_all]',
            'all' => '[payplug_apm_countries_all]',
            'selected' => '[payplug_apm_countries_selected]',
            'none' => '[payplug_apm_countries_none]',
            'no_match' => '[payplug_apm_countries_no_match]',
            'remove' => '[payplug_apm_countries_remove]',
        ], $labels);
    }

    public function test_stored_restriction_is_the_value(): void
    {
        $block = $this->builder()->countries('satispay', ['IT', 'FR'], ['IT'], ['BE', 'FR', 'IT'], self::NAMES);

        self::assertSame(['IT'], $block['value']);
        self::assertSame(['FR', 'IT'], $block['default']);
    }

    public function test_stored_empty_selection_is_kept(): void
    {
        self::assertSame([], $this->builder()->countries('satispay', ['IT', 'FR'], [], ['FR', 'IT'], self::NAMES)['value']);
    }

    public function test_api_all_proposes_every_shop_country(): void
    {
        $block = $this->builder()->countries('bancontact', null, null, ['US', 'BE'], self::NAMES);

        self::assertSame([['value' => 'US', 'label' => 'United States (US)'], ['value' => 'BE', 'label' => 'Belgium']], $block['options']);
        self::assertSame('[payplug_apm_countries_help Bancontact]', $block['help']);
    }

    public function test_no_proposable_country_sends_the_empty_message(): void
    {
        $block = $this->builder()->countries('ideal', ['NL', 'XK'], null, ['BE', 'FR'], self::NAMES);

        self::assertSame([], $block['options']);
        self::assertSame([], $block['value']);
        self::assertSame('[payplug_apm_countries_empty iDEAL Netherlands, XK]', $block['empty']);
        self::assertSame('[payplug_apm_countries_help iDEAL]', $block['help']);
    }

    public function test_stored_countries_no_longer_proposable_are_announced(): void
    {
        $block = $this->builder()->countries('satispay', ['IT', 'FR', 'DE'], ['IT', 'DE'], ['BE', 'FR', 'IT'], self::NAMES);

        self::assertSame(['IT'], $block['value']);
        self::assertSame('[payplug_apm_countries_removed Germany]', $block['removed']);
    }

    public function test_brand_names_are_not_translated(): void
    {
        $expected = [
            'bancontact' => 'Bancontact',
            'ideal' => 'iDEAL',
            'mybank' => 'MyBank',
            'satispay' => 'Satispay',
            'wero' => 'Wero',
            'bizum' => 'Bizum',
            'scalapay' => 'Scalapay',
        ];
        foreach ($expected as $apm => $brand) {
            self::assertSame('[payplug_apm_countries_help ' . $brand . ']', $this->builder()->countries($apm, null, null, ['FR'], self::NAMES)['help']);
        }
    }

    private function builder(): ApmRestrictionsBlock
    {
        $placeholders = [
            'payplug_apm_thresholds_error_text' => ' %s %s',
            'payplug_apm_countries_help' => ' %s',
            'payplug_apm_countries_removed' => ' %s',
            'payplug_apm_countries_empty' => ' %1$s %2$s',
        ];

        return new ApmRestrictionsBlock(static fn (string $key): string => '[' . $key . ($placeholders[$key] ?? '') . ']');
    }
}
