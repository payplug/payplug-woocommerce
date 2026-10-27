<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Helper;

use Payplug\PayplugWoocommerce\Helper\PhoneNumberHelper;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\GatewayLogSpy;
use PHPUnit\Framework\TestCase;

class PhoneNumberHelper_test extends TestCase
{
    private const METROPOLITAN_MOBILE = '06 12 34 56 78';

    public function test_regions_of_a_plain_country_is_the_country_only(): void
    {
        self::assertSame(['DE'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'de'));
        self::assertSame(['DE'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'de', true));
    }

    public function test_regions_of_a_french_territory_end_with_fr_when_region_match_is_required(): void
    {
        self::assertSame(['MQ', 'FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'MQ', true));
    }

    public function test_regions_of_bl_and_mf_try_gp_and_each_other_before_fr_when_region_match_is_required(): void
    {
        self::assertSame(['BL', 'GP', 'MF', 'FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'BL', true));
        self::assertSame(['MF', 'GP', 'BL', 'FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'MF', true));
    }

    public function test_regions_of_a_french_territory_go_straight_to_fr_for_a_metropolitan_number_when_region_match_is_not_required(): void
    {
        self::assertSame(['MQ', 'FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'MQ'));
        self::assertSame(['BL', 'GP', 'MF', 'FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'BL'));
        self::assertSame(['NC', 'FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'NC'));
    }

    public function test_regions_of_a_french_territory_try_every_overseas_region_for_an_overseas_number_when_region_match_is_not_required(): void
    {
        self::assertSame(
            ['GF', 'GP', 'MQ', 'RE', 'YT', 'PM', 'BL', 'MF'],
            PhoneNumberHelper::getRegions('0596 60 12 34', 'GF')
        );
        self::assertSame(
            ['BL', 'GP', 'MF', 'MQ', 'GF', 'RE', 'YT', 'PM'],
            PhoneNumberHelper::getRegions('0596 60 12 34', 'BL')
        );
        self::assertSame(
            ['NC', 'GP', 'MQ', 'GF', 'RE', 'YT', 'PM', 'BL', 'MF'],
            PhoneNumberHelper::getRegions('0262 12 34 56', 'NC')
        );
    }

    public function test_regions_of_a_french_territory_are_its_own_for_an_overseas_number_when_region_match_is_required(): void
    {
        self::assertSame(['GF'], PhoneNumberHelper::getRegions('0596 60 12 34', 'GF', true));
        self::assertSame(['BL', 'GP', 'MF'], PhoneNumberHelper::getRegions('0590 87 12 34', 'BL', true));
    }

    /**
     * @dataProvider french_territory_provider
     */
    public function test_every_french_territory_is_tried_first_then_fr(string $territory): void
    {
        foreach ([false, true] as $require_region_match) {
            $regions = PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, $territory, $require_region_match);

            self::assertSame($territory, $regions[0]);
            self::assertSame('FR', end($regions));
        }
    }

    public function french_territory_provider(): array
    {
        return [['GP'], ['MQ'], ['RE'], ['GF'], ['YT'], ['PM'], ['BL'], ['MF'], ['NC'], ['PF'], ['WF']];
    }

    /**
     * @dataProvider overseas_national_number_provider
     */
    public function test_fr_is_not_tried_for_a_national_number_with_an_overseas_prefix(string $phone): void
    {
        foreach ([false, true] as $require_region_match) {
            self::assertNotContains('FR', PhoneNumberHelper::getRegions($phone, 'GP', $require_region_match));
        }
    }

    public function overseas_national_number_provider(): array
    {
        return [
            'RE landline' => ['0262 12 34 56'],
            'RE landline 0263' => ['0263 01 23 45'],
            'YT landline' => ['0269 61 12 34'],
            'PM' => ['0508 41 23 45'],
            'GP landline' => ['0590 87 12 34'],
            'GF landline' => ['0594 12 34 56'],
            'MQ landline' => ['0596 60 12 34'],
            'MQ landline with dots' => ['05.96.60.12.34'],
            'MQ landline with dashes' => ['0596-60-12-34'],
            'MQ landline without the leading 0' => ['596 60 12 34'],
            'YT mobile' => ['0639 01 23 45'],
            'GP mobile 0690' => ['0690 12 34 56'],
            'GP mobile 0691' => ['0691 22 34 56'],
            'RE mobile 0692' => ['0692 12 34 56'],
            'RE mobile 0693' => ['0693 12 34 56'],
            'GF mobile' => ['0694 12 34 56'],
            'MQ mobile 0696' => ['0696 12 34 56'],
            'MQ mobile 0697' => ['0697 27 34 56'],
        ];
    }

    /**
     * @dataProvider number_read_in_fr_provider
     */
    public function test_fr_is_tried_for_a_metropolitan_or_international_number(string $phone): void
    {
        foreach ([false, true] as $require_region_match) {
            self::assertContains('FR', PhoneNumberHelper::getRegions($phone, 'GP', $require_region_match));
        }
    }

    public function number_read_in_fr_provider(): array
    {
        return [
            'metropolitan mobile' => [self::METROPOLITAN_MOBILE],
            'metropolitan mobile 0695' => ['06 95 12 34 56'],
            'metropolitan mobile 0698' => ['06 98 12 34 56'],
            'metropolitan mobile 0699' => ['06 99 12 34 56'],
            'metropolitan landline 05' => ['05 56 12 34 56'],
            'metropolitan landline 02' => ['02 99 12 34 56'],
            'overseas number written with +33' => ['+33 596 60 12 34'],
            'overseas number written with 0033' => ['0033 596 60 12 34'],
        ];
    }

    public function test_regions_of_fr_is_fr_only(): void
    {
        self::assertSame(['FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'FR'));
        self::assertSame(['FR'], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, 'FR', true));
    }

    public function test_regions_of_empty_country_is_empty(): void
    {
        self::assertSame([], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, ''));
        self::assertSame([], PhoneNumberHelper::getRegions(self::METROPOLITAN_MOBILE, null));
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
            'MQ landline on a GF address' => ['0596 60 12 34', 'GF', '+596596601234', false],
            'MQ landline without the leading 0 on a GF address' => ['596 60 12 34', 'GF', '+596596601234', false],
            'YT landline on a RE address' => ['0269 61 12 34', 'RE', '+262269611234', false],
            'RE landline on a GP address' => ['0263 01 23 45', 'GP', '+262263012345', false],
            'metropolitan landline 05 on a GP address' => ['05 56 12 34 56', 'GP', '+33556123456', false],
            'metropolitan landline 02 on a RE address' => ['02 99 12 34 56', 'RE', '+33299123456', false],
            'metropolitan mobile 0695 on a MQ address' => ['06 95 12 34 56', 'MQ', '+33695123456', true],
            'overseas number written with +33 on a GF address' => ['+33 596 60 12 34', 'GF', '+33596601234', false],
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
            'metropolitan landline 05 on a GP address' => ['05 56 12 34 56', 'GP', '+33556123456', false],
            'metropolitan landline 02 on a RE address' => ['02 99 12 34 56', 'RE', '+33299123456', false],
            'metropolitan mobile 0695 on a MQ address' => ['06 95 12 34 56', 'MQ', '+33695123456', true],
            'overseas number written with +33 on a GF address' => ['+33 596 60 12 34', 'GF', '+33596601234', false],
        ];
    }

    /**
     * @dataProvider metropolitan_number_known_to_another_overseas_region_provider
     */
    public function test_metropolitan_number_known_to_another_overseas_region_is_read_in_fr(
        string $phone,
        string $country,
        string $e164
    ): void {
        foreach ([false, true] as $require_region_match) {
            self::assertSame(
                ['e164' => $e164, 'mobile' => false],
                PhoneNumberHelper::resolve($phone, $country, $require_region_match)
            );
        }
    }

    public function metropolitan_number_known_to_another_overseas_region_provider(): array
    {
        return [
            '0976 number known to GP on a MQ address' => ['0976 01 23 45', 'MQ', '+33976012345'],
            '0976 number known to GF on a GP address' => ['0976 21 23 45', 'GP', '+33976212345'],
            '0939 number known to GP on a GF address' => ['0939 51 23 45', 'GF', '+33939512345'],
            '0939 number known to GP and GF on a RE address' => ['0939 61 23 45', 'RE', '+33939612345'],
            '0947 number known to GP on a GF address' => ['0947 51 23 45', 'GF', '+33947512345'],
            '08 number known to RE on a MQ address' => ['0899 12 34 56', 'MQ', '+33899123456'],
            '08 number known to RE on a GP address' => ['0806 12 34 56', 'GP', '+33806123456'],
            '08 number known to every overseas region on a NC address' => ['0800 12 34 56', 'NC', '+33800123456'],
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
            'MQ landline on a GF address' => ['0596 60 12 34', 'GF'],
            'YT landline on a RE address' => ['0269 61 12 34', 'RE'],
            'RE landline on a GP address' => ['0263 01 23 45', 'GP'],
        ];
    }

    /**
     * @dataProvider overseas_number_unknown_to_its_region_provider
     */
    public function test_overseas_number_unknown_to_its_region_is_not_read_as_a_metropolitan_number(string $phone, string $country): void
    {
        foreach ([false, true] as $require_region_match) {
            self::assertNull(PhoneNumberHelper::resolve($phone, $country, $require_region_match));
        }
    }

    public function overseas_number_unknown_to_its_region_provider(): array
    {
        return [
            'MQ landline range unknown to libphonenumber on a GP address' => ['0596 12 34 56', 'GP'],
            'PM number in 10-digit form on a GP address' => ['0508 41 23 45', 'GP'],
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
        self::assertSame('warning', $spy->entries[0]['level']);
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
