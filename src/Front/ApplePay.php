<?php

namespace Payplug\PayplugWoocommerce\Front;

use Payplug\Payplug;
use Payplug\PayplugWoocommerce\Controller\ApplePay as ApplePayGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;

class ApplePay
{
    use ServiceGetter;

    /**
     * Marks the orders created by place_order_with_dummy_data(), the only ones applepay_cancel_order() deletes.
     */
    private const CART_ORDER_META = '_payplug_applepay_cart_order';

    /**
     * Order setter of each Apple Pay contact field, besides addressLines and emailAddress.
     */
    private const SHIPPING_CONTACT_SETTERS = [
        'familyName' => 'set_shipping_last_name',
        'givenName' => 'set_shipping_first_name',
        'country' => 'set_shipping_country',
        'locality' => 'set_shipping_city',
        'phoneNumber' => 'set_shipping_phone',
        'postalCode' => 'set_shipping_postcode',
    ];

    private const BILLING_CONTACT_SETTERS = [
        'familyName' => 'set_billing_last_name',
        'givenName' => 'set_billing_first_name',
        'locality' => 'set_billing_city',
        'country' => 'set_billing_country',
        'postalCode' => 'set_billing_postcode',
    ];

    private $options;

    public function __construct()
    {
        $this->options = $this->get_service('configuration')->get_options();

        //routes for apple_pay on cart and product page
        add_action('wc_ajax_applepay_get_shippings', [$this, 'applepay_get_shippings']);
        add_action('wc_ajax_place_order_with_dummy_data', [$this, 'place_order_with_dummy_data']);
        add_action('wc_ajax_update_applepay_order', [$this, 'update_applepay_order']);
        add_action('wc_ajax_update_applepay_payment', [$this, 'update_applepay_payment']);
        add_action('wc_ajax_applepay_cancel_order', [$this, 'applepay_cancel_order']);
        add_action('wc_ajax_applepay_empty_cart', [$this, 'applepay_empty_cart']);
        add_action('wc_ajax_applepay_add_to_cart', [$this, 'applepay_add_to_cart']);
    }

    public function applepay_get_shippings(): void
    {
        WC()->customer->set_shipping_country('FR');
        WC()->customer->set_shipping_city('Paris');
        WC()->customer->set_shipping_postcode('12345');
        WC()->customer->set_shipping_address('Dummy Address');

        $packages = WC()->cart->get_shipping_packages();
        $shippings = [];

        foreach ($packages as $package_key => $package) {
            $shipping_methods = $this->get_shipping_methods_from_package($package);

            foreach ($shipping_methods as $shipping_method) {
                if (!$shipping_method->supports('shipping-zones') || !$shipping_method->is_enabled()) {
                    continue;
                }

                $rates = $shipping_method->get_rates_for_package($package);
                $rate_id = $shipping_method->get_rate_id();
                if (isset($rates[$rate_id]) && $this->checkApplePayShipping($shipping_method)) {
                    $shipping_rate = $rates[$rate_id];

                    array_push($shippings, [
                        'identifier' => $shipping_method->id . '_' . $shipping_method->instance_id,
                        'label' => $shipping_method->method_title,
                        'detail' => strip_tags($shipping_method->method_description),
                        'amount' => $shipping_rate->get_cost() + $shipping_rate->get_shipping_tax(),
                    ]);
                }
            }
        }
        wp_send_json_success($shippings);
    }

    /**
     * @param $shipping
     *
     * @return bool
     */
    private function checkApplePayShipping($shipping = [])
    {
        if (empty($shipping)) {
            return false;
        }

        $apple_pay_options = PayplugWoocommerceHelper::get_applepay_options();
        $apple_pay_carriers = $apple_pay_options['carriers'];

        $exists = false;
        foreach ($apple_pay_carriers as $carrier => $carrier_id) {
            if ($carrier_id === $shipping->id) {
                return true;
            }
        }

        return $exists;
    }

    private function get_shipping_methods_from_package($package)
    {
        $shipping_zone = \WC_Shipping_Zones::get_zone_matching_package($package);

        return $shipping_zone->get_shipping_methods(true);
    }

