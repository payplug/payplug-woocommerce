<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Controller\HostedFields;
use Payplug\PayplugWoocommerce\Controller\IntegratedPayment;
use Payplug\PayplugWoocommerce\Model\UhfCard;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\HostedFieldsPaymentProcessor;

class PayplugCreditCard extends PayplugGateway
{
    public $save_card = false;

    /**
     * Whether the hosted_fields mode can actually render a working card form: both
     * HOSTED_FIELDS_SDK_URL and the account's company_ref (from GET /account) must be
     * available, or the SDK is given an empty src/companyId and silently renders nothing
     * (see hosted_fields_scripts() and payment_fields()).
     */
    public bool $hosted_fields_available = true;

    public function __construct()
    {
        parent::__construct();

        $this->id = 'payplug';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = _x('PayPlug', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug for your customers.', 'payplug');
        $this->new_method_label = __('Pay with another credit card', 'payplug');
        $this->title = $this->get_configuration()->get_option('payment_methods.configuration.payplug.title');
        $this->description = $this->get_configuration()->get_option('payment_methods.configuration.payplug.description');
        $this->save_card = $this->get_configuration()->get_option('payment_methods.configuration.payplug.save_card') && is_user_logged_in();
        $this->embedded_mode = $this->get_configuration()->get_option('payment_methods.configuration.payplug.embedded_mode');

        // A shop's stored mode can predate its current currency (e.g. it was 'popup'
        // before switching to a non-EUR currency, or vice versa) - satisfy_requirements()
        // no longer blocks the whole gateway on currency, so a stale mode would otherwise
        // reach checkout and render the wrong flow for the current currency. Mirrors the
        // BO-display normalization in PaymentMethods::payment_method_standard(); this
        // doesn't persist anything, the stored value is only corrected once the merchant
        // saves the BO settings again.
        $is_eur_shop = PayplugWoocommerceHelper::is_eur_shop();
        if ($is_eur_shop && !in_array($this->embedded_mode, ['redirect', 'popup', 'integrated'], true)) {
            $this->embedded_mode = 'redirect';
        } elseif (!$is_eur_shop && 'hosted_fields' !== $this->embedded_mode) {
            $this->embedded_mode = 'hosted_fields';
        }

        if ('hosted_fields' === $this->embedded_mode) {
            $company_ref = PayplugWoocommerceHelper::get_account_data_from_options()['company_ref'] ?? '';
            $this->hosted_fields_available = !empty($company_ref) && !empty(HOSTED_FIELDS_SDK_URL);

            if (!$this->hosted_fields_available) {
                PayplugGateway::log(sprintf(
                    'Hosted Fields form not rendered: %s.',
                    empty($company_ref) ? 'company_ref is empty (GET /account failed?)' : 'HOSTED_FIELDS_SDK_URL is not configured'
                ), 'error');
            }
        }

        $this->supports = [
            'products',
            'refunds',
            'tokenization',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        ];

        // Ensure the description is not empty to correctly display users's save cards
        if (empty($this->description) && $this->save_card_available()) {
            $this->description = ' ';
        }

        if ('test' === $this->mode) {
            $this->description .= " \n";
            $this->description .= __('You are in TEST MODE. In test mode you can use the card 4242424242424242 with any valid expiration date and CVC.', 'payplug');
            $this->description = trim($this->description);
        }

        //add fields of IP to the description
        if ('integrated' == $this->embedded_mode || 'hosted_fields' == $this->embedded_mode) {
            $this->has_fields = true;
        }

        $this->handle_cc_enabled();

        add_action('wp_enqueue_scripts', [$this, 'scripts']);
        if (PayplugWoocommerceHelper::is_subscriptions_enabled()) {
            add_action(
                'woocommerce_scheduled_subscription_payment_' . $this->id,
                [$this, 'scheduled_subscription_payment'],
                10,
                2
            );
        }
    }

    /**
     * A hosted_fields gateway whose card form can't render (see $hosted_fields_available) must
     * not be offered at all: the shopper would otherwise see "Card" with nothing to fill in and
     * only get a "missing token" error on submit. Non-EUR shops are forced into hosted_fields,
     * so they have no other card flow to fall back to.
     */
    public function is_available()
    {
        if ('hosted_fields' === $this->embedded_mode && !$this->hosted_fields_available) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        if ('hosted_fields' !== $this->embedded_mode) {
            return parent::process_payment($order_id);
        }

        return (new HostedFieldsPaymentProcessor($this))->process_payment((int) $order_id);
    }

    /**
     * @param int $order_id
     * @param float|string|null $amount
     * @param string $reason
     *
     * @return bool|\WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        // No such order, or one paid via the Retail API (no UPC operation on it): both keep
        // going through the inherited Retail process_refund() untouched - only a UPC-paid order
        // takes this path. Delegating a missing order to it, rather than duplicating its own
        // "order does not exist" WP_Error here, avoids a second untested error-message copy.
        if (!$order instanceof \WC_Order) {
            return parent::process_refund($order_id, $amount, $reason);
        }

        $operation_id = (string) $order->get_meta('_payplug_upc_operation_id');

        if ('' === $operation_id) {
            return parent::process_refund($order_id, $amount, $reason);
        }

        return (new HostedFieldsPaymentProcessor($this))->process_refund($order, $operation_id, $amount, (string) $reason);
    }

    /**
     * if the plugin is disabled the gateways should be disabled
     *
     * @return mixed|string
     */
    private function handle_cc_enabled()
    {
        if (!empty($this->settings['enabled']) && (bool) $this->settings['enabled']) {
            $active = $this->get_configuration()->get_option('payment_methods.configuration.payplug.active');
            $this->enabled = ($active === null || (bool) $active) ? 'yes' : 'no';
        } else {
            $this->enabled = 'no';
        }

        return $this->enabled;
    }

    /**
     * Hosted fields (UHF) is only offered for non-EUR currencies, where CB cards aren't
     * accepted: its logo shows Visa and Mastercard only.
     */
    public static function scheme_logo_url(?string $embedded_mode): string
    {
        if ('hosted_fields' === $embedded_mode) {
            return PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/logos_scheme_visa_mastercard.svg';
        }

        return ('it_IT' === get_locale())
            ? PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_PostePay.svg'
            : PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg';
    }

    /**
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {
        $src = self::scheme_logo_url($this->embedded_mode);

        $icons = apply_filters('payplug_payment_icons', [
            'payplug' => sprintf('<img src="%s" alt="Visa & Mastercard" class="payplug-payment-icon" />', esc_url($src)),
        ]);

        $icons_str = '';
        foreach ($icons as $icon) {
            $icons_str .= $icon;
        }

        return $icons_str;
    }

    /**
     * Embedded payment form scripts.
     *
     * Register scripts and additionnal data needed for the
     * embedded payment form.
     */
    public function scripts(): void
    {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page() && !isset($_GET['change_payment_method'])) {
            return;
        }

        // If PayPlug is not enabled bail.
        if ('no' == $this->enabled) {
            return;
        }

        // If keys are not set bail.
        if (empty($this->get_api_key($this->mode))) {
            PayplugGateway::log('Keys are not set correctly.');

            return;
        }

        // Register checkout styles.
        wp_register_style('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-checkout.css', [], PAYPLUG_GATEWAY_VERSION);
        wp_enqueue_style('payplug-checkout');

        if (
            ('integrated' == $this->embedded_mode && !PayplugWoocommerceHelper::is_checkout_block()) ||
            ('integrated' == $this->embedded_mode && is_wc_endpoint_url('order-pay'))
        ) {
            $this->integrated_payments_scripts();
        }

        if (('popup' == $this->embedded_mode) && ('payplug' == $this->id || 'american_express' == $this->id) && !PayplugWoocommerceHelper::is_checkout_block()) {
            $this->popup_payments_scripts();
        }

        if ('hosted_fields' == $this->embedded_mode && (!PayplugWoocommerceHelper::is_checkout_block() || is_wc_endpoint_url('order-pay'))) {
            $this->hosted_fields_scripts();
        }
    }

