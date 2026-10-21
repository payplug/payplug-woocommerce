<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src;

use PHPUnit\Framework\TestCase;

class Languages_test extends TestCase
{
    private const KEYS = [
        'payplug_apm_countries_title',
        'payplug_apm_countries_description',
        'payplug_apm_countries_help',
        'payplug_apm_countries_empty',
        'payplug_apm_countries_removed',
        'payplug_apm_countries_warning',
        'payplug_apm_countries_search',
        'payplug_apm_countries_select_all',
        'payplug_apm_countries_unselect_all',
        'payplug_apm_countries_all',
        'payplug_apm_countries_selected',
        'payplug_apm_countries_none',
        'payplug_apm_countries_no_match',
        'payplug_apm_countries_remove',
        'payplug_apm_thresholds_title',
        'payplug_apm_thresholds_description',
        'payplug_apm_thresholds_min',
        'payplug_apm_thresholds_max',
        'payplug_apm_thresholds_error_text',
        'payplug_apm_thresholds_error_maxtext',
        'payplug_apm_thresholds_error_mintext',
        'payplug_apm_amount_out_of_bounds_error',
        'payplug_apm_amount_max_gt_min_error',
        'payplug_apm_amount_invalid_error',
        'payplug_apm_country_invalid_error',
        'payplug_apm_not_eligible_error',
    ];

    private const LOCALES = ['en_US', 'fr_FR', 'es_ES', 'it_IT'];

    private const PM_TEXTS = [
        'fr_FR' => [
            'payplug_apm_countries_help' => 'Seuls les pays activés dans votre boutique et acceptés par %s sont proposés.',
            'payplug_apm_countries_empty' => "%1\$s n'est accepté dans aucun des pays activés dans votre boutique. Pour le proposer à vos clients, activez au moins un de ces pays dans votre boutique : %2\$s.",
            'payplug_apm_countries_removed' => 'Ces pays étaient sélectionnés mais ne sont plus disponibles pour ce moyen de paiement : %s. Ils seront retirés de votre sélection au prochain enregistrement.',
            'payplug_apm_countries_warning' => 'Aucun pays sélectionné : ce moyen de paiement ne sera proposé à aucun client.',
        ],
        'en_US' => [
            'payplug_apm_countries_help' => 'Only the countries enabled in your shop and accepted by %s are offered.',
            'payplug_apm_countries_empty' => '%1$s is not accepted in any of the countries enabled in your shop. To offer it to your customers, enable at least one of these countries in your shop: %2$s.',
            'payplug_apm_countries_removed' => 'These countries were selected but are no longer available for this payment method: %s. They will be removed from your selection the next time you save.',
            'payplug_apm_countries_warning' => 'No country selected: this payment method will not be offered to any customer.',
        ],
    ];

    /**
     * @dataProvider locales_provider
     */
    public function test_every_key_is_translated(string $locale): void
    {
        $entries = self::parse(dirname(__DIR__, 3) . '/languages/payplug-' . $locale . '.po');

        foreach (self::KEYS as $key) {
            self::assertArrayHasKey($key, $entries, $locale . ' ' . $key);
            self::assertNotSame('', $entries[$key], $locale . ' ' . $key);
            self::assertNotSame($key, $entries[$key], $locale . ' ' . $key);
        }
    }

    /**
     * @dataProvider locales_provider
     */
    public function test_placeholders_match_the_english_text(string $locale): void
    {
        $english = self::parse(dirname(__DIR__, 3) . '/languages/payplug-en_US.po');
        $entries = self::parse(dirname(__DIR__, 3) . '/languages/payplug-' . $locale . '.po');

        foreach (self::KEYS as $key) {
            self::assertSame(self::placeholders($english[$key] ?? ''), self::placeholders($entries[$key] ?? ''), $locale . ' ' . $key);
        }
    }

    public function test_pm_validated_texts_are_verbatim(): void
    {
        foreach (self::PM_TEXTS as $locale => $texts) {
            $entries = self::parse(dirname(__DIR__, 3) . '/languages/payplug-' . $locale . '.po');
            foreach ($texts as $key => $text) {
                self::assertSame($text, $entries[$key] ?? null, $locale . ' ' . $key);
            }
        }
    }

    public function test_template_lists_every_key(): void
    {
        $entries = self::parse(dirname(__DIR__, 3) . '/languages/payplug.pot');

        foreach (self::KEYS as $key) {
            self::assertArrayHasKey($key, $entries, $key);
        }
    }

    public function locales_provider(): array
    {
        return array_combine(self::LOCALES, array_map(static fn (string $locale): array => [$locale], self::LOCALES));
    }

    /**
     * @return array<string, string> msgid => msgstr
     */
    private static function parse(string $file): array
    {
        $entries = [];
        $current = null;
        $msgid = '';
        $msgstr = '';
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^msgid "(.*)"$/', $line, $match)) {
                if (null !== $current) {
                    $entries[$msgid] = $msgstr;
                }
                $current = 'msgid';
                $msgid = stripcslashes($match[1]);
                $msgstr = '';
            } elseif (preg_match('/^msgstr "(.*)"$/', $line, $match)) {
                $current = 'msgstr';
                $msgstr = stripcslashes($match[1]);
            } elseif (null !== $current && preg_match('/^"(.*)"$/', $line, $match)) {
                if ('msgid' === $current) {
                    $msgid .= stripcslashes($match[1]);
                } else {
                    $msgstr .= stripcslashes($match[1]);
                }
            }
        }
        if (null !== $current) {
            $entries[$msgid] = $msgstr;
        }

        return $entries;
    }

    /**
     * @return string[]
     */
    private static function placeholders(string $text): array
    {
        preg_match_all('/%(?:\d+\$)?s/', $text, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
