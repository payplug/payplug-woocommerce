<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Support;

use PayplugUnifiedCore\Validators\DomainAssociationValidator;

/**
 * Synthetic Apple Pay domain-association files: hex-encoded JSON shaped like the real file, with a
 * dummy signature.
 */
final class DomainAssociationFixtures
{
    public static function valid(int $created_on = 1700000000): string
    {
        return self::payload(DomainAssociationValidator::PROD_PSP_ID, $created_on) . "\n";
    }

    public static function validQa(): string
    {
        return self::payload(DomainAssociationValidator::QA_PSP_ID, 1700000000) . "\n";
    }

    public static function htmlChallengePage(): string
    {
        return "<!DOCTYPE html><html>Just a moment...</html>\n";
    }

    private static function payload(string $psp_id, int $created_on): string
    {
        return bin2hex((string) json_encode([
            'pspId' => $psp_id,
            'version' => 1,
            'createdOn' => $created_on,
            'signature' => str_repeat('ab', 64),
        ]));
    }
}
