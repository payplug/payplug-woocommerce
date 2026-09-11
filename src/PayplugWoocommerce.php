<?php

namespace Payplug\PayplugWoocommerce;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Payplug\PayplugWoocommerce\Admin\Ajax;
use Payplug\PayplugWoocommerce\Admin\Metabox;
use Payplug\PayplugWoocommerce\Admin\Notices;
use Payplug\PayplugWoocommerce\Admin\SetupCallback;
use Payplug\PayplugWoocommerce\Admin\WoocommerceActions;
use Payplug\PayplugWoocommerce\Front\Oney\OneyDisplay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugAmex;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugApplePay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugBancontact;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugBizum;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugCreditCard;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugIdeal;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugMybank;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugOney3x;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugOney3xWithoutFees;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugOney4x;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugOney4xWithoutFees;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugSatispay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugScalapay;
use Payplug\PayplugWoocommerce\Gateway\Blocks\PayplugWero;
use Payplug\PayplugWoocommerce\Traits\GatewayGetter;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use Payplug\PayplugWoocommerce\Upc\PaymentReconciler;

class PayplugWoocommerce
{
    use ServiceGetter;
    use GatewayGetter;

    /**
     * @var PayplugWoocommerce
     */
    private static $instance;

    /**
     * PayPlug admin notices
     *
     * @var Notices
     */
    public $notices;

    /**
     * PayPlug metabox
     *
     * @var Metabox
     */
    public $metabox;

    /**
     * Custom woocommerce actions
     *
     * @var WoocommerceActions
     */
    public $actions;

    /**
     * @var PayplugWoocommerceRequest
     */
    public $requests;

    /**
     * Ajax actions handler
     *
     * @var Ajax
     */
    public $ajax;

    /**
     * PayPlug Setup authentifications.
     *
     * @var SetupCallback
     */
    public $setup_callback;

    /**
     * Get the singleton instance.
     *
     * @return PayplugWoocommerce
     */
    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Singleton instance can't be cloned.
     */
    private function __clone()
    {
    }

    /**
     * Singleton instance can't be serialized.
     */
    public function __wakeup(): void
    {
    }

    /**
     * PayplugWoocommerce constructor.
     */
    private function __construct()
    {
        // Bail early if WooCommerce is not activated
        if (!defined('WC_VERSION')) {
            add_action('admin_notices', function (): void {
                ?>
				<div id="message" class="notice notice-error">
					<p><?php _e('PayPlug requires an active version of WooCommerce', 'payplug'); ?></p>
				</div>
				<?php
            });

            return;
        }

        $this->check_upgrade();

        if (PayplugWoocommerceHelper::is_pre_30()) {
            require_once PAYPLUG_GATEWAY_PLUGIN_DIR . '/woocommerce-compat.php';
        }

        $this->notices = new Notices();
        $this->metabox = new Metabox();
        $this->actions = new WoocommerceActions();
        $this->requests = new PayplugWoocommerceRequest();
        new Front\ApplePay();
        new Front\HostedFields();
        new Front\UpcWebhook();
        add_action('woocommerce_api_payplug_upc_3ds', [$this, 'render_upc_3ds_redirect']);
        add_action('template_redirect', [$this, 'maybe_notice_upc_cancelled']);
        add_action('woocommerce_thankyou', [$this, 'maybe_reconcile_pending_upc_payment']);
        $this->ajax = new Ajax();

        $this->setup_callback = new SetupCallback();

        if (PayplugWoocommerceHelper::show_oney_popup()) {
            $this->animationHandlers();
        }

        add_action('woocommerce_payment_gateways', [$this, 'register_payplug_gateway']);

        // Registers WooCommerce Blocks integration.
        add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_gateways_block_support']);

        add_filter('plugin_action_links_' . PAYPLUG_GATEWAY_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }

    /**
     * Register PayPlug gateway.
     *
     * @param $methods
     *
     * @return array
     */
    public function register_payplug_gateway($methods)
    {
        $methods[] = __NAMESPACE__ . '\\Gateway\\PayplugCreditCard';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney3x';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney4x';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney3xWithoutFees';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PayplugGatewayOney4xWithoutFees';
        $methods[] = __NAMESPACE__ . '\\Gateway\\Bancontact';
        $methods[] = __NAMESPACE__ . '\\Gateway\\AmericanExpress';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Mybank';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Ideal';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Satispay';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Wero';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Bizum';
        $methods[] = __NAMESPACE__ . '\\Gateway\\PPRO\\Scalapay';

        $methods[] = __NAMESPACE__ . '\\Controller\\ApplePay';

        return $methods;
    }

    /**
     * Add additional action links.
     *
     * @param array $links
     *
     * @return array
     */
    public function plugin_action_links($links = [])
    {
        $plugin_links = [
            '<a href="' . esc_url(PayplugWoocommerceHelper::get_setting_link()) . '">' . esc_html__('Settings', 'payplug') . '</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    public function animationHandlers(): void
    {
        new OneyDisplay();
    }

    /**
     * Registers WooCommerce Blocks integration.
     */
    public function woocommerce_gateways_block_support(): void
    {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry): void {
                    $payment_method_registry->register(new PayplugCreditCard());
                    $payment_method_registry->register(new PayplugBancontact());
                    $payment_method_registry->register(new PayplugSatispay());
                    $payment_method_registry->register(new PayplugAmex());
                    $payment_method_registry->register(new PayplugIdeal());
                    $payment_method_registry->register(new PayplugMybank());
                    $payment_method_registry->register(new PayplugWero());
                    $payment_method_registry->register(new PayplugBizum());
                    $payment_method_registry->register(new PayplugScalapay());
                    $payment_method_registry->register(new PayplugApplePay());
                    $payment_method_registry->register(new PayplugOney3x());
                    $payment_method_registry->register(new PayplugOney4x());
                    $payment_method_registry->register(new PayplugOney3xWithoutFees());
                    $payment_method_registry->register(new PayplugOney4xWithoutFees());
                }
            );
        }
    }

