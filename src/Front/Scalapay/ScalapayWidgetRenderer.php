<?php

namespace Payplug\PayplugWoocommerce\Front\Scalapay;

use Payplug\PayplugWoocommerce\Service\ScalapayWidgetSettings;

/**
 * Attributes and markup of the <scalapay-widget> element, printed as Scalapay serves it.
 */
class ScalapayWidgetRenderer
{
    public const TYPES = [
        'product' => 'product',
        'cart' => 'cart',
        'checkout' => 'checkout',
    ];

    /**
     * @return array<string, string>|null merchant-token, type, amount-selectors (JSON), locale and the filtered
     *                                    environment if any, null without a token
     */
    public static function attributes(string $page, ScalapayWidgetSettings $settings, string $locale): ?array
    {
        $token = $settings->merchant_token();
        if ('' === $token || !isset(self::TYPES[$page])) {
            return null;
        }

        $attributes = [
            'merchant-token' => $token,
            'type' => self::TYPES[$page],
            'amount-selectors' => (string) wp_json_encode(self::filtered_selectors($page, $settings->amount_selectors($page))),
            'locale' => $locale,
        ];

        $environment = self::environment($page);
        if ('' !== $environment) {
            $attributes['environment'] = $environment;
        }

        return $attributes;
    }

    /**
     * @param array<string, string>|null $attributes attributes() result
     */
    public static function render(?array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', $name, esc_attr($value));
        }

        return '<scalapay-widget' . $html . '></scalapay-widget>';
    }

    /**
     * @return string '' for none, which also covers 'prod'
     */
    private static function environment(string $page): string
    {
        /**
         * Scalapay environment of the widget, e.g. 'integration' for the Scalapay sandbox.
         *
         * @param string $environment SCALAPAY_WIDGET_ENV, '' for none
         * @param string $page product, cart or checkout
         */
        $environment = apply_filters('payplug_scalapay_widget_environment', static::configured_environment(), $page);

        return is_string($environment) && 'prod' !== $environment ? $environment : '';
    }

    protected static function configured_environment(): string
    {
        return defined('SCALAPAY_WIDGET_ENV') ? (string) SCALAPAY_WIDGET_ENV : '';
    }

    /**
     * @param string[] $selectors
     *
     * @return string[]
     */
    private static function filtered_selectors(string $page, array $selectors): array
    {
        $filtered = ScalapayWidgetSettings::normalise_selectors(apply_filters('payplug_scalapay_widget_amount_selectors', $selectors, $page));

        return empty($filtered) ? $selectors : $filtered;
    }
}
