<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Service;

use Payplug\PayplugWoocommerce\Service\ScalapayWidgetSettings;
use Payplug\PayplugWoocommerce\Tests\phpunit\src\Support\ScalapayWidgetFixtures;
use PHPUnit\Framework\TestCase;

class ScalapayWidgetSettings_test extends TestCase
{
    private const ELIGIBLE = ['amount' => 10000, 'currency' => 'EUR', 'country' => 'FR'];

    public function test_every_page_is_enabled_when_nothing_is_stored(): void
    {
        $settings = new ScalapayWidgetSettings([]);

        foreach (ScalapayWidgetSettings::PAGES as $page) {
            self::assertTrue($settings->is_page_enabled($page), $page);
        }
    }

    public function test_a_page_switched_off_is_disabled_and_the_others_stay_enabled(): void
    {
        $settings = new ScalapayWidgetSettings($this->options(['widget_cart' => false]));

        self::assertFalse($settings->is_page_enabled('cart'));
        self::assertTrue($settings->is_page_enabled('product'));
        self::assertTrue($settings->is_page_enabled('checkout'));
    }

    public function test_an_unknown_page_is_disabled_and_has_no_selectors(): void
    {
        $settings = new ScalapayWidgetSettings($this->options([]));

        self::assertFalse($settings->is_page_enabled('category'));
        self::assertSame([], $settings->amount_selectors('category'));
        self::assertFalse($settings->should_render('category', self::ELIGIBLE));
    }

    public function test_default_selectors_when_nothing_is_stored(): void
    {
        $settings = new ScalapayWidgetSettings([]);

        foreach (ScalapayWidgetSettings::PAGES as $page) {
            self::assertSame(ScalapayWidgetSettings::DEFAULT_SELECTORS[$page], $settings->amount_selectors($page), $page);
            self::assertNotEmpty(ScalapayWidgetSettings::DEFAULT_SELECTORS[$page], $page);
        }
    }

    public function test_stored_selectors_override_their_page_only(): void
    {
        $settings = new ScalapayWidgetSettings($this->options(['widget_selectors' => '{"product":[" #price-container "]}']));

        self::assertSame(['#price-container'], $settings->amount_selectors('product'));
        self::assertSame(ScalapayWidgetSettings::DEFAULT_SELECTORS['cart'], $settings->amount_selectors('cart'));
    }

    /**
     * @dataProvider unusable_stored_selectors_provider
     *
     * @param mixed $stored
     */
    public function test_unusable_stored_selectors_fall_back_to_the_defaults($stored): void
    {
        $settings = new ScalapayWidgetSettings($this->options(['widget_selectors' => $stored]));

        self::assertSame(ScalapayWidgetSettings::DEFAULT_SELECTORS['product'], $settings->amount_selectors('product'));
    }

    public function unusable_stored_selectors_provider(): array
    {
        return [
            'malformed json' => ['{bad'],
            'empty list' => ['{"product":[]}'],
            'blank entries only' => ['{"product":["  "]}'],
            'not a list of strings' => ['{"product":[12]}'],
            'page is a string' => ['{"product":"#price"}'],
            'not a string' => [['product' => ['#price']]],
        ];
    }

    public function test_merchant_token_is_trimmed(): void
    {
        $settings = new ScalapayWidgetSettings($this->options([], ' tok_test_123 '));

        self::assertSame('tok_test_123', $settings->merchant_token());
    }

    /**
     * @dataProvider missing_token_provider
     *
     * @param mixed $permission
     */
    public function test_missing_or_malformed_token_is_empty($permission): void
    {
        $options = $this->options([]);
        $options['payment_methods']['permissions']['scalapay'] = $permission;

        self::assertSame('', (new ScalapayWidgetSettings($options))->merchant_token());
    }

    public function missing_token_provider(): array
    {
        return [
            'no key' => [['enabled' => true]],
            'null' => [['merchant_token' => null]],
            'array' => [['merchant_token' => ['tok']]],
            'permission not an array' => ['tok_test_123'],
        ];
    }