    public function place_order_with_dummy_data(): void
    {
        $apple_pay = new ApplePayGateway();
        if (is_admin()) {
            return;
        }

        $cart = WC()->cart;

        if (!$cart->is_empty()) {
            if (!ApplePayRateLimiter::allow()) {
                $this->send_rate_limited();

                return;
            }

            try {
                $order = $this->create_cart_order($apple_pay);
                if ($order) {
                    $cart->empty_cart();

                    // After empty_cart(): emptying the cart clears order_awaiting_payment from the session.
                    PayplugWoocommerceHelper::remember_order_awaiting_payment($order->get_id());

                    $this->process_cart_payment($order, $apple_pay);

                    return;
                }
            } catch (\Throwable $e) {
                PayplugGateway::log(sprintf('Apple Pay: place_order_with_dummy_data failed (%s)', get_class($e)), 'warning');
            }

            $this->send_order_not_created();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Order of the cart with placeholder addresses, null when WooCommerce could not create it.
     */
    private function create_cart_order(ApplePayGateway $apple_pay): ?\WC_Order
    {
        $order_id = WC()->checkout()->create_order(['payment_method' => $apple_pay->id]);
        $order = is_wp_error($order_id) ? false : wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            PayplugGateway::log(sprintf(
                'Apple Pay: place_order_with_dummy_data could not create the order (%s)',
                is_wp_error($order_id) ? $order_id->get_error_code() : 'order not found'
            ), 'warning');

            return null;
        }

        $order->set_address([
            'first_name' => 'payplug_applepay_first_name',
            'last_name' => 'payplug_applepay_last_name',
            'address_1' => 'payplug_applepay_address',
            'address_2' => '',
            'city' => 'payplug_applepay_city',
            'postcode' => 'payplug_applepay_psotcode',
            'country' => WC()->countries->get_base_country(),
            'email' => 'payplug_applepay_email@payplug.com',
        ], 'billing');
        $order->set_address([
            'first_name' => 'payplug_applepay_first_name',
            'last_name' => 'payplug_applepay_last_name',
            'address_1' => 'payplug_applepay_address',
            'address_2' => '',
            'city' => 'payplug_applepay_city',
            'postcode' => 'payplug_applepay_psotcode',
            'country' => WC()->countries->get_base_country(),
            'email' => 'payplug_applepay_email@payplug.com',
        ], 'shipping');

        $order->set_payment_method($apple_pay);
        $order->update_meta_data(self::CART_ORDER_META, 'yes');

        $packages = WC()->cart->get_shipping_packages();

        WC()->shipping()->reset_shipping();

        $order->calculate_taxes();

        $order->calculate_totals();

        $order->save();

        return $order;
    }

    public function process_cart_payment($order, $gateway): void
    {
        $order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
        $customer_id = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
        $amount = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total() - ($order->get_shipping_tax() + $order->get_shipping_total()));
        $amount = $gateway->validate_order_amount($amount);
        wp_send_json([
            'total' => $amount,
            'order_id' => $order_id,
            'payment_data' => $gateway->process_standard_payment($order, $amount, $customer_id, 'product'),
        ]);
    }

    public function update_applepay_order(): void
    {
        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $order = $order_id ? wc_get_order($order_id) : false;
        if (!PayplugWoocommerceHelper::session_owns_order($order)) {
            PayplugWoocommerceHelper::refuse_order('update_applepay_order', $order_id, 'Apple Pay: ');

            return;
        }

        // A logged-in customer owns all their orders: never rewrite the shipping or the total of a settled one.
        if (!$order->needs_payment()) {
            $this->send_order_kept();

            return;
        }

        $selected_shipping_method = !empty($_POST['shipping_method']) ? $_POST['shipping_method'] : null;
        $this->apply_contact($order, PayplugWoocommerceHelper::sanitize_apple_pay_contact($_POST['shipping'] ?? null), 'shipping');
        $this->apply_contact($order, PayplugWoocommerceHelper::sanitize_apple_pay_contact($_POST['billing'] ?? null), 'billing');

        $address_data = PayplugAddressData::from_order($order);

        $order->set_shipping_country($address_data->get_shipping()['country']);
        $order->set_billing_country($address_data->get_billing()['country']);

        $shipping_address = $address_data->get_shipping();
        $shipping_address['state'] = '';

        $package = [
            'contents' => [],
            'contents_cost' => 0,
            'destination' => $shipping_address,
            'applied_coupons' => $order->get_used_coupons(),
            'user' => [
                'ID' => $order->get_customer_id(),
            ],
        ];

        foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
            $order->remove_item($item_id);
        }

