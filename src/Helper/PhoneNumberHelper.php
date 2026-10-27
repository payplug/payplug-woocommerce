<?php

namespace Payplug\PayplugWoocommerce\Helper;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use PayplugUnifiedCore\Exceptions\InvalidPhoneNumberException;
use PayplugUnifiedCore\Utilities\Helpers\PhoneHelper;

final class PhoneNumberHelper
{
    /**
     * Territories whose shoppers may hold a metropolitan French number.
     */
    public const FRENCH_NUMBERING_TERRITORIES = ['GP', 'MQ', 'RE', 'GF', 'YT', 'PM', 'BL', 'MF', 'NC', 'PF', 'WF'];

    /**
     * Territories sharing the +590 numbering plan, which libphonenumber assigns to GP or BL.
     */
    public const SHARED_NUMBERING_REGIONS = [
        'BL' => ['GP', 'MF'],
        'MF' => ['GP', 'BL'],
    ];

    /**
     * French overseas regions with their own country code, tried in this order after the address
     * territory for a national number with an overseas prefix that does not have to belong to it.
     */
    public const FRENCH_OVERSEAS_REGIONS = ['GP', 'MQ', 'GF', 'RE', 'YT', 'PM', 'BL', 'MF'];

    /**
     * National number, with or without its leading 0, starting with an overseas prefix that FR
     * would read as a metropolitan number: RE (262, 263) and YT (269) landlines, PM (508),
     * GP/BL/MF (590), GF (594) and MQ (596) landlines, YT (639) and overseas (690 to 694, 696, 697)
     * mobiles. A number written in international form (+ or 00) is not a national number.
     */
    private const OVERSEAS_NATIONAL_NUMBER_PATTERN = '/^(?!\+|00)0?(?:26[239]|508|59[046]|639|69[0-467])/';

    /**
     * Regions to read a phone number in, in order: the address country and the territories sharing
     * its numbering plan, then for a French territory either FR, or for a national number with an
     * overseas prefix the other French overseas regions (only when the number does not have to
     * belong to the address region).
     *
     * @param string $phone
     * @param string|null $country address country
     * @param bool $requireRegionMatch
     *
     * @return string[]
     */
    public static function getRegions($phone, $country, $requireRegionMatch = false): array
    {
        $country = strtoupper((string) $country);
        if ('' === $country) {
            return [];
        }

        if (!in_array($country, self::FRENCH_NUMBERING_TERRITORIES, true)) {
            return [$country];
        }

        $regions = array_merge([$country], self::SHARED_NUMBERING_REGIONS[$country] ?? []);
        $digits = (string) preg_replace('/[^\d+]/', '', (string) $phone);
        if (1 !== preg_match(self::OVERSEAS_NATIONAL_NUMBER_PATTERN, $digits)) {
            $regions[] = 'FR';
        } elseif (!$requireRegionMatch) {
            $regions = array_merge($regions, self::FRENCH_OVERSEAS_REGIONS);
        }

        return array_values(array_unique($regions));
    }

    /**
     * Formats a phone number in E.164 and tells whether it is a mobile, reading it in each
     * region of getRegions() until one accepts it.
     *
     * @param string|null $phone
     * @param string|null $country address country
     * @param bool $requireRegionMatch true: the number must belong to one of the regions tried
     *
     * @return array{e164: string, mobile: bool}|null null when the number is missing or unusable
     */
    public static function resolve($phone, $country, $requireRegionMatch = false): ?array
    {
        $regions = is_string($phone) && '' !== trim($phone) ? self::getRegions($phone, $country, $requireRegionMatch) : [];
        if ([] === $regions) {
            return null;
        }

        $first_error = null;
        foreach ($regions as $region) {
            try {
                $e164 = PhoneHelper::toE164($phone, $region, (bool) $requireRegionMatch);

                return [
                    'e164' => $e164,
                    'mobile' => PhoneHelper::isMobile($phone, $region, (bool) $requireRegionMatch),
                ];
            } catch (InvalidPhoneNumberException $e) {
                $first_error = $first_error ?? $e;
            }
        }

        PayplugGateway::log(sprintf(
            'PhoneNumberHelper - Phone number "%s" could not be used for country "%s": %s',
            self::mask($phone),
            $country,
            $first_error->getMessage()
        ), 'warning');

        return null;
    }

    /**
     * @param string|null $phone
     * @param string|null $country
     * @param bool $requireRegionMatch
     *
     * @return string|null the E.164 number, null when the number is missing or unusable
     */
    public static function toE164($phone, $country, $requireRegionMatch = false): ?string
    {
        $resolved = self::resolve($phone, $country, $requireRegionMatch);

        return null === $resolved ? null : $resolved['e164'];
    }

    /**
     * @param string|null $phone
     * @param string|null $country
     * @param bool $requireRegionMatch
     *
     * @return bool false when the number is not a mobile or is missing or unusable
     */
    public static function isMobile($phone, $country, $requireRegionMatch = false): bool
    {
        $resolved = self::resolve($phone, $country, $requireRegionMatch);

        return null !== $resolved && $resolved['mobile'];
    }

    /**
     * @param string $phone
     *
     * @return string the phone number with all but its first 3 and last 2 characters hidden
     */
    public static function mask($phone): string
    {
        $phone = (string) $phone;
        $length = strlen($phone);
        if ($length <= 5) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3) . str_repeat('*', $length - 5) . substr($phone, -2);
    }
}