    public function test_configured_for_a_page_when_live_active_enabled_and_tokenised(): void
    {
        $settings = new ScalapayWidgetSettings($this->options([]));

        foreach (ScalapayWidgetSettings::PAGES as $page) {
            self::assertTrue($settings->is_configured_for($page), $page);
        }
    }

    /**
     * @dataProvider not_configured_provider
     */
    public function test_not_configured(array $options): void
    {
        self::assertFalse((new ScalapayWidgetSettings($options))->is_configured_for('product'));
    }

    public function not_configured_provider(): array
    {
        $options = $this->options([]);

        return [
            'test mode' => [array_merge($options, ['mode' => false])],
            'plugin disabled' => [array_merge($options, ['enabled' => false])],
            'scalapay inactive' => [$this->options(['active' => false])],
            'page switched off' => [$this->options(['widget_product' => false])],
            'no token' => [$this->options([], '')],
            'no options' => [[]],
        ];
    }

    public function test_renders_for_an_eligible_context(): void
    {
        self::assertTrue((new ScalapayWidgetSettings($this->options([])))->should_render('product', self::ELIGIBLE));
    }

    /**
     * @dataProvider ineligible_context_provider
     */
    public function test_does_not_render_for_an_ineligible_context(?array $context): void
    {
        self::assertFalse((new ScalapayWidgetSettings($this->options([])))->should_render('cart', $context));
    }

    public function ineligible_context_provider(): array
    {
        return [
            'no cart' => [null],
            'below min' => [['amount' => 499, 'currency' => 'EUR', 'country' => 'FR']],
            'above max' => [['amount' => 200001, 'currency' => 'EUR', 'country' => 'FR']],
            'country not accepted' => [['amount' => 10000, 'currency' => 'EUR', 'country' => 'DE']],
            'not euro' => [['amount' => 10000, 'currency' => 'USD', 'country' => 'FR']],
            'no country' => [['amount' => 10000, 'currency' => 'EUR', 'country' => '']],
        ];
    }

    public function test_merchant_range_narrows_the_api_range(): void
    {
        $settings = new ScalapayWidgetSettings($this->options(['custom_amounts' => '{"min":20000}']));

        self::assertFalse($settings->should_render('product', self::ELIGIBLE));
        self::assertTrue($settings->should_render('product', ['amount' => 20000] + self::ELIGIBLE));
    }

    public function test_does_not_render_without_api_limits(): void
    {
        $options = $this->options([]);
        $options['payment_methods']['permissions']['scalapay']['enabled'] = false;

        self::assertFalse((new ScalapayWidgetSettings($options))->should_render('product', self::ELIGIBLE));
    }

    public function test_does_not_render_when_not_configured_even_if_eligible(): void
    {
        self::assertFalse((new ScalapayWidgetSettings($this->options([], '')))->should_render('product', self::ELIGIBLE));
    }

    /**
     * @dataProvider valid_submitted_selectors_provider
     *
     * @param mixed $submitted
     */
    public function test_normalise_selectors($submitted, array $expected): void
    {
        self::assertSame($expected, ScalapayWidgetSettings::normalise_selectors($submitted));
    }

    public function valid_submitted_selectors_provider(): array
    {
        return [
            'list' => [['#price', ' .summary .price bdi '], ['#price', '.summary .price bdi']],
            'one per line' => ["#price\r\n\n.total bdi\n", ['#price', '.total bdi']],
            'duplicates' => [['#price', '#price'], ['#price']],
            'empty string: defaults' => ['', []],
            'empty list: defaults' => [[], []],
            'child combinator and quotes' => [['.price > [data-x=\'a\'] bdi'], ['.price > [data-x=\'a\'] bdi']],
            'longest, in multibyte characters' => [[str_repeat('é', ScalapayWidgetSettings::MAX_SELECTOR_LENGTH)], [str_repeat('é', ScalapayWidgetSettings::MAX_SELECTOR_LENGTH)]],
        ];
    }

    /**
     * @dataProvider invalid_submitted_selectors_provider
     *
     * @param mixed $submitted
     */
    public function test_invalid_selectors_are_rejected($submitted): void
    {
        self::assertNull(ScalapayWidgetSettings::normalise_selectors($submitted));
    }

