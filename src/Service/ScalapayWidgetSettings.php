<?php

namespace Payplug\PayplugWoocommerce\Service;

/**
 * Scalapay installment widget settings, read from the raw plugin options (keys may be missing).
 */
class ScalapayWidgetSettings
{
    public const PAGES = ['product', 'cart', 'checkout'];

    public const DEFAULT_SELECTORS = [
        'product' => [
            '.woocommerce-variation-price .woocommerce-Price-amount bdi',
            '.summary .price ins .woocommerce-Price-amount bdi',
            '.summary .price > .woocommerce-Price-amount bdi',
            '.wp-block-woocommerce-product-price ins .woocommerce-Price-amount',
            '.wp-block-woocommerce-product-price .woocommerce-Price-amount',
        ],
        'cart' => [
            '.cart_totals .order-total .woocommerce-Price-amount bdi',
            '.wc-block-cart .wc-block-components-totals-footer-item .wc-block-formatted-money-amount',
        ],
        'checkout' => [
            '.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount bdi',
            '.wc-block-checkout .wc-block-components-totals-footer-item .wc-block-formatted-money-amount',
        ],
    ];

    public const MAX_SELECTORS = 10;

    public const MAX_SELECTOR_LENGTH = 255;

    private const APM = 'scalapay';

    /**
     * @var array woocommerce_payplug_settings
     */
    private $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function is_page_enabled(string $page): bool
    {
        return in_array($page, self::PAGES, true) && (bool) ($this->configuration()['widget_' . $page] ?? true);
    }

    /**
     * @return string[] stored selectors of the page, its default ones when none are usable
     */
    public function amount_selectors(string $page): array
    {
        $stored = self::decode_selectors($this->configuration()['widget_selectors'] ?? null)[$page] ?? null;
        $selectors = is_array($stored) ? self::normalise_selectors($stored) : null;

        return empty($selectors) ? (self::DEFAULT_SELECTORS[$page] ?? []) : $selectors;
    }

    public function merchant_token(): string
    {
        $token = $this->options['payment_methods']['permissions'][self::APM]['merchant_token'] ?? '';

        return is_string($token) ? trim($token) : '';
    }

    /**
     * Live mode, plugin enabled, Scalapay active, page switched on and merchant token known.
     */
    public function is_configured_for(string $page): bool
    {
        return !empty($this->options['enabled'])
            && !empty($this->options['mode'])
            && !empty($this->configuration()['active'])
            && $this->is_page_enabled($page)
            && '' !== $this->merchant_token();
    }

    /**
     * @param array{amount: int, currency: string, country: string}|null $context amount in cents
     */
    public function should_render(string $page, ?array $context): bool
    {
        if (null === $context || !$this->is_configured_for($page)) {
            return false;
        }

        return null === ApmEligibility::reason_not_eligible(
            ApmSettings::config_for($this->options, self::APM),
            (int) ($context['amount'] ?? 0),
            (string) ($context['currency'] ?? ''),
            (string) ($context['country'] ?? '')
        );
    }

    /**
     * Amount of a [min, max] price range (cents) to check with should_render(): the Scalapay
     * minimum when the range contains it, the nearest end of the range otherwise, so that it is
     * eligible exactly when the range meets the Scalapay range.
     */
    public function amount_to_check(int $min, int $max): int
    {
        return max($min, min($max, ApmEligibility::client_restrictions($this->options, self::APM)['min']));
    }

    /**
     * @param mixed $submitted list of selectors, or one selector per line
     *
     * @return string[]|null trimmed unique selectors ([] = default ones), null when invalid
     */
    public static function normalise_selectors($submitted): ?array
    {
        $entries = is_string($submitted) ? preg_split('/\R/', $submitted) : $submitted;
        if (!is_array($entries)) {
            return null;
        }

        $selectors = [];
        foreach ($entries as $entry) {
            $selector = is_string($entry) ? trim($entry) : $entry;
            if ('' !== $selector) {
                $selectors[] = $selector;
            }
        }
        $valid = array_filter($selectors, [self::class, 'is_valid_selector']);

        return count($valid) === count($selectors) && count($selectors) <= self::MAX_SELECTORS
            ? array_values(array_unique($selectors))
            : null;
    }

    /**
     * @param array $configuration payment_methods.configuration.scalapay
     * @param array $data submitted settings, enable_scalapay_widget_<page> switches
     */
    public static function with_posted_switches(array $configuration, array $data): array
    {
        foreach (self::PAGES as $page) {
            if (array_key_exists('enable_scalapay_widget_' . $page, $data)) {
                $configuration['widget_' . $page] = (bool) $data['enable_scalapay_widget_' . $page];
            }
        }

        return $configuration;
    }

    /**
     * @param mixed $stored widget_selectors
     * @param array $data submitted settings, scalapay_widget_<page>_amount_selectors fields
     *
     * @return string|null widget_selectors to store, null when a submitted list is invalid
     */
    public static function merge_posted_selectors($stored, array $data): ?string
    {
        $selectors = self::decode_selectors($stored);
        foreach (self::PAGES as $page) {
            $field = 'scalapay_widget_' . $page . '_amount_selectors';
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $submitted = self::normalise_selectors($data[$field]);
            if (null === $submitted) {
                return null;
            }
            $selectors[$page] = $submitted;
        }

        return (string) json_encode((object) array_filter($selectors), JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param mixed $selector
     */
    private static function is_valid_selector($selector): bool
    {
        return is_string($selector)
            && mb_strlen($selector, 'UTF-8') <= self::MAX_SELECTOR_LENGTH
            && !preg_match('/[\x00-\x1F\x7F]/', $selector);
    }

    private function configuration(): array
    {
        $configuration = $this->options['payment_methods']['configuration'][self::APM] ?? [];

        return is_array($configuration) ? $configuration : [];
    }

    /**
     * @param mixed $stored widget_selectors
     *
     * @return array<string, mixed> page => stored selectors, unchecked
     */
    private static function decode_selectors($stored): array
    {
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return is_array($decoded) ? array_intersect_key($decoded, array_flip(self::PAGES)) : [];
    }
}
