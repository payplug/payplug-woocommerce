<?php

namespace Payplug\PayplugWoocommerce\Front\Scalapay;

/**
 * Language of the Scalapay widget, from the language the shop is displayed in.
 */
class ScalapayLocale
{
    public const SUPPORTED = ['en', 'fr', 'it', 'es', 'de', 'pt', 'nl'];

    public const FALLBACK = 'en';

    public static function from_wp_locale(string $wp_locale): string
    {
        $language = strtolower((string) preg_replace('/[_-].*$/', '', $wp_locale));

        return in_array($language, self::SUPPORTED, true) ? $language : self::FALLBACK;
    }

    public static function for_site(): string
    {
        $wp_locale = (string) determine_locale();
        $locale = apply_filters('payplug_scalapay_widget_locale', self::from_wp_locale($wp_locale), $wp_locale);

        return is_string($locale) && '' !== $locale ? $locale : self::FALLBACK;
    }
}
