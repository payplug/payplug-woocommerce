<?php

namespace Payplug\PayplugWoocommerce\Gateway\PPRO;

use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use PayplugUnifiedCore\Utilities\Helpers\AmountHelper;
use PayplugUnifiedCore\Utilities\Helpers\PhoneHelper;

class Scalapay extends PayplugGenericGateway
{
    protected $allowed_country_codes = [];
    protected $enable_refund = true;
    public const ENABLE_ON_TEST_MODE = false;

    /**
     * Last-resort fallback bounds, in cents. Used only when neither the account's live API
     * amounts nor the stored scalapay.default_amounts are available - e.g. an options array
     * that predates that key, since Configuration loads the raw stored options without
     * merging the schema defaults. Mirrors Configuration::$expected_fields'
     * payment_methods.configuration.scalapay.default_amounts.
     */
    public const DEFAULT_MIN_AMOUNT = 500;
    public const DEFAULT_MAX_AMOUNT = 400000;

    public function __construct()
    {
        parent::__construct();

        //since we're calling the parent construct we need to redefine the payment properties
        //once we detach the cc from default payment method, this will be no longer needed
        $this->id = 'scalapay';
        $this->method_title = __('pay_with_scalapay', 'payplug');
        $this->title = __('pay_with_scalapay', 'payplug');
        $this->method_description = '';
        $this->description = '';
        $this->image = 'scalapay.svg';

        //WOOCO FIELDS
        $this->has_fields = false;
        $this->enabled = 'yes';

        if (!$this->checkGateway()) {
            $this->enabled = 'no';
        }

        add_action('woocommerce_order_item_add_action_buttons', [$this, 'refund_not_available']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout'], 10);
    }

    /**
     * Server-side guard mirroring check_gateway(): a stale/bypassed client could still submit
     * an order outside the merchant's configured (or account-authorized) amount range.
     *
     * @throws \Exception
     */
    public function validate_checkout(): void
    {
        $posted_data = $this->get_post_data();

        if (($posted_data['payment_method'] ?? '') !== $this->id) {
            return;
        }

        [$min_cents, $max_cents] = self::effective_bounds(
            $this->settings['payment_methods']['configuration']['scalapay'] ?? [],
            $this->settings['payment_methods']['permissions']['scalapay']['amounts'] ?? '{}'
        );

        // Compared in euros rather than multiplying the total by 100: a float cents value
        // such as 19.99 * 100 => 1998.9999999999998 can fall the wrong side of an exact
        // boundary, whereas dividing the (integer) bound by 100 yields the same double as
        // the parsed total. Same direction as PayplugGatewayOney3x's amount checks.
        $order_total = (float) $this->get_order_total();
        if ($order_total < $min_cents / 100 || $order_total > $max_cents / 100) {
            throw new \Exception(sprintf(__('The total amount of your order should be between %s€ and %s€ to pay with Scalapay.', 'payplug'), $min_cents / 100, $max_cents / 100));
        }
    }

    /**
     * Remove Scalapay from the available gateways if the order/cart amount is outside the
     * merchant's configured bounds (or the account's authorized range, if unconfigured).
     *
     * @param array $gateways
     *
     * @return array
     */
    public function check_gateway($gateways)
    {
        if (isset($gateways[$this->id]) && $gateways[$this->id]->id == $this->id) {
            [$min_cents, $max_cents] = self::effective_bounds(
                $this->settings['payment_methods']['configuration']['scalapay'] ?? [],
                $this->settings['payment_methods']['permissions']['scalapay']['amounts'] ?? '{}'
            );

            // Euro comparison, for the float-precision reason spelled out in validate_checkout().
            $order_total = (float) $this->get_order_total();
            if ($order_total < $min_cents / 100 || $order_total > $max_cents / 100) {
                unset($gateways[$this->id]);
            }
        }

        return parent::check_gateway($gateways);
    }

    /**
     * The account's PayPlug-authorized range, in cents: the live API amounts when available,
     * otherwise the stored default_amounts fallback. This is the ceiling the merchant may
     * only narrow, never widen - shared with the BO display (PaymentMethods.php) and the
     * save-time validator's bounds (Ajax.php) so the three can't silently drift apart.
     *
     * @param array $config payment_methods.configuration.scalapay (default_amounts)
     * @param string $api_amounts payment_methods.permissions.scalapay.amounts, JSON-encoded
     *                            {"min":{"EUR":n},"max":{"EUR":n}}
     *
     * @return array{0: int, 1: int} [min_cents, max_cents]
     */
    public static function authorized_bounds($config, $api_amounts)
    {
        $authorized = json_decode($api_amounts ?? '{}', true);
        $min = is_array($authorized) ? ($authorized['min']['EUR'] ?? null) : null;
        $max = is_array($authorized) ? ($authorized['max']['EUR'] ?? null) : null;

        if (null === $min || null === $max) {
            $fallback = json_decode($config['default_amounts'] ?? '', true);
            $fallback = is_array($fallback) ? $fallback : [];
            $min = $min ?? ($fallback['min'] ?? self::DEFAULT_MIN_AMOUNT);
            $max = $max ?? ($fallback['max'] ?? self::DEFAULT_MAX_AMOUNT);
        }

        return [(int) $min, (int) $max];
    }

    /**
     * The merchant's configured min/max (when set) narrowed against the account's live
     * PayPlug-authorized range - shared with the BO settings display (PaymentMethods.php)
     * so both stay in sync by construction.
     *
     * @param array $config payment_methods.configuration.scalapay (custom_amounts/default_amounts)
     * @param string $api_amounts payment_methods.permissions.scalapay.amounts, JSON-encoded
     *                            {"min":{"EUR":n},"max":{"EUR":n}}
     *
     * @return array{0: int, 1: int} [min_cents, max_cents]
     */
    public static function effective_bounds($config, $api_amounts)
    {
        $custom_amounts = json_decode($config['custom_amounts'] ?? '{}', true);
        $custom_amounts = is_array($custom_amounts) ? $custom_amounts : [];

        [$authorized_min, $authorized_max] = self::authorized_bounds($config, $api_amounts);

        // !empty() rather than isset(): a stored 0 is not a meaningful Scalapay threshold,
        // so it falls back to the account bound instead of being read as an override.
        $min = !empty($custom_amounts['min']) ? (int) $custom_amounts['min'] : $authorized_min;
        $max = !empty($custom_amounts['max']) ? (int) $custom_amounts['max'] : $authorized_max;

        return [$min, $max];
    }

    /**
     * @param \WC_Order $order
     * @param int $amount
     * @param int $customer_id
     *
     * @throws \Exception
     *
     * @return array
     */
    public function process_standard_payment($order, $amount, $customer_id)
    {
        $order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
        try {
            $country = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
            $phone = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_phone : $order->get_billing_phone();
            $billing_email = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
            if (!PhoneHelper::isMobile($phone, $country)) {
                throw new \Exception(__('Mobile phone number fullfilled is invalid. Please retry.', 'payplug'));
            }

            if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL) || strpos($billing_email, '+') !== false) {
                throw new \Exception(__('Your email address is too long and the + character is not valid, please change it to another address (max 100 characters).', 'payplug'));
            }

            $address_data = PayplugAddressData::from_order($order);

            $return_url = esc_url_raw($order->get_checkout_order_received_url());

            if (!(substr($return_url, 0, 4) === 'http')) {
                $return_url = get_site_url() . $return_url;
            }

            $cart_items = [];
            $items = $order->get_items();
            foreach ($items as $item) {
                $data = $item->get_data();
                $total = AmountHelper::toCents((float) $data['total']);
                $cart_items[] = [
                    'delivery_label' => 'storepickup',
                    'delivery_type' => 'storepickup',
                    'brand' => 'Woocommerce',
                    'merchant_item_id' => 'cart-' . $data['id'] . '-' . $data['product_id'],
                    'name' => $data['name'],
                    'expected_delivery_date' => date('Y-m-d', strtotime('+1 week')),
                    'total_amount' => $total,
                    'price' => (int) round($total / $data['quantity']),
                    'quantity' => $data['quantity'],
                ];
            }

            $payment_data = [
                'amount' => $amount,
                'currency' => get_woocommerce_currency(),
                'payment_method' => $this->id,
                'billing' => $address_data->get_billing(),
                'shipping' => $address_data->get_shipping(),
                'payment_context' => [
                    'cart' => $cart_items,
                ],
                'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
                'hosted_payment' => [
                    'return_url' => $return_url,
                    'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
                ],
                'metadata' => [
                    'order_id' => $order_id,
                    'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
                    'domain' => $this->limit_length(esc_url_raw(home_url()), 500),
                ],
            ];

            if (PayplugWoocommerceHelper::is_checkout_block() && is_checkout()) {
                $payment_data['metadata']['woocommerce_block'] = 'CHECKOUT';
            } elseif (PayplugWoocommerceHelper::is_cart_block() && is_cart()) {
                $payment_data['metadata']['woocommerce_block'] = 'CART';
            }

            /**
             * Filter the payment data before it's used
             *
             * @param array $payment_data
             * @param int $order_id
             * @param array $customer_details
             * @param PayplugAddressData $address_data
             */
            $payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $order_id, [], $address_data);
            $payment = $this->payplug_api->payment_create($payment_data);

            // Save transaction id for the order
            PayplugWoocommerceHelper::is_pre_30()
                ? update_post_meta($order_id, '_transaction_id', $payment->id)
                : $order->set_transaction_id($payment->id);

            if (is_callable([$order, 'save'])) {
                $order->save();
            }

            /**
             * Fires once a payment has been created.
             *
             * @param int $order_id Order ID
             * @param PaymentResource $payment Payment resource
             */
            \do_action('payplug_gateway_payment_created', $order_id, $payment);

            $metadata = PayplugWoocommerceHelper::extract_transaction_metadata($payment);
            PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

            PayplugGateway::log(sprintf('Payment creation complete for order #%s', $order_id));

            return [
                'result' => 'success',
                'redirect' => $payment->hosted_payment->payment_url,
                'cancel' => $payment->hosted_payment->cancel_url,
            ];
        } catch (\HttpException $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
            throw new \Exception(__($e->getMessage(), 'payplug'));
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
            throw new \Exception(__($e->getMessage(), 'payplug'));
        }
    }
}
