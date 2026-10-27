<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Helper;

use Payplug\PayplugWoocommerce\Helper\PhoneNumberHelper;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use PHPUnit\Framework\TestCase;

class PhoneNumberHelper_test extends TestCase
{
    public function test_regions_of_a_plain_country_is_the_country_only(): void
    {
        self::assertSame(['DE'], PhoneNumberHelper::getRegions('de'));
    }

    public function test_regions_of_a_french_territory_ends_with_fr(): void
    {
        self::assertSame(['MQ', 'FR'], PhoneNumberHelper::getRegions('MQ'));
    }

    public function test_regions_of_bl_and_mf_try_gp_and_each_other_before_fr(): void
    {
        self::assertSame(['BL', 'GP', 'MF', 'FR'], PhoneNumberHelper::getRegions('BL'));
        self::assertSame(['MF', 'GP', 'BL', 'FR'], PhoneNumberHelper::getRegions('MF'));
    }

    /**
     * @dataProvider french_territory_provider
     */
    public function test_every_french_territory_is_tried_first_then_fr(string $territory): void
    {
        $regions = PhoneNumberHelper::getRegions($territory);

        self::assertSame($territory, $regions[0]);
        self::assertSame('FR', end($regions));
    }

    public function french_territory_provider(): array
    {
        return [['GP'], ['MQ'], ['RE'], ['GF'], ['YT'], ['PM'], ['BL'], ['MF'], ['NC'], ['PF'], ['WF']];
    }

    public function test_regions_of_fr_is_fr_only(): void
    {
        self::assertSame(['FR'], PhoneNumberHelper::getRegions('FR'));
    }

    public function test_regions_of_empty_country_is_empty(): void
    {
        self::assertSame([], PhoneNumberHelper::getRegions(''));
        self::assertSame([], PhoneNumberHelper::getRegions(null));
    }

    public function test_mask_keeps_first_three_and_last_two_characters(): void
    {
        self::assertSame('234****34', PhoneNumberHelper::mask('234234234'));
        self::assertSame('003********79', PhoneNumberHelper::mask('0033609860479'));
    }

    public function test_mask_hides_short_numbers_entirely(): void
    {
        self::assertSame('*****', PhoneNumberHelper::mask('12345'));
        self::assertSame('', PhoneNumberHelper::mask(''));
    }

    /**
     * @dataProvider non_strict_provider
     */
    public function test_valid_number_is_resolved_in_its_own_country_when_region_match_is_not_required(
        string $phone,
        string $country,
        string $e164,
        bool $mobile
    ): void {
        self::assertSame(['e164' => $e164, 'mobile' => $mobile], PhoneNumberHelper::resolve($phone, $country));
        self::assertSame($e164, PhoneNumberHelper::toE164($phone, $country, false));
        self::assertSame($mobile, PhoneNumberHelper::isMobile($phone, $country, false));
    }

    public function non_strict_provider(): array
    {
        return [
            'FR mobile on a DE address' => ['+33612345678', 'DE', '+33612345678', true],
            'FR mobile with 00 prefix on a BE address' => ['0033609860479', 'BE', '+33609860479', true],
            'BE mobile with 00 prefix on a FR address' => ['0032 470 12 34 56', 'FR', '+32470123456', true],
            'metropolitan mobile on a MQ address' => ['07 81 23 45 67', 'MQ', '+33781234567', true],
            'MQ mobile on a MQ address' => ['0696 12 34 56', 'MQ', '+596696123456', true],
            'GP/BL mobile on a BL address' => ['0690 12 34 56', 'BL', '+590690123456', true],
            'GP/MF landline on a MF address' => ['0590 87 12 34', 'MF', '+590590871234', false],
            'YT landline on a YT address' => ['02 69 61 23 45', 'YT', '+262269612345', false],
            'YT mobile on a YT address' => ['0639 01 23 45', 'YT', '+262639012345', true],
            'metropolitan mobile on a YT address' => ['06 12 34 56 78', 'YT', '+33612345678', true],
            'NC mobile on a NC address' => ['75.12.34', 'NC', '+687751234', true],
            'metropolitan mobile on a NC address' => ['06 12 34 56 78', 'NC', '+33612345678', true],
            'PF mobile on a PF address' => ['87 12 34 56', 'PF', '+68987123456', true],
            'metropolitan mobile on a PF address' => ['06 12 34 56 78', 'PF', '+33612345678', true],
            'WF mobile on a WF address' => ['82 12 34', 'WF', '+681821234', true],
            'metropolitan mobile on a WF address' => ['06 12 34 56 78', 'WF', '+33612345678', true],
            'FR mobile on a FR address' => ['06 12 34 56 78', 'FR', '+33612345678', true],
            'lower-case country' => ['06 12 34 56 78', 'fr', '+33612345678', true],
        ];
    }