    /**
     * Integrated payment form scripts.
     *
     * Register scripts and additionnal data needed for the
     * embedded payment form.
     */
    public function integrated_payments_scripts(): void
    {
        $translations = [
            'cardholder' => __('payplug_integrated_payment_cardholder', 'payplug'),
            'your_card' => __('payplug_integrated_payment_your_card', 'payplug'),
            'card_number' => __('payplug_integrated_payment_card_number', 'payplug'),
            'expiration_date' => __('payplug_integrated_payment_expiration_date', 'payplug'),
            'cvv' => __('payplug_integrated_payment_cvv', 'payplug'),
            'one_click' => __('payplug_integrated_payment_oneClick', 'payplug'),
            'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
            'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
            'nonce' => wp_create_nonce('woocommerce-process_checkout'),
            'mode' => PayplugWoocommerceHelper::check_mode(), // true for TEST, false for LIVE
            'check_payment_url' => \WC_AJAX::get_endpoint('payplug_check_payment'),
            'secureDomain' => PayplugWoocommerceHelper::get_secure_domain(),
            'check_payment_error' => __('payplug_integrated_payment_check_error', 'payplug'),
        ];

        /**x
         * Integrated payments scripts
         */
        wp_enqueue_style('payplug-card-form', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-card-form.css', [], PAYPLUG_GATEWAY_VERSION);

        wp_register_script('payplug-domain', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-domain.js', [], 'v1.0');
        wp_enqueue_script('payplug-domain');
        wp_register_script('payplug-integrated-payments-api', IP_API, [], 'v1.1', true);
        wp_enqueue_script('payplug-integrated-payments-api');

        wp_register_script('jquery-bind-first', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/jquery.bind-first-0.2.3.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('jquery-bind-first');

        wp_register_script('payplug-integrated-payments', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-integrated-payments.js', ['jquery', 'jquery-bind-first', 'payplug-integrated-payments-api'], PAYPLUG_GATEWAY_VERSION, true);
        wp_enqueue_script('payplug-integrated-payments');

        wp_localize_script('payplug-integrated-payments', 'payplug_integrated_payment_params', $translations);
    }

    /**
     * popup payment form scripts.
     *
     * Register scripts and additionnal data needed for the
     * embedded payment form.
     */
    public function popup_payments_scripts(): void
    {
        //load popup features
        wp_register_script('payplug', 'https://api.payplug.com/js/1/form.latest.js', [], null, true);
        wp_register_script('payplug-checkout', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-checkout.js', ['jquery', 'payplug'], PAYPLUG_GATEWAY_VERSION, true);
        wp_localize_script('payplug-checkout', 'payplug_checkout_params', [
            'ajax_url' => \WC_AJAX::get_endpoint('payplug_create_order'),
            'order_review_url' => \WC_AJAX::get_endpoint('payplug_order_review_url'),
            'nonce' => [
                'checkout' => wp_create_nonce('woocommerce-process_checkout'),
            ],
            'is_embedded' => 'redirect' !== $this->embedded_mode,
        ]);

        wp_enqueue_script('payplug-checkout');
    }

    /**
     * Hosted Fields payment form scripts.
     *
     * Register scripts and additional data needed for the
     * hosted fields payment form.
     */
    public function hosted_fields_scripts(): void
    {
        // An empty HOSTED_FIELDS_SDK_URL registers a script with no src (prints nothing,
        // window.dalenys stays undefined) and an empty companyId renders a card form the
        // SDK will never fill in - both silently, with no console error. Bail instead of
        // enqueueing a form that can never work; payment_fields() uses the same
        // hosted_fields_available flag (computed once in the constructor) to skip
        // rendering the template altogether.
        if (!$this->hosted_fields_available) {
            return;
        }

        // The Dalenys SDK's companyId must be the account's UUID company_ref (from
        // GET /account, mirroring the Sylius PayPlug plugin) - not the merchant-entered
        // Account ID (hosted_fields.identifier), which is a different value reserved for
        // a future server-side payment-capture call.
        $account = PayplugWoocommerceHelper::get_account_data_from_options();

        // No ajax_url/nonce: the client never round-trips the token through a separate
        // endpoint (see payplug-hosted-fields.js's tokenize()) - process_payment() reads
        // hf_token straight from the real checkout POST once the order exists.
        $translations = [
            'company_id' => $account['company_ref'] ?? '',
            'placeholder_pan' => __('payplug_integrated_payment_card_number', 'payplug'),
            'placeholder_exp' => __('payplug_integrated_payment_expiration_date', 'payplug'),
            'placeholder_cvv' => __('payplug_integrated_payment_cvv', 'payplug'),
        ];

        wp_enqueue_style('payplug-card-form', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-card-form.css', [], PAYPLUG_GATEWAY_VERSION);

        wp_register_script('payplug-hosted-fields-sdk', HOSTED_FIELDS_SDK_URL, [], null, true);
        wp_enqueue_script('payplug-hosted-fields-sdk');

        // jquery-bind-first isn't guaranteed registered by the other embedded modes
        // (hosted_fields and integrated are mutually exclusive), so it's registered here
        // too rather than assumed - same handle/asset integrated_payments_scripts() uses.
        wp_register_script('jquery-bind-first', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/jquery.bind-first-0.2.3.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('jquery-bind-first');

        wp_register_script('payplug-hosted-fields', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-hosted-fields.js', ['jquery', 'jquery-bind-first', 'payplug-hosted-fields-sdk'], PAYPLUG_GATEWAY_VERSION, true);
        wp_enqueue_script('payplug-hosted-fields');

        wp_localize_script('payplug-hosted-fields', 'payplug_hosted_fields_params', $translations);
    }

    /**
     * extra payment fields
     */
    public function payment_fields(): void
    {
        $description = $this->get_description();

        if (!empty($description)) {
            echo wpautop(wptexturize($description));
        }

        if ('integrated' == $this->embedded_mode) {
            echo IntegratedPayment::template_form($this->save_card);
        }

        // Deliberately elseif, not two independent ifs: a UHF alias is never a WC_Payment_Token
        // (see Controller/HostedFields.php), so WooCommerce's native saved_payment_methods()
        // list would show the merchant's Retail-tokenized cards even though hosted_fields mode
        // can never charge them - worse than not showing them at all. A merchant switching to
        // hosted_fields therefore does lose the Retail card list from checkout with no
        // migration; that's the intended trade-off, not an oversight.
        if ('hosted_fields' == $this->embedded_mode && $this->hosted_fields_available) {
            $cards = ($this->save_card && is_user_logged_in())
                ? UhfCard::get_customer_cards(get_current_user_id(), $this->mode)
                : [];
            echo HostedFields::template_form($this->save_card, $cards);
        } elseif ($this->save_card_available()) {
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
    }

    /**
     * Process the subscription scheduled payment
     */
    public function scheduled_subscription_payment($amount, $order)
    {
        $order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
        $subscription = wcs_get_subscription($order->get_meta('_subscription_renewal'));
        $payplug_parent_meta = $subscription->get_parent()->get_meta('_payplug_metadata');

        if (!$payplug_parent_meta) {
            PayplugGateway::log('Could not find the intial payment data belong to the current user and the current subscription.', 'error');
            throw new \Exception(__('Invalid payment method.', 'payplug'));
        }

        $parent_order = $subscription->get_parent();
        $parent_tokens = $parent_order->get_payment_tokens();

        if (!empty($parent_tokens)) {
            $token = $parent_tokens[0];
        } else {
            $token = $this->payplug_api->payment_retrieve($payplug_parent_meta['transaction_id'])->card->id;
        }

        if (!$token) {
            PayplugGateway::log('Could not find the payment token or the payment doesn\'t belong to the current user.', 'error');
            throw new \Exception(__('Invalid payment method.', 'payplug'));
        }

        $amount = (int) PayplugWoocommerceHelper::get_payplug_amount($amount, $order->get_currency());

        try {
            $address_data = PayplugAddressData::from_order($order);
            $return_url = esc_url_raw($order->get_checkout_order_received_url());

            if (!(substr($return_url, 0, 4) === 'http')) {
                $return_url = get_site_url() . $return_url;
            }

            $payment_data = [
                'amount' => $amount,
                'currency' => get_woocommerce_currency(),
                'payment_method' => $token,
                'allow_save_card' => false,
                'billing' => $address_data->get_billing(),
                'shipping' => $address_data->get_shipping(),
                'initiator' => 'MERCHANT',
                'hosted_payment' => [
                    'return_url' => $return_url,
                    'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
                ],
                'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
                'metadata' => [
                    'order_id' => $order->get_id(),
                    'customer_id' => ((int) get_current_user_id() > 0) ? get_current_user_id() : 'guest',
                    'domain' => $this->limit_length(esc_url_raw(home_url()), 500),
                    'woocommerce_block' => \WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout'),
                    'subscription' => 'renewal',
                ],
            ];

            PayplugGateway::log(sprintf('Processing payment for order #%s', $order_id));
            PayplugGateway::log(sprintf('Processing payment for subscription #%s', $order->get_meta('_subscription_renewal')));

            /** This filter is documented in src/Gateway/PayplugGateway */
            $payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);

            $payment = $this->payplug_api->payment_create($payment_data);

            // Save transaction id for the order
            PayplugWoocommerceHelper::is_pre_30()
                ? update_post_meta($order_id, '_transaction_id', $payment->id)
                : $order->set_transaction_id($payment->id);

            if (is_callable([$order, 'save'])) {
                $order->save();
            }

            /** This action is documented in src/Gateway/PayplugGateway */
            \do_action('payplug_gateway_payment_created', $order_id, $payment);

            $metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
            PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

            $this->response->process_payment($payment, true);

            if (($payment->__get('is_paid'))) {
                $redirect = $order->get_checkout_order_received_url();
            } elseif (isset($payment->__get('hosted_payment')->payment_url)) {
                $redirect = $payment->__get('hosted_payment')->payment_url;
            } else {
                $redirect = $return_url;
            }

            return [
                'payment_id' => $payment->id,
                'result' => 'success',
                'is_paid' => $payment->__get('is_paid'), // Use for path redirect before DSP2
                'redirect' => $redirect,
            ];
        } catch (HttpException $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
            throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
            throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
        }
    }
}
