<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Payplug\PayplugWoocommerce\Front\Scalapay\ScalapayWidgetDisplay;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Service\ApmEligibility;

/**
 * Scalapay installment widget in the order summary of the Blocks cart. The amount and the billing
 * country are checked in the browser, against the live cart.
 */
class ScalapayWidgetCartIntegration implements IntegrationInterface
{
    public const HANDLE = 'wc-payplug-scalapay_widget_cart-blocks';

    public function get_name(): string
    {
        return 'payplug_scalapay_widget_cart';
    }

    public function initialize(): void
    {
        $asset_path = PAYPLUG_GATEWAY_PLUGIN_DIR . 'assets/js/blocks/wc-payplug-scalapay_widget_cart-blocks.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }
        $asset = require $asset_path;

        wp_register_script(self::HANDLE, PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/blocks/wc-payplug-scalapay_widget_cart-blocks.js', $asset['dependencies'], $asset['version'], true);
    }

    public function get_script_handles(): array
    {
        return wp_script_is(self::HANDLE, 'registered') && null !== ScalapayWidgetDisplay::configured_attributes('cart') ? [self::HANDLE] : [];
    }

    public function get_editor_script_handles(): array
    {
        return [];
    }

    public function get_script_data(): array
    {
        $options = PayplugWoocommerceHelper::get_payplug_options();

        return [
            'widget' => ScalapayWidgetDisplay::configured_attributes('cart'),
            'restrictions' => ApmEligibility::client_restrictions(is_array($options) ? $options : [], 'scalapay'),
            'country' => (string) WC()->countries->get_base_country(),
        ];
    }
}