    public function invalid_submitted_selectors_provider(): array
    {
        return [
            'not a list' => [12],
            'null' => [null],
            'non string entry' => [['#price', 12]],
            'nested list' => [[['#price']]],
            'control character' => [["#price\t.amount"]],
            'too long' => [[str_repeat('a', ScalapayWidgetSettings::MAX_SELECTOR_LENGTH + 1)]],
            'too long, in multibyte characters' => [[str_repeat('é', ScalapayWidgetSettings::MAX_SELECTOR_LENGTH + 1)]],
            'too many' => [array_map(static fn (int $i): string => '#p' . $i, range(1, ScalapayWidgetSettings::MAX_SELECTORS + 1))],
        ];
    }

    public function test_posted_switches_are_saved_and_absent_ones_kept(): void
    {
        $configuration = ['active' => true, 'widget_product' => true, 'widget_cart' => true];

        $saved = ScalapayWidgetSettings::with_posted_switches($configuration, [
            'enable_scalapay_widget_product' => false,
            'enable_scalapay_widget_checkout' => true,
        ]);

        self::assertSame(['active' => true, 'widget_product' => false, 'widget_cart' => true, 'widget_checkout' => true], $saved);
    }

    public function test_posted_selectors_replace_their_page_and_keep_the_others(): void
    {
        $merged = ScalapayWidgetSettings::merge_posted_selectors('{"cart":["#total"],"product":["#old"]}', [
            'scalapay_widget_product_amount_selectors' => "#price\n#price-sale",
        ]);

        self::assertSame(['cart' => ['#total'], 'product' => ['#price', '#price-sale']], json_decode($merged, true));
    }

    public function test_posted_empty_selectors_restore_the_defaults(): void
    {
        $merged = ScalapayWidgetSettings::merge_posted_selectors('{"product":["#old"]}', ['scalapay_widget_product_amount_selectors' => '']);

        self::assertSame('{}', $merged);
    }

    public function test_nothing_posted_keeps_the_stored_selectors(): void
    {
        self::assertSame(['cart' => ['#total']], json_decode(ScalapayWidgetSettings::merge_posted_selectors('{"cart":["#total"]}', []), true));
        self::assertSame('{}', ScalapayWidgetSettings::merge_posted_selectors('{bad', []));
    }

    public function test_invalid_posted_selectors_are_refused(): void
    {
        self::assertNull(ScalapayWidgetSettings::merge_posted_selectors('{"cart":["#total"]}', [
            'scalapay_widget_product_amount_selectors' => '#price',
            'scalapay_widget_cart_amount_selectors' => [12],
        ]));
    }

    /**
     * @dataProvider price_range_provider
     */
    public function test_amount_to_check_of_a_price_range(int $min, int $max, int $expected, bool $renders): void
    {
        $settings = new ScalapayWidgetSettings($this->options([]));

        $amount = $settings->amount_to_check($min, $max);

        self::assertSame($expected, $amount);
        self::assertSame($renders, $settings->should_render('product', ['amount' => $amount] + self::ELIGIBLE));
    }

    public function price_range_provider(): array
    {
        return [
            'single price inside' => [10000, 10000, 10000, true],
            'single price below min' => [499, 499, 499, false],
            'straddling the min' => [300, 1000, 500, true],
            'straddling the max' => [150000, 300000, 150000, true],
            'containing the whole range' => [100, 300000, 500, true],
            'fully below' => [100, 499, 499, false],
            'fully above' => [200001, 300000, 200001, false],
            'no price' => [0, 0, 0, false],
        ];
    }

    public function test_amount_to_check_follows_the_merchant_minimum(): void
    {
        $settings = new ScalapayWidgetSettings($this->options(['custom_amounts' => '{"min":1000}']));

        self::assertSame(1000, $settings->amount_to_check(300, 5000));
        self::assertSame(900, $settings->amount_to_check(300, 900));
        self::assertFalse($settings->should_render('product', ['amount' => 900] + self::ELIGIBLE));
    }

    private function options(array $configuration, string $token = ScalapayWidgetFixtures::TOKEN): array
    {
        return ScalapayWidgetFixtures::options($configuration, $token);
    }
}
