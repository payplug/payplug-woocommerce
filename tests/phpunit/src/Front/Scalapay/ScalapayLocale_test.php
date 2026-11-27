<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front\Scalapay;

use Payplug\PayplugWoocommerce\Front\Scalapay\ScalapayLocale;
use PHPUnit\Framework\TestCase;

class ScalapayLocale_test extends TestCase
{
    /**
     * @dataProvider wp_locales_provider
     */
    public function test_language_of_the_wordpress_locale(string $wp_locale, string $expected): void
    {
        self::assertSame($expected, ScalapayLocale::from_wp_locale($wp_locale));
    }

    public function wp_locales_provider(): array
    {
        return [
            'french' => ['fr_FR', 'fr'],
            'belgian french' => ['fr_BE', 'fr'],
            'british english' => ['en_GB', 'en'],
            'italian' => ['it_IT', 'it'],
            'spanish' => ['es_ES', 'es'],
            'german formal' => ['de_DE_formal', 'de'],
            'brazilian portuguese' => ['pt_BR', 'pt'],
            'dutch' => ['nl_NL', 'nl'],
            'unsupported language' => ['ja', ScalapayLocale::FALLBACK],
            'three letter language' => ['fil', ScalapayLocale::FALLBACK],
            'upper case' => ['FR_fr', 'fr'],
            'empty' => ['', ScalapayLocale::FALLBACK],
        ];
    }
}
