<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Controller\HostedFields;
use Payplug\PayplugWoocommerce\Controller\IntegratedPayment;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Upc\Adapters\WooCommerceLock;
use Payplug\PayplugWoocommerce\Upc\PaymentCaptureContextBuilder;
use Payplug\PayplugWoocommerce\Upc\PaymentCaptureOutcomeApplier;
use Payplug\PayplugWoocommerce\Upc\UnifiedApiPaymentServiceFactory;
use PayplugUnifiedCore\Exceptions\ApiException;
use PayplugUnifiedCore\Exceptions\InvalidHostedFieldException;
use PayplugUnifiedCore\Exceptions\PayplugException;

class PayplugCreditCard extends PayplugGateway
{
    /**
     * Comfortably above the OAuth token fetch + payment-creation call's own combined worst case
     * (10s timeout each, see Upc\Adapters\WpOAuthHttpClient/WpUnifiedApiHttpClient) - long enough
     * to span one real attempt, short enough that a crashed request never locks an order out for
     * long.
     */
    private const PROCESS_PAYMENT_LOCK_TTL_SECONDS = 30;

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
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        if ('hosted_fields' !== $this->embedded_mode) {
            return parent::process_payment($order_id);
        }

        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order) {
            return ['result' => 'failure'];
        }

        // An order that already succeeded (payment_complete()'d) or was refunded must never get a
        // second payment created against it - a double-click, or a resubmit after a slow-but-
        // successful first response, would otherwise charge the customer twice. A pending/on-hold/
        // failed order is deliberately NOT short-circuited here: telling "the same attempt,
        // resubmitted" apart from "a genuinely new attempt with a fresh hf_token" isn't possible
        // from this side alone without either an idempotency key the Unified API may not expose,
        // or resolving the prior operation and guessing whether the shopper meant to retry it -
        // both risk misapplying an outcome more than the rarer double-charge this guards against.
        if (in_array($order->get_status(), ['processing', 'completed', 'refunded'], true)) {
            return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
        }

        $hf_token = isset($_POST['hf_token']) ? sanitize_text_field(wp_unslash($_POST['hf_token'])) : '';

        if ('' === $hf_token) {
            wc_add_notice(__('payplug_hosted_fields_missing_token', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        $selected_brand = isset($_POST['hf_selected_brand']) ? sanitize_text_field(wp_unslash($_POST['hf_selected_brand'])) : '';
        $save_card = !empty($_POST['savecard']);

        // Guards only the truly unambiguous half of the double-payment risk: two requests for the
        // *same* order genuinely in flight at once (a double-click, or two tabs) are always the
        // same attempt - never a legitimate new one - so it's always correct to make the second
        // wait rather than create a second payment. A *sequential* retry (this order's previous
        // attempt already finished, successfully or not, before this request started) is
        // deliberately left alone - see the comment on the terminal-status short-circuit above for
        // why that case isn't resolved here.
        $lock = new WooCommerceLock();
        $lock_key = 'payplug_upc_process_payment_' . $order_id;

        if (!$lock->acquire($lock_key, self::PROCESS_PAYMENT_LOCK_TTL_SECONDS)) {
            wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

            return ['result' => 'failure'];
        }

        // Re-read and re-check now that the lock is actually held: the status read above happened
        // before acquire(), so a request that was preempted for as long as another one's entire
        // API round trip could otherwise still build and send a second payment against an order
        // that has, by now, already succeeded.
        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order) {
            $lock->release($lock_key);

            return ['result' => 'failure'];
        }

        if (in_array($order->get_status(), ['processing', 'completed', 'refunded'], true)) {
            $lock->release($lock_key);

            return ['result' => 'success', 'redirect' => $order->get_checkout_order_received_url()];
        }

        try {
            $dto = (new PaymentCaptureContextBuilder())->build($order, $hf_token, $selected_brand, $save_card);
            $output = (new UnifiedApiPaymentServiceFactory())->create()->createPayment($dto);

            return (new PaymentCaptureOutcomeApplier())->apply($order, $output);
        } catch (ApiException $e) {
            self::log(sprintf('UPC payment creation failed for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (InvalidHostedFieldException $e) {
            self::log(sprintf('UPC payment creation rejected for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (PayplugException $e) {
            // Catch-all for every other UPC exception (InvalidCommonFieldsException from a
            // misconfigured/empty account id, InvalidPhoneNumberException, ...) - all of them
            // share PayplugException as their base. Without this, one of their messages (internal,
            // not written for a shopper) would escape to WC_Checkout::process_checkout()'s generic
            // \Exception handler and get shown verbatim as a checkout error notice.
            self::log(sprintf('UPC payment creation rejected for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } catch (\LogicException $e) {
            self::log(sprintf('UPC payment creation misconfigured for order #%s: %s', $order_id, $e->getMessage()), 'error');
        } finally {
            $lock->release($lock_key);
        }

        wc_add_notice(__('payplug_hosted_fields_tokenization_error', 'payplug'), 'error');

        return ['result' => 'failure'];
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
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {
        $src = ('it_IT' === get_locale())
            ? PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_PostePay.svg'
            : PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/logos_scheme_CB.svg';

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
        ];

        /**x
         * Integrated payments scripts
         */
        wp_enqueue_style('payplugIP', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-integrated-payments.css', [], PAYPLUG_GATEWAY_VERSION);

        wp_register_script('payplug-domain', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-domain.js', [], 'v1.0');
        wp_enqueue_script('payplug-domain');
        wp_register_script('payplug-integrated-payments-api', IP_API, [], 'v1.1', true);
        wp_enqueue_script('payplug-integrated-payments-api');

        wp_register_script('jquery-bind-first', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/jquery.bind-first-0.2.3.min.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('jquery-bind-first');

        wp_register_script('payplug-integrated-payments', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-integrated-payments.js', ['jquery', 'jquery-bind-first', 'payplug-integrated-payments-api'], 'v1.1', true);
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

        // ajax_url/nonce for wc_ajax_payplug_hosted_fields_token were dropped here: the
        // client no longer round-trips the token through that endpoint before submitting
        // (see payplug-hosted-fields.js's tokenize()) - process_payment() reads hf_token
        // straight from the real checkout POST once the order exists.
        $translations = [
            'company_id' => $account['company_ref'] ?? '',
        ];

        wp_enqueue_style('payplugIP', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-integrated-payments.css', [], PAYPLUG_GATEWAY_VERSION);

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

        if ('hosted_fields' == $this->embedded_mode && $this->hosted_fields_available) {
            echo HostedFields::template_form($this->save_card);
        }

        if ($this->save_card_available()) {
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

        $amount = (int) PayplugWoocommerceHelper::get_payplug_amount($amount);

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
