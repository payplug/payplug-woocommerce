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
     * @return array<string, string>|null merchant-token, type, amount-selectors (JSON) and locale, null without a token
     */
    public static function attributes(string $page, ScalapayWidgetSettings $settings, string $locale): ?array
    {
        $token = $settings->merchant_token();
        if ('' === $token || !isset(self::TYPES[$page])) {
            return null;
        }

		// to get the sandbox mod, add `'environment' => 'integration'`	to the array bellow
        return [
            'merchant-token' => $token,
            'type' => self::TYPES[$page],
            'amount-selectors' => (string) wp_json_encode(self::filtered_selectors($page, $settings->amount_selectors($page))),
            'locale' => $locale,
        ];
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