    /**
     * @dataProvider strict_provider
     */
    public function test_number_must_belong_to_the_address_region_or_fr_for_a_territory_when_region_match_is_required(
        string $phone,
        string $country,
        string $e164,
        bool $mobile
    ): void {
        self::assertSame(['e164' => $e164, 'mobile' => $mobile], PhoneNumberHelper::resolve($phone, $country, true));
        self::assertSame($e164, PhoneNumberHelper::toE164($phone, $country, true));
        self::assertSame($mobile, PhoneNumberHelper::isMobile($phone, $country, true));
    }

    public function strict_provider(): array
    {
        return [
            'FR mobile on a FR address' => ['06 12 34 56 78', 'FR', '+33612345678', true],
            'metropolitan mobile on a MQ address' => ['07 81 23 45 67', 'MQ', '+33781234567', true],
            'MQ mobile on a MQ address' => ['0696 12 34 56', 'MQ', '+596696123456', true],
            'GP/BL mobile on a BL address' => ['0690 12 34 56', 'BL', '+590690123456', true],
            'GP/BL mobile on a MF address' => ['0690 12 34 56', 'MF', '+590690123456', true],
            'metropolitan mobile on a NC address' => ['06 12 34 56 78', 'NC', '+33612345678', true],
            'FR landline on a FR address' => ['0171254015', 'FR', '+33171254015', false],
        ];
    }

    /**
     * @dataProvider foreign_number_provider
     */
    public function test_foreign_number_is_rejected_when_region_match_is_required(string $phone, string $country): void
    {
        self::assertNull(PhoneNumberHelper::resolve($phone, $country, true));
        self::assertNull(PhoneNumberHelper::toE164($phone, $country, true));
        self::assertFalse(PhoneNumberHelper::isMobile($phone, $country, true));
    }

    public function foreign_number_provider(): array
    {
        return [
            'BE mobile on a FR address' => ['+32470123456', 'FR'],
            'FR mobile on a DE address' => ['+33612345678', 'DE'],
            'BE mobile on a MQ address' => ['+32470123456', 'MQ'],
        ];
    }

    /**
     * @dataProvider unusable_number_provider
     *
     * @param mixed $phone
     * @param mixed $country
     */
    public function test_unusable_number_resolves_to_nothing_in_both_modes($phone, $country): void
    {
        foreach ([false, true] as $require_region_match) {
            self::assertNull(PhoneNumberHelper::resolve($phone, $country, $require_region_match));
            self::assertNull(PhoneNumberHelper::toE164($phone, $country, $require_region_match));
            self::assertFalse(PhoneNumberHelper::isMobile($phone, $country, $require_region_match));
        }
    }

    public function unusable_number_provider(): array
    {
        return [
            'invalid IT number' => ['234234234', 'IT'],
            'not a number' => ['not-a-phone', 'FR'],
            'empty phone' => ['', 'FR'],
            'blank phone' => ['   ', 'FR'],
            'null phone' => [null, 'FR'],
            'empty country' => ['0612345678', ''],
            'unknown country' => ['0612345678', 'ZZ'],
        ];
    }

    public function test_unusable_number_is_logged_masked_once(): void
    {
        $spy = GatewayLogSpy::start();
        try {
            PhoneNumberHelper::resolve('234234234', 'IT');
        } finally {
            $spy->stop();
        }

        self::assertCount(1, $spy->entries);
        self::assertSame('error', $spy->entries[0]['level']);
        self::assertStringContainsString('234****34', $spy->entries[0]['message']);
        self::assertStringContainsString('"IT"', $spy->entries[0]['message']);
        self::assertStringNotContainsString('234234234', $spy->entries[0]['message']);
    }

    public function test_unusable_number_on_a_territory_is_logged_once_for_all_regions_tried(): void
    {
        $spy = GatewayLogSpy::start();
        try {
            PhoneNumberHelper::resolve('+32470123456', 'BL', true);
        } finally {
            $spy->stop();
        }

        self::assertCount(1, $spy->entries);
        self::assertStringContainsString('+32*******56', $spy->entries[0]['message']);
        self::assertStringNotContainsString('+32470123456', $spy->entries[0]['message']);
    }

    public function test_missing_phone_or_country_is_not_logged(): void
    {
        $spy = GatewayLogSpy::start();
        try {
            PhoneNumberHelper::resolve('', 'FR');
            PhoneNumberHelper::resolve('0612345678', '');
        } finally {
            $spy->stop();
        }

        self::assertSame([], $spy->entries);
    }
}
