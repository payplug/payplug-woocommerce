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
            try {
                $checkout = WC()->checkout();

                $order_id = $checkout->create_order(['payment_method' => $apple_pay->id]);
                $order = wc_get_order($order_id);

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

                $packages = WC()->cart->get_shipping_packages();

                WC()->shipping()->reset_shipping();

                $order->calculate_taxes();

                $order->calculate_totals();

                $order->save();

                $cart->empty_cart();

                // After empty_cart(): emptying the cart clears order_awaiting_payment from the session.
                PayplugWoocommerceHelper::remember_order_awaiting_payment($order->get_id());

                $this->process_cart_payment($order, $apple_pay);
            } catch (\Exception $exception) {
                wp_send_json_error([
                    'message' => __('Your order was cancelled.', 'woocommerce'),
                ]);
            }
        } else {
            wp_send_json_error();
        }
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
            $this->refuse_order('update_applepay_order', $order_id);

            return;
        }

        $selected_shipping_method = !empty($_POST['shipping_method']) ? $_POST['shipping_method'] : null;
        if (!empty($_POST['shipping'])) {
            foreach ($_POST['shipping'] as $key => $data) {
                switch ($key) {
                    case 'familyName':
                        $order->set_shipping_last_name($data);
                        break;
                    case 'givenName':
                        $order->set_shipping_first_name($data);
                        break;
                    case 'country':
                        $order->set_shipping_country($data);
                        break;
                    case 'locality':
                        $order->set_shipping_city($data);
                        break;
                    case 'phoneNumber':
                        $order->set_shipping_phone($data);
                        break;
                    case 'postalCode':
                        $order->set_shipping_postcode($data);
                        break;
                    case 'addressLines':
                        $order->set_shipping_address_1($data[0]);
                        if (!empty($data[1])) {
                            $order->set_shipping_address_2($data[1]);
                        }
                        break;
                    case 'emailAddress':
                        $order->set_billing_email($data);
                        break;
                }
            }
        }
        if (!empty($_POST['billing'])) {
            foreach ($_POST['billing'] as $key => $data) {
                switch ($key) {
                    case 'familyName':
                        $order->set_billing_last_name($data);
                        break;
                    case 'givenName':
                        $order->set_billing_first_name($data);
                        break;
                    case 'addressLines':
                        $order->set_billing_address_1($data[0]);
                        if (!empty($data[1])) {
                            $order->set_billing_address_2($data[1]);
                        }
                        break;
                    case 'locality':
                        $order->set_billing_city($data);
                        break;
                    case 'country':
                        $order->set_billing_country($data);
                        break;
                    case 'postalCode':
                        $order->set_billing_postcode($data);
                        break;
                }
            }
        }

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

        wp_send_json($order);
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
            || '' === $payment_id
            || !hash_equals((string) $order->get_transaction_id(), $payment_id)
        ) {
            $this->refuse_order('update_applepay_payment', $order_id);

            return;
        }

        $applepay = new ApplePayGateway();
        $payment_token = $_POST['payment_token'] ?? null;

        $payment = $this->retrieve_payment($payment_id);
        if (PayplugWoocommerceHelper::get_payment_order_id($payment) !== $order_id) {
            $this->refuse_order('update_applepay_payment', $order_id);

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
        $payment_id = !empty($_POST['payment_id']) ? wc_clean(wp_unslash($_POST['payment_id'])) : '';
        $order = $order_id ? wc_get_order($order_id) : false;
        if (!PayplugWoocommerceHelper::session_owns_order($order)) {
            $this->refuse_order('applepay_cancel_order', $order_id);

            return;
        }

        if (!$order->has_status(['pending', 'failed'])) {
            $this->send_order_kept();

            return;
        }

        //if there's no payment created
        if ('' === $payment_id) {
            $this->restore_cart_from_order($order);
        } else {
            if (!hash_equals((string) $order->get_transaction_id(), $payment_id)) {
                $this->refuse_order('applepay_cancel_order', $order_id);

                return;
            }

            $payplug = $this->get_merchant_client();
            $payment = null;
            if ($payplug) {
                try {
                    $payment = $this->retrieve_payment($payment_id, $payplug);
                } catch (\Throwable $e) {
                    PayplugGateway::log(sprintf('Apple Pay: payment of order #%d could not be retrieved', $order_id), 'warning');
                }
            }

            if (!$payment) {
                $this->restore_cart_from_order($order);
                wp_send_json_error([
                    'message' => __('Your order was cancelled.', 'woocommerce'),
                ]);

                return;
            }

            if (PayplugWoocommerceHelper::get_payment_order_id($payment) !== $order_id) {
                $this->refuse_order('applepay_cancel_order', $order_id);

                return;
            }

            if (!empty($payment->is_paid)) {
                PayplugGateway::log(sprintf('Apple Pay: payment of order #%d may be paid, order kept', $order_id), 'warning');
                $this->send_order_kept();

                return;
            }

            $this->restore_cart_from_order($order);

            if (!$this->abort_payment($payment, $payment_id, $payplug, $order_id)) {
                PayplugGateway::log(sprintf('Apple Pay: payment of order #%d may be paid, order kept', $order_id), 'warning');
                $this->send_order_kept();

                return;
            }
        }

        if (!$order->delete(true)) {
            wp_send_json_error([
                'message' => __('Your order was cancelled.', 'woocommerce'),
            ]);

            return;
        }

        if (WC()->session && absint(WC()->session->get('order_awaiting_payment')) === $order_id) {
            WC()->session->set('order_awaiting_payment', null);
        }

        wp_send_json_success([
            'message' => __('Your order was cancelled.', 'woocommerce'),
        ]);
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
     * Client authenticated with the merchant key, null when no key is configured.
     */
    protected function get_merchant_client(): ?Payplug
    {
        $api_key = json_decode($this->options['api_key'] ?? '', true);
        $test_key = !empty($api_key['test']) ? $api_key['test'] : '';
        $live_key = !empty($api_key['live']) ? $api_key['live'] : '';

        if (empty($test_key) && empty($live_key)) {
            return null;
        }

        return new Payplug((bool) $this->options ? $live_key : $test_key);
    }

    /**
     * Aborts the payment. When the abort fails, true only if the payment is confirmed not paid.
     *
     * @param \Payplug\Resource\Payment $payment
     */
    private function abort_payment($payment, string $payment_id, Payplug $payplug, int $order_id): bool
    {
        try {
            $payment->abort($payplug);

            return true;
        } catch (\Throwable $e) {
            PayplugGateway::log(sprintf('Apple Pay: payment of order #%d could not be aborted', $order_id), 'warning');
        }

        try {
            return empty($this->retrieve_payment($payment_id, $payplug)->is_paid);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function restore_cart_from_order(\WC_Order $order): void
    {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            $variation_id = $item->get_variation_id();
            $variations = [];

            if ($variation_id) {
                $product = wc_get_product($variation_id);
                $variations = $product->get_variation_attributes();
            }

            WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations);
        }
    }

    private function send_order_kept(): void
    {
        wp_send_json_error([
            'message' => __('Invalid order.', 'payplug'),
        ]);
    }

    private function refuse_order(string $handler, int $order_id): void
    {
        PayplugGateway::log(sprintf('Apple Pay: %s refused for order #%d', $handler, $order_id), 'warning');
        PayplugWoocommerceHelper::send_order_forbidden();
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
        try {
            if (!empty($_POST['product_id'])) {
                $product_id = $_POST['product_id'];
            } else {
                $product_id = $_POST['product_variation_id'];
            }

            $product_quantity = !empty($_POST['product_quantity']) ? $_POST['product_quantity'] : 1;

            WC()->cart->add_to_cart($product_id, $product_quantity);
            wp_send_json_success([
                'total' => WC()->cart->total - WC()->cart->shipping_total,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Your order was cancelled.', 'woocommerce'),
            ]);
        }
    }
}