        $use_taxes = false;
        $shipping_zone = \WC_Shipping_Zones::get_zone_matching_package($package);
        if ($shipping_zone && !empty($selected_shipping_method)) {
            $shipping_methods = $shipping_zone->get_shipping_methods(true);

            foreach ($shipping_methods as $shipping_method) {
                if (!$shipping_method->supports('shipping-zones') || !$shipping_method->is_enabled()) {
                    continue;
                }

                $id_shipping_method = $shipping_method->id . '_' . $shipping_method->instance_id;
                if ($id_shipping_method === $selected_shipping_method) {
                    $shipping_method->calculate_shipping($package);
                    $rates = $shipping_method->get_rates_for_package($package);

                    if (!empty($rates)) {
                        $rate = reset($rates);

                        $item = new \WC_Order_Item_Shipping();
                        $item->set_method_title($rate->get_label());
                        $item->set_method_id($rate->get_id());
                        $item->set_total($rate->get_cost());

                        if (!empty($rate->taxes)) {
                            $use_taxes = true;
                        }
                        $shipping_taxes = \WC_Tax::calc_shipping_tax(
                            $rate->get_cost(),
                            $rate->get_taxes()
                        );

                        $item->set_taxes([
                            'total' => $shipping_taxes,
                        ]);

                        $item->calculate_taxes();

                        $item->save();

                        $order->add_item($item);
                        break;
                    }
                }
            }
        }

        $order->calculate_totals(wc_tax_enabled());
        $order->save();

