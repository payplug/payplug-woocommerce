<?php

namespace Payplug\PayplugWoocommerce\Front\Scalapay;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Service\ApmCheckoutContext;
use Payplug\PayplugWoocommerce\Service\ScalapayWidgetSettings;

/**
 * Scalapay installment widget on the product page and the classic cart, at the places of the
 * Oney badge, and its script on every page that can show it.
 */
class ScalapayWidgetDisplay
{
    public const SCRIPT_HANDLE = 'payplug-scalapay-widget';

    public const REMOUNT_HANDLE = 'payplug-scalapay-widget-remount';

    /**
     * Style without a file, owned by the plugin, that carries the inline cart CSS whatever the
     * theme does with the WooCommerce styles.
     */
    public const STYLE_HANDLE = 'payplug-scalapay-widget';

    public const CART_CELL_CLASS = 'payplug-scalapay-widget-cell';

    /**
     * The cart totals table is responsive: on small screens WooCommerce prints the data-title of
     * each cell, followed by ": ", before its content. The widget cell has no title.
     */
    private const CART_CELL_CSS = '.woocommerce table.shop_table_responsive tr td.' . self::CART_CELL_CLASS . '::before,'
        . '.woocommerce-page table.shop_table_responsive tr td.' . self::CART_CELL_CLASS . '::before{display:none}';

    private const SUBSCRIPTION_TYPES = ['subscription', 'downloadable_subscription', 'virtual_subscription', 'variable-subscription'];

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_filter('script_loader_tag', [$this, 'module_script_tag'], 10, 2);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'show_on_product']);
        add_action('woocommerce_cart_totals_after_order_total', [$this, 'show_on_cart']);
    }

    public function enqueue_assets(): void
    {
        $this->enqueue_for(self::current_page());
    }

    /**
     * Cart and checkout fragments are refreshed over AJAX, so the script is loaded whenever the
     * page type is configured, whatever the current cart.
     */
    public function enqueue_for(?string $page): void
    {
        if (null === $page || !PayplugWoocommerceHelper::is_eur_shop() || !self::settings()->is_configured_for($page)) {
            return;
        }

        self::enqueue_script();
        if ('product' === $page) {
            wp_enqueue_script(self::REMOUNT_HANDLE, PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-scalapay-widget.js', ['jquery'], PAYPLUG_GATEWAY_VERSION, true);
        }
        if ('cart' === $page) {
            wp_register_style(self::STYLE_HANDLE, false);
            wp_enqueue_style(self::STYLE_HANDLE);
            wp_add_inline_style(self::STYLE_HANDLE, self::CART_CELL_CSS);
        }
    }

    /**
     * Also called where the widget is printed, for pages that render the checkout without being
     * detected as such (page builders).
     */
    public static function enqueue_script(): void
    {
        wp_enqueue_script(self::SCRIPT_HANDLE, SCALAPAY_WIDGET_URL, [], null, true);
    }

    /**
     * @param string $tag
     * @param string $handle
     */
    public function module_script_tag($tag, $handle): string
    {
        if (self::SCRIPT_HANDLE !== $handle || false !== strpos((string) $tag, 'type="module"')) {
            return (string) $tag;
        }

        $tag = (string) preg_replace('/\stype=(["\'])[^"\']*\1/', '', (string) $tag, 1);

        return (string) preg_replace('/<script\b/', '<script type="module"', $tag, 1);
    }

    public function show_on_product(): void
    {
        global $product;

        if (!function_exists('is_product') || !is_product() || !$product instanceof \WC_Product) {
            return;
        }

        echo self::product_markup($product);
    }

    public function show_on_cart(): void
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }

        echo self::cart_row(self::markup('cart', self::with_shop_country(ApmCheckoutContext::current())));
    }

    public static function product_markup(\WC_Product $product): string
    {
        if (in_array($product->get_type(), self::SUBSCRIPTION_TYPES, true)) {
            return '';
        }

        $cents = array_map(static fn ($price): int => (int) PayplugWoocommerceHelper::get_payplug_amount($price), self::display_prices($product));

        return self::markup('product', self::with_shop_country([
            'amount' => empty($cents) ? 0 : self::settings()->amount_to_check(min($cents), max($cents)),
            'currency' => (string) get_woocommerce_currency(),
            'country' => WC()->customer ? (string) WC()->customer->get_billing_country() : '',
        ]));
    }

    public static function cart_row(string $markup): string
    {
        return '' === $markup ? '' : '<tr><td colspan="2" class="' . self::CART_CELL_CLASS . '">' . $markup . '</td></tr>';
    }

    /**
     * Widget of a page where the amount and the billing country are checked by the caller: on the
     * checkout the payment method itself is listed only when eligible.
     *
     * @return array<string, string>|null
     */
    public static function configured_attributes(string $page): ?array
    {
        $settings = self::settings();

        return self::attributes_if($settings->is_configured_for($page), $page, $settings);
    }

    /**
     * @return array<int|float|string> displayed prices: of the variations of a variable product, of the
     *                                 visible children of a grouped one
     */
    private static function display_prices(\WC_Product $product): array
    {
        if ($product instanceof \WC_Product_Variable) {
            return array_values($product->get_variation_prices(true)['price']);
        }
        if (!$product instanceof \WC_Product_Grouped) {
            return [wc_get_price_to_display($product)];
        }

        $prices = [];
        foreach (array_filter(array_map('wc_get_product', $product->get_children()), 'wc_products_array_filter_visible_grouped') as $child) {
            if ('' !== $child->get_price() && !in_array($child->get_type(), array_merge(self::SUBSCRIPTION_TYPES, ['grouped']), true)) {
                $prices = array_merge($prices, self::display_prices($child));
            }
        }

        return $prices;
    }

    /**
     * @param array{amount: int, currency: string, country: string}|null $context
     */
    private static function markup(string $page, ?array $context): string
    {
        $settings = self::settings();

        return ScalapayWidgetRenderer::render(self::attributes_if($settings->should_render($page, $context), $page, $settings));
    }

    /**
     * @return array<string, string>|null
     */
    private static function attributes_if(bool $renderable, string $page, ScalapayWidgetSettings $settings): ?array
    {
        if (!apply_filters('payplug_scalapay_widget_should_render', $renderable, $page)) {
            return null;
        }

        return ScalapayWidgetRenderer::attributes($page, $settings, ScalapayLocale::for_site());
    }

    /**
     * @param array{amount: int, currency: string, country: string}|null $context
     *
     * @return array{amount: int, currency: string, country: string}|null the shop country when no billing country is known
     */
    private static function with_shop_country(?array $context): ?array
    {
        if (null !== $context && '' === $context['country']) {
            $context['country'] = (string) WC()->countries->get_base_country();
        }

        return $context;
    }

    private static function current_page(): ?string
    {
        $pages = [
            'product' => function_exists('is_product') && is_product(),
            'cart' => function_exists('is_cart') && is_cart(),
            'checkout' => function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received') && !is_checkout_pay_page(),
        ];
        $page = array_search(true, $pages, true);

        return false === $page ? null : $page;
    }

    private static function settings(): ScalapayWidgetSettings
    {
        $options = PayplugWoocommerceHelper::get_payplug_options();

        return new ScalapayWidgetSettings(is_array($options) ? $options : []);
    }
}
