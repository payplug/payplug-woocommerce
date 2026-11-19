<?php

namespace Payplug\PayplugWoocommerce\Tests\phpunit\src\Gateway\PPRO;

use Payplug\PayplugWoocommerce\Gateway\PPRO\Scalapay;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use PHPUnit\Framework\TestCase;

/**
 * Under the French CCD2 consumer credit rules, the Scalapay name shown at checkout must not
 * suggest a payment in several instalments.
 */
class Scalapay_test extends TestCase
{
    private const INSTALMENT_WORDING = '/\b\d\s*x\b|plusieurs fois|échéances?|instal+ments?|cuotas|plazos|\brate\b/iu';

    private bool $textdomain_was_loaded;

    private bool $textdomain_was_unloaded;

    /** @var object|null */
    private $textdomain_registry;

    /** @var mixed */
    private $original_currency;

    /** @var \Closure */
    private $block_http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->textdomain_was_loaded = is_textdomain_loaded('payplug');
        $this->textdomain_was_unloaded = isset($GLOBALS['l10n_unloaded']['payplug']);
        $this->textdomain_registry = isset($GLOBALS['wp_textdomain_registry']) ? clone $GLOBALS['wp_textdomain_registry'] : null;
        $this->original_currency = get_option('woocommerce_currency');
        update_option('woocommerce_currency', 'EUR');
        $this->block_http = fn () => new \WP_Error('http_request_blocked', 'No HTTP call expected in this test.');
        add_filter('pre_http_request', $this->block_http);
    }

    protected function tearDown(): void
    {
        unload_textdomain('payplug');
        if (null !== $this->textdomain_registry) {
            $GLOBALS['wp_textdomain_registry'] = $this->textdomain_registry;
        }
        if ($this->textdomain_was_loaded) {
            PayplugWoocommerceHelper::load_plugin_textdomain(dirname(PAYPLUG_GATEWAY_PLUGIN_BASENAME) . '/languages');
        }
        if ($this->textdomain_was_unloaded) {
            $GLOBALS['l10n_unloaded']['payplug'] = true;
        } else {
            unset($GLOBALS['l10n_unloaded']['payplug']);
        }
        if (false === $this->original_currency) {
            delete_option('woocommerce_currency');
        } else {
            update_option('woocommerce_currency', $this->original_currency);
        }
        remove_filter('pre_http_request', $this->block_http);
        parent::tearDown();
    }

    public function scalapay_titles(): array
    {
        return [
            'fr_FR' => ['fr_FR', 'Payez avec Scalapay'],
            'en_US' => ['en_US', 'Pay with Scalapay'],
            'es_ES' => ['es_ES', 'Pagar con Scalapay'],
            'it_IT' => ['it_IT', 'Paga con Scalapay'],
        ];
    }

    /**
     * @dataProvider scalapay_titles
     */
    public function test_the_checkout_title_is_the_brand_only_wording(string $locale, string $expected): void
    {
        unload_textdomain('payplug');
        $this->assertTrue(load_textdomain('payplug', $this->languages_dir() . '/payplug-' . $locale . '.mo'));

        $gateway = new Scalapay();

        $this->assertSame($expected, $gateway->get_title());
        $this->assertSame($expected, $gateway->method_title);
        $this->assertSame('', $gateway->description);
    }

    /**
     * @dataProvider scalapay_titles
     */
    public function test_the_po_msgstr_matches_the_expected_wording(string $locale, string $expected): void
    {
        $this->assertSame($expected, $this->po_msgstr($locale, 'pay_with_scalapay'));
    }

    /**
     * @dataProvider scalapay_titles
     */
    public function test_the_title_does_not_mention_instalments(string $locale): void
    {
        $this->assertNotRegExp(self::INSTALMENT_WORDING, $this->po_msgstr($locale, 'pay_with_scalapay'));
    }

    private function po_msgstr(string $locale, string $msgid): string
    {
        $po = file_get_contents($this->languages_dir() . '/payplug-' . $locale . '.po');
        $this->assertSame(1, preg_match('/^msgid "' . preg_quote($msgid, '/') . '"\nmsgstr "(.*)"$/m', $po, $matches));

        return $matches[1];
    }

    private function languages_dir(): string
    {
        return dirname(__DIR__, 5) . '/languages';
    }
}