        wp_send_json_success();
    }

    /**
     * @param array<string, string|string[]> $contact output of PayplugWoocommerceHelper::sanitize_apple_pay_contact()
     * @param string $type 'shipping' or 'billing'
     */
    private function apply_contact(\WC_Order $order, array $contact, string $type): void
    {
        $setters = 'shipping' === $type ? self::SHIPPING_CONTACT_SETTERS : self::BILLING_CONTACT_SETTERS;
        foreach ($setters as $key => $setter) {
            if (isset($contact[$key])) {
                $order->{$setter}($contact[$key]);
            }
        }

        if (isset($contact['addressLines'])) {
            $order->{'set_' . $type . '_address_1'}($contact['addressLines'][0]);
            if (isset($contact['addressLines'][1])) {
                $order->{'set_' . $type . '_address_2'}($contact['addressLines'][1]);
            }
        }

        if ('shipping' === $type && isset($contact['emailAddress'])) {
            $order->set_billing_email($contact['emailAddress']);
        }
    }

    /**
     * update the payment since customers can change their options during the payment
     *
     * @throws \Payplug\Exception\ConfigurationNotSetException
     *
     * @return void
     */
    public function update_applepay_payment(): void
    {
        $payment_id = isset($_POST['payment_id']) ? wc_clean(wp_unslash($_POST['payment_id'])) : '';
        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $order = $order_id ? wc_get_order($order_id) : false;
        if (
            !PayplugWoocommerceHelper::session_owns_order($order)
            || !is_string($payment_id)
            || '' === $payment_id
            || !hash_equals((string) $order->get_transaction_id(), $payment_id)
        ) {
            PayplugWoocommerceHelper::refuse_order('update_applepay_payment', $order_id, 'Apple Pay: ');

            return;
        }

        if (!$order->needs_payment()) {
            $this->send_order_kept();

            return;
        }

        $applepay = new ApplePayGateway();
        $payment_token = $_POST['payment_token'] ?? null;

        $payment = $this->retrieve_payment($payment_id);
        if (PayplugWoocommerceHelper::get_payment_order_id($payment) !== $order_id) {
            PayplugWoocommerceHelper::refuse_order('update_applepay_payment', $order_id, 'Apple Pay: ');

            return;
        }

        // The order total already holds the shipping method chosen in the sheet (update_applepay_order).
        $amount = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total());
        $amount = $applepay->validate_order_amount($amount);
        if (is_wp_error($amount)) {
            wp_send_json_error(['message' => $amount->get_error_message()]);

            return;
        }

        $address_data = PayplugAddressData::from_order($order);

        // delivery_type must be removed in Apple Pay
        $billing = $address_data->get_billing();
        unset($billing['delivery_type']);
        $shipping = $address_data->get_shipping();
        unset($shipping['delivery_type']);

        $data = ['apple_pay' => [
            'amount' => $amount,
            'payment_token' => $payment_token,
            'billing' => $billing,
            'shipping' => $shipping,
        ]];

        $update = $payment->update($data);

        wp_send_json_success([
            'amount' => $amount,
            'update' => $update,
        ]);
    }

    public function applepay_cancel_order(): void
    {
        if (empty($_POST['order_id'])) {
            wp_send_json_error([
                'message' => __('Your order was cancelled.', 'woocommerce'),
            ]);

            return;
        }

        $order_id = absint(wp_unslash($_POST['order_id']));
        $posted_payment_id = !empty($_POST['payment_id']) ? wc_clean(wp_unslash($_POST['payment_id'])) : '';
        $order = $order_id ? wc_get_order($order_id) : false;
        if (!PayplugWoocommerceHelper::session_owns_order($order) || !$this->is_cart_order($order)) {
            PayplugWoocommerceHelper::refuse_order('applepay_cancel_order', $order_id, 'Apple Pay: ');

            return;
        }

        if (!$order->has_status(['pending', 'failed'])) {
            $this->send_order_kept();

            return;
        }

        $payment_id = (string) $order->get_transaction_id();
        if ('' !== $posted_payment_id && (!is_string($posted_payment_id) || !hash_equals($payment_id, $posted_payment_id))) {
            PayplugWoocommerceHelper::refuse_order('applepay_cancel_order', $order_id, 'Apple Pay: ');

            return;
        }

        $cart_lines = $this->get_cart_lines_from_order($order);

        if ('' !== $payment_id) {
            $payplug = $this->get_merchant_client();
            $payment = null;
            if ($payplug) {
                try {
                    $payment = $this->retrieve_payment($payment_id, $payplug);
                } catch (\Throwable $e) {
                    $this->log_api_failure('retrieved', $order_id, $e);
                }
            }

            if (!$payment) {
                $this->send_order_not_cancelled();

                return;
            }

            if (PayplugWoocommerceHelper::get_payment_order_id($payment) !== $order_id) {
                PayplugWoocommerceHelper::refuse_order('applepay_cancel_order', $order_id, 'Apple Pay: ');

                return;
            }

            if (!empty($payment->is_paid)) {
                PayplugGateway::log(sprintf('Apple Pay: payment of order #%d may be paid, order kept', $order_id), 'warning');
                $this->send_order_kept();

                return;
            }

            if (!$this->abort_payment($payment, $payment_id, $payplug, $order_id)) {
                PayplugGateway::log(sprintf('Apple Pay: payment of order #%d may be paid, order kept', $order_id), 'warning');
                $this->send_order_kept();

                return;
            }
        }

        if (!$order->delete(true)) {
            $this->send_order_not_cancelled();

            return;
        }

        $this->restore_cart($cart_lines);

        if (WC()->session && absint(WC()->session->get('order_awaiting_payment')) === $order_id) {
            WC()->session->set('order_awaiting_payment', null);
        }

        wp_send_json_success([
            'message' => __('Your order was cancelled.', 'woocommerce'),
        ]);
    }

    /**
     * Order created by the Apple Pay cart or product button, still to be paid with Apple Pay.
     */
    private function is_cart_order(\WC_Order $order): bool
    {
        return 'apple_pay' === $order->get_payment_method() && 'yes' === $order->get_meta(self::CART_ORDER_META);
    }

    /**
     * @param string $payment_id
     * @param Payplug|null $payplug client to call the API with, the SDK default configuration when null
     *
     * @return \Payplug\Resource\Payment
     */
    protected function retrieve_payment($payment_id, $payplug = null)
    {
        return \Payplug\Payment::retrieve($payment_id, $payplug);
    }

    /**
     * Client authenticated for the configured mode, null when that mode has no key.
     */
    protected function get_merchant_client(): ?Payplug
    {
        $mode = PayplugWoocommerceHelper::check_mode() ? 'live' : 'test';
        $bearer_token = (string) $this->get_api()->get_bearer_token($mode);
        if ('' === $bearer_token) {
            return null;
        }

        return new Payplug($bearer_token, '2019-08-06');
    }

    /**
     * Aborts the payment. When the abort fails, true only if the payment is not paid and carries a failure.
     *
     * @param \Payplug\Resource\Payment $payment
     */
    private function abort_payment($payment, string $payment_id, Payplug $payplug, int $order_id): bool
    {
        try {
            $payment->abort($payplug);

            return true;
        } catch (\Throwable $e) {
            $this->log_api_failure('aborted', $order_id, $e);
        }

        try {
            $payment = $this->retrieve_payment($payment_id, $payplug);
        } catch (\Throwable $e) {
            $this->log_api_failure('retrieved', $order_id, $e);

            return false;
        }

        return empty($payment->is_paid) && !empty($payment->failure);
    }

    private function log_api_failure(string $failed_action, int $order_id, \Throwable $e): void
    {
        PayplugGateway::log(sprintf('Apple Pay: payment of order #%d could not be %s (%s: %s)', $order_id, $failed_action, get_class($e), $e->getMessage()), 'warning');
    }

    /**
     * Lines to add back to the cart once the order is deleted, without the lines whose variation no longer exists.
     *
     * @return array[] add_to_cart() arguments, one entry per order line
     */
    private function get_cart_lines_from_order(\WC_Order $order): array
    {
        $lines = [];
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            $variations = [];

            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation instanceof \WC_Product_Variation || !$variation->get_parent_id()) {
                    continue;
                }
                $variations = $variation->get_variation_attributes();
            }

            $lines[] = [$item->get_product_id(), $item->get_quantity(), $variation_id, $variations];
        }

        return $lines;
    }

    /**
     * @param array[] $lines
     */
    private function restore_cart(array $lines): void
    {
        foreach ($lines as list($product_id, $quantity, $variation_id, $variations)) {
            WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations);
        }
    }

    /**
     * HTTP 200 because the cart and product page JS have no fail branch. They read data.msg, the Blocks JS reads data.message.
     */
    private function send_rate_limited(): void
    {
        $message = __('Too many Apple Pay attempts. Please wait a few minutes and try again.', 'payplug');

        wp_send_json_error([
            'msg' => $message,
            'message' => $message,
        ]);
    }

    /**
     * The cart and product page JS read data.msg, the Blocks JS reads data.message.
     */
    private function send_order_not_created(): void
    {
        $message = __('Your order was cancelled.', 'woocommerce');

        wp_send_json_error([
            'msg' => $message,
            'message' => $message,
        ]);
    }

    private function send_order_kept(): void
    {
        wp_send_json_error([
            'message' => __('Invalid order.', 'payplug'),
        ]);
    }

    /**
     * The order is still there: do not tell the customer it was cancelled.
     */
    private function send_order_not_cancelled(): void
    {
        wp_send_json_error([
            'message' => __('Your order could not be cancelled.', 'payplug'),
        ]);
    }

    public function applepay_empty_cart(): void
    {
        try {
            WC()->cart->empty_cart();
            wp_send_json_success();
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Your order was cancelled.', 'woocommerce'),
            ]);
        }
    }

    public function applepay_add_to_cart(): void
    {
        $product_id = $this->get_posted_id('product_id') ?: $this->get_posted_id('product_variation_id');
        $quantity = $this->get_posted_quantity();
        if (!$product_id || $quantity < 1) {
            $this->send_order_not_created();

            return;
        }

        try {
            WC()->cart->add_to_cart($product_id, $quantity);
            $total = WC()->cart->total - WC()->cart->shipping_total;
        } catch (\Throwable $e) {
            $this->send_order_not_created();

            return;
        }

        wp_send_json_success([
            'total' => $total,
        ]);
    }

    /**
     * Positive integer posted under $key, 0 when absent or not a positive number.
     */
    private function get_posted_id(string $key): int
    {
        $value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash($_POST[$key]) : '';

        return is_numeric($value) && $value > 0 ? absint($value) : 0;
    }

    /**
     * 1 when no quantity is posted, 0 when the posted quantity is not a scalar.
     *
     * @return int|float
     */
    private function get_posted_quantity()
    {
        if (!isset($_POST['product_quantity']) || '' === $_POST['product_quantity']) {
            return 1;
        }

        return is_scalar($_POST['product_quantity']) ? wc_stock_amount(wp_unslash($_POST['product_quantity'])) : 0;
    }
}
