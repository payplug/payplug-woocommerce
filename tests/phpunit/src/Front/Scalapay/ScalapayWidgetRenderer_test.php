<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Front\Scalapay;

use Payplug\PayplugWoocommerce\Front\Scalapay\ScalapayWidgetRenderer;
use Payplug\PayplugWoocommerce\Service\ScalapayWidgetSettings;
use PHPUnit\Framework\TestCase;

class ScalapayWidgetRenderer_test extends TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('payplug_scalapay_widget_amount_selectors');
        parent::tearDown();
    }

    public function test_attributes_of_each_page(): void
    {
        foreach (ScalapayWidgetSettings::PAGES as $page) {
            $attributes = ScalapayWidgetRenderer::attributes($page, $this->settings('tok_test_123'), 'fr');

            self::assertSame(['merchant-token', 'type', 'amount-selectors', 'locale'], array_keys($attributes), $page);
            self::assertSame('tok_test_123', $attributes['merchant-token']);
            self::assertSame($page, $attributes['type']);
            self::assertSame(ScalapayWidgetSettings::DEFAULT_SELECTORS[$page], json_decode($attributes['amount-selectors'], true));
            self::assertSame('fr', $attributes['locale']);
        }
    }

    public function test_no_attributes_without_a_token(): void
    {
        self::assertNull(ScalapayWidgetRenderer::attributes('product', $this->settings(''), 'fr'));
    }

    public function test_no_attributes_for_an_unknown_page(): void
    {
        self::assertNull(ScalapayWidgetRenderer::attributes('category', $this->settings('tok_test_123'), 'fr'));
    }

    public function test_selectors_filter_replaces_the_selectors(): void
    {
        add_filter('payplug_scalapay_widget_amount_selectors', static fn (array $selectors, string $page): array => 'cart' === $page ? ['#my-total'] : $selectors, 10, 2);

        $cart = ScalapayWidgetRenderer::attributes('cart', $this->settings('tok_test_123'), 'fr');
        $product = ScalapayWidgetRenderer::attributes('product', $this->settings('tok_test_123'), 'fr');

        self::assertSame('["#my-total"]', $cart['amount-selectors']);
        self::assertSame(ScalapayWidgetSettings::DEFAULT_SELECTORS['product'], json_decode($product['amount-selectors'], true));
    }

    public function test_invalid_filtered_selectors_keep_the_settings_selectors(): void
    {
        add_filter('payplug_scalapay_widget_amount_selectors', static fn (): string => '');

        $attributes = ScalapayWidgetRenderer::attributes('cart', $this->settings('tok_test_123'), 'fr');

        self::assertSame(ScalapayWidgetSettings::DEFAULT_SELECTORS['cart'], json_decode($attributes['amount-selectors'], true));
    }

    public function test_render_prints_only_the_widget_element(): void
    {
        $html = ScalapayWidgetRenderer::render([
            'merchant-token' => 'tok_test_123',
            'type' => 'product',
            'amount-selectors' => '["#price-container"]',
            'locale' => 'fr',
        ]);

        self::assertSame('<scalapay-widget merchant-token="tok_test_123" type="product" amount-selectors="[&quot;#price-container&quot;]" locale="fr"></scalapay-widget>', $html);
    }

    public function test_render_escapes_every_attribute_and_keeps_the_selectors_json(): void
    {
        $selectors = ['.price > [data-x=\'a\'] bdi', '#total'];
        $html = ScalapayWidgetRenderer::render([
            'merchant-token' => 'tok"><script>alert(1)</script>',
            'type' => 'product',
            'amount-selectors' => (string) json_encode($selectors),
            'locale' => 'fr',
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertSame(1, preg_match('/amount-selectors="([^"]*)"/', $html, $match));
        self::assertSame($selectors, json_decode(html_entity_decode($match[1], ENT_QUOTES), true));
    }

    public function test_render_without_attributes_prints_nothing(): void
    {
        self::assertSame('', ScalapayWidgetRenderer::render(null));
        self::assertSame('', ScalapayWidgetRenderer::render([]));
    }

    private function settings(string $token): ScalapayWidgetSettings
    {
        return new ScalapayWidgetSettings([
            'payment_methods' => ['permissions' => ['scalapay' => ['merchant_token' => $token]]],
        ]);
    }
}