    private function check_upgrade()
    {
        return $this->get_service('upgrade')->run_upgrade();
    }

    /**
     * Echoes the Base64-decoded 3DS-pending HTML (a self-submitting form to the bank's challenge
     * page) stashed by PaymentCaptureOutcomeApplier::apply(), then clears it - single-use, same as
     * the token it was built from.
     */
    public function render_upc_3ds_redirect(): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is the bank's own
        // self-submitting 3DS challenge form, already Base64-decoded by
        // PaymentOutput/UnifiedApiPaymentService from a 2xx Unified API response; escaping it
        // would corrupt the form.
        echo $this->resolve_upc_3ds_html();
        exit;
    }

    /**
     * Split out from render_upc_3ds_redirect() so the order-key validation and single-use-transient
     * handling - the security-sensitive part this endpoint depends on - is unit-testable without
     * that method's own exit call, which can't be caught in PHPUnit. A failure path never returns
     * (wp_die() itself exits - or, under the core test suite's wp_die_handler override, throws
     * WPDieException instead).
     */
    public function resolve_upc_3ds_html(): string
    {
        $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $submitted_order_key = isset($_GET['order_key']) ? sanitize_text_field(wp_unslash($_GET['order_key'])) : '';

        $order = wc_get_order($order_id);
        // wc_get_order() also resolves a refund id to a WC_Order_Refund, which has no
        // get_order_key() - an instanceof check, not a "!== false" one, keeps this public,
        // unauthenticated endpoint from fataling on a guessed refund id.
        $order_key_matches = $order instanceof \WC_Order && '' !== $submitted_order_key && hash_equals($order->get_order_key(), $submitted_order_key);

        // Validate the key BEFORE consuming the single-use transient: deleting it first would let
        // anyone who merely guesses an order id destroy the legitimate customer's 3DS challenge.
        if (!$order_key_matches) {
            wp_die(esc_html__('This payment confirmation link has expired.', 'payplug'), '', ['response' => 410]);
        }

        $html = get_transient('payplug_upc_redirect_html_' . $order_id);

        delete_transient('payplug_upc_redirect_html_' . $order_id);

        if (false === $html) {
            wp_die(esc_html__('This payment confirmation link has expired.', 'payplug'), '', ['response' => 410]);
        }

        return $html;
    }

    /**
     * A shopper who abandons the bank's 3DS challenge is sent here (PaymentCaptureContextBuilder
     * sets this order-pay URL as the payment's cancelUrl) rather than to the order-received page,
     * which would otherwise show "Thank you, your order has been received" for a payment that was
     * never completed.
     */
    public function maybe_notice_upc_cancelled(): void
    {
        if (!isset($_GET['payplug_upc_cancelled'])) {
            return;
        }

        wc_add_notice(__('Your payment was not completed. Please try again.', 'payplug'), 'notice');
    }

    /**
     * @param int $order_id
     */
    public function maybe_reconcile_pending_upc_payment($order_id): void
    {
        $order = wc_get_order($order_id);

        if ($order instanceof \WC_Order) {
            (new PaymentReconciler())->reconcile($order);
        }
    }
}
