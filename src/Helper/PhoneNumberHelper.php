<?php

namespace Payplug\PayplugWoocommerce\Helper;

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
     * Regions to read a phone number in, in order: the address country, the territories
     * sharing its numbering plan, then FR for a French territory.
     *
     * @param string|null $country
     *
     * @return string[]
     */
    public static function getRegions($country): array
    {
        $country = strtoupper((string) $country);
        if ('' === $country) {
            return [];
        }

        if (!in_array($country, self::FRENCH_NUMBERING_TERRITORIES, true)) {
            return [$country];
        }

        $shared = self::SHARED_NUMBERING_REGIONS[$country] ?? [];

        return array_merge([$country], $shared, ['FR']);
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
        if (!is_string($phone) || '' === trim($phone)) {
            return null;
        }

        $regions = self::getRegions($country);
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
        ), 'error');

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
