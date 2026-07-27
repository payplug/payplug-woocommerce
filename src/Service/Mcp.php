<?php

namespace Payplug\PayplugWoocommerce\Service;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use PayPlugPluginMcp\Models\Entities\PaymentInputDTO;

if (!defined('ABSPATH')) {
    exit;
}

class Mcp
{
    use ServiceGetter;

    /**
     * @description Create a PaymentInputDTO from given parameters
     *
     * @param array $params
     *
     * @return array
     */
    protected function createPaymentInputDto(array $params)
    {
        if (!$params || !is_array($params)) {
            return [
                'result' => false,
                'code' => null,
                'message' => 'Wrong $params given',
                'dto' => null,
            ];
        }

        try {
            $attributes = $this->formatMCPAttributes($params);
            $dto = PaymentInputDTO::create($attributes);

            return [
                'result' => true,
                'code' => 200,
                'message' => 'DTO created',
                'dto' => $dto,
            ];
        } catch (\Throwable $e) {
            return [
                'result' => false,
                'code' => (int) $e->getCode(),
                'message' => $e->getMessage(),
                'dto' => null,
            ];
        }
    }

    /**
     * @param array $attributes
     *
     * @return array
     */
    protected function formatMCPAttributes(array $attributes)
    {
        $attributes['payment_method'] = 'email_link';

        // Get API key using ServiceGetter trait
        $api_key = $this->get_api()->get_bearer_token();
        $attributes['api_bearer'] = $api_key;

        // Build URLs for WooCommerce
        $attributes['urls'] = [
            'return' => esc_url_raw(add_query_arg('utm_nooverride', '1', wc_get_checkout_url())),
            'cancel' => esc_url_raw(wc_get_checkout_url()),
            'notification' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
        ];

        // Set metadata
        $attributes['metadata'] = array_merge(
            isset($attributes['metadata']) ? $attributes['metadata'] : [],
            [
                'order_id' => isset($attributes['order_id']) ? $attributes['order_id'] : '',
                'customer_id' => isset($attributes['customer']['identifier']) ? $attributes['customer']['identifier'] : 'guest',
                'source' => 'MCP Payplug WooCommerce',
                'domain' => esc_url_raw(home_url()),
            ]
        );
        $attributes['context'] = [];

        return $attributes;
    }

    /**
     * Validates customer and cart data that don't require a WooCommerce order to exist.
     *
     * @param array $customer Customer information
     * @param array $cart Cart with products
     *
     * @return array|null Error response array if invalid, null if valid.
     */
    protected function validateCreateByLinkParams(array $customer, array $cart)
    {
        if (!empty($customer['customer_address_email']) && !is_email($customer['customer_address_email'])) {
            return $this->invalidParamError(400, "Invalid email address: '{$customer['customer_address_email']}'.");
        }

        foreach (!empty($cart['products']) ? $cart['products'] : [] as $product) {
            $product_id = (int) $product['product_id'];
            $wc_product = wc_get_product($product_id);

            if (!$wc_product) {
                return $this->invalidParamError(404, "Product with ID $product_id not found.");
            }

            $variation_id = isset($product['variation_id']) ? (int) $product['variation_id'] : 0;
            if ($wc_product->is_type('variable') && empty($variation_id)) {
                return $this->invalidParamError(
                    400,
                    "Product '$product_id' is a variable product but no variation_id was provided. Please select a variation."
                );
            }
        }

        return null;
    }

    /**
     * Normalizes a phone number to E.164 format, using the same libphonenumber-based
     * logic as the classic checkout flow (see PayplugAddressData::prepare_address_data()).
     *
     * @param mixed $phone_number
     * @param mixed $country ISO country code (e.g. "FR"), used to interpret local formats.
     *
     * @return string|null The normalized E.164 phone number, or null if it can't be validated.
     */
    protected function normalizePhoneNumber($phone_number, $country)
    {
        if (!is_string($phone_number) || !is_string($country) || '' === $country) {
            return null;
        }

        try {
            $phone_number_util = PhoneNumberUtil::getInstance();
            $parsed_number = $phone_number_util->parse($phone_number, $country);

            if (!$phone_number_util->isValidNumber($parsed_number)) {
                return null;
            }

            return $phone_number_util->format($parsed_number, PhoneNumberFormat::E164);
        } catch (NumberParseException $e) {
            return null;
        }
    }

    /**
     * @param int $code
     * @param string $message
     *
     * @return array
     */
    protected function invalidParamError($code, $message)
    {
        return [
            'result' => false,
            'code' => $code,
            'message' => $message,
            'order_id' => null,
            'resource_id' => null,
            'payment_url' => null,
        ];
    }

    /**
     * Creates a payment link for a customer.
     *
     * @param array $customer Customer information
     * @param array $cart Cart with products
     *
     * @return array Result with payment URL or error
     */
    public function createByLink(array $customer, array $cart)
    {
        if (!empty($customer['customer_address_mobile_phone_number'])) {
            $normalized_phone = $this->normalizePhoneNumber(
                $customer['customer_address_mobile_phone_number'],
                isset($customer['customer_address_country']) ? $customer['customer_address_country'] : ''
            );

            if (null === $normalized_phone) {
                return $this->invalidParamError(
                    400,
                    "Invalid phone number '{$customer['customer_address_mobile_phone_number']}' for country '{$customer['customer_address_country']}'."
                );
            }

            $customer['customer_address_mobile_phone_number'] = $normalized_phone;
        }

        $validation_error = $this->validateCreateByLinkParams($customer, $cart);
        if (null !== $validation_error) {
            return $validation_error;
        }

        // Create a new WooCommerce order
        $order = wc_create_order([
                     'customer_id' => isset($customer['customer_id']) ? (int) $customer['customer_id'] : 0,
                     'status' => 'pending',
                 ]);

        if (is_wp_error($order)) {
            return [
                'result' => false,
                'code' => 500,
                'message' => 'Failed to create order: ' . $order->get_error_message(),
                'order_id' => null,
                'resource_id' => null,
                'payment_url' => null,
            ];
        }

        // Add products to the order (already validated in validateCreateByLinkParams)
        if (!empty($cart['products'])) {
            foreach ($cart['products'] as $product) {
                $qty = (int) $product['qty'];
                $variation_id = isset($product['variation_id']) ? (int) $product['variation_id'] : 0;
                $variation = isset($product['variation']) ? $product['variation'] : [];

                $order->add_product(wc_get_product((int) $product['product_id']), $qty, [
                    'variation_id' => $variation_id,
                    'variation' => $variation,
                ]);
            }
        }

        // Set billing address
        $order->set_billing_first_name(isset($customer['customer_address_first_name']) ? wc_clean($customer['customer_address_first_name']) : '');
        $order->set_billing_last_name(isset($customer['customer_address_last_name']) ? wc_clean($customer['customer_address_last_name']) : '');
        $order->set_billing_email(isset($customer['customer_address_email']) ? sanitize_email($customer['customer_address_email']) : '');
        $order->set_billing_phone(isset($customer['customer_address_mobile_phone_number']) ? wc_clean($customer['customer_address_mobile_phone_number']) : '');
        $order->set_billing_address_1(isset($customer['customer_address_address1']) ? wc_clean($customer['customer_address_address1']) : '');
        $order->set_billing_address_2(isset($customer['customer_address_address2']) ? wc_clean($customer['customer_address_address2']) : '');
        $order->set_billing_city(isset($customer['customer_address_city']) ? wc_clean($customer['customer_address_city']) : '');
        $order->set_billing_postcode(isset($customer['customer_address_postcode']) ? wc_clean($customer['customer_address_postcode']) : '');
        $order->set_billing_country(isset($customer['customer_address_country']) ? wc_clean($customer['customer_address_country']) : '');

        // Set shipping address (same as billing)
        $order->set_shipping_first_name(isset($customer['customer_address_first_name']) ? wc_clean($customer['customer_address_first_name']) : '');
        $order->set_shipping_last_name(isset($customer['customer_address_last_name']) ? wc_clean($customer['customer_address_last_name']) : '');
        $order->set_shipping_address_1(isset($customer['customer_address_address1']) ? wc_clean($customer['customer_address_address1']) : '');
        $order->set_shipping_address_2(isset($customer['customer_address_address2']) ? wc_clean($customer['customer_address_address2']) : '');
        $order->set_shipping_city(isset($customer['customer_address_city']) ? wc_clean($customer['customer_address_city']) : '');
        $order->set_shipping_postcode(isset($customer['customer_address_postcode']) ? wc_clean($customer['customer_address_postcode']) : '');
        $order->set_shipping_country(isset($customer['customer_address_country']) ? wc_clean($customer['customer_address_country']) : '');

        // Set payment method to PayPlug so IPN works correctly
        $order->set_payment_method('payplug');
        $order->set_payment_method_title(__('PayPlug', 'payplug'));

        // Calculate totals
        $order->calculate_totals();
        $order->save();

        $order_total = $order->get_total();
        $currency = $order->get_currency();

        // Prepare DTO parameters
        $dto_params = [
            'order_id' => $order->get_id(),
            'amount' => PayplugWoocommerceHelper::get_payplug_amount($order_total),
            'currency_iso_code' => $currency,
            'customer' => [
                'identifier' => $customer['customer_id'],
                'billing' => [
                    'title' => isset($customer['customer_address_title']) ? $customer['customer_address_title'] : '',
                    'first_name' => $customer['customer_address_first_name'],
                    'last_name' => $customer['customer_address_last_name'],
                    'mobile_phone_number' => isset($customer['customer_address_mobile_phone_number']) ? $customer['customer_address_mobile_phone_number'] : '',
                    'email' => $customer['customer_address_email'],
                    'address1' => $customer['customer_address_address1'],
                    'address2' => isset($customer['customer_address_address2']) ? $customer['customer_address_address2'] : '',
                    'postcode' => $customer['customer_address_postcode'],
                    'city' => $customer['customer_address_city'],
                    'country' => $customer['customer_address_country'],
                    'language' => $customer['customer_address_language'],
                ],
                'shipping' => [
                    'title' => isset($customer['customer_address_title']) ? $customer['customer_address_title'] : '',
                    'first_name' => $customer['customer_address_first_name'],
                    'last_name' => $customer['customer_address_last_name'],
                    'mobile_phone_number' => isset($customer['customer_address_mobile_phone_number']) ? $customer['customer_address_mobile_phone_number'] : '',
                    'email' => $customer['customer_address_email'],
                    'address1' => $customer['customer_address_address1'],
                    'address2' => isset($customer['customer_address_address2']) ? $customer['customer_address_address2'] : '',
                    'postcode' => $customer['customer_address_postcode'],
                    'city' => $customer['customer_address_city'],
                    'country' => $customer['customer_address_country'],
                    'language' => $customer['customer_address_language'],
                ],
            ],
        ];

        $dtoResult = $this->createPaymentInputDto($dto_params);

        if (!$dtoResult['result'] || !$dtoResult['dto']) {
            return [
                'result' => false,
                'code' => $dtoResult['code'],
                'message' => $dtoResult['message'],
                'order_id' => null,
                'resource_id' => null,
                'payment_url' => null,
            ];
        }

        /** @var PaymentInputDTO $dto */
        $dto = $dtoResult['dto'];

        // Check if API key is configured
        if (empty($dto->getApiBearer())) {
            return [
                'result' => false,
                'code' => 401,
                'message' => 'PayPlug API key is not configured. Please configure the PayPlug plugin settings in WooCommerce.',
                'order_id' => null,
                'resource_id' => null,
                'payment_url' => null,
            ];
        }

        try {
            $payment_action = new \PayPlugPluginMcp\Actions\PaymentAction();
            $payment_object = $payment_action->createAction($dto);

            $resource = $payment_object->getResource();

            if (!$payment_object->getResult() || !$resource || empty($resource->id)) {
                $order->update_status('failed', __('Payplug payment link creation failed.', 'payplug'));

                return [
                    'result' => false,
                    'code' => $payment_object->getCode() ? (int) $payment_object->getCode() : 500,
                    'message' => $payment_object->getMessage() ?: __('Payment processing failed. Please retry.', 'payplug'),
                    'order_id' => $order->get_id(),
                    'resource_id' => null,
                    'payment_url' => null,
                ];
            }

            // Save transaction id for the order
            $order->set_transaction_id($resource->id);
            $order->update_meta_data('_payplug_payment_id', $resource->id);

            $metadata = \Payplug\PayplugWoocommerce\PayplugWoocommerceHelper::extract_transaction_metadata($resource);
            \Payplug\PayplugWoocommerce\PayplugWoocommerceHelper::save_transaction_metadata($order, $metadata);

            $order->add_order_note(sprintf(
                __('Payplug payment link created. Payment ID: %s', 'payplug'),
                $resource->id
            ));
            $order->save();

            \do_action('payplug_gateway_payment_created', $order->get_id(), $resource);

            return [
                'result' => true,
                'code' => 200,
                'message' => 'Order and payment created successfully.',
                'order_id' => $order->get_id(),
                'resource_id' => $resource->id,
                'payment_url' => !empty($resource->hosted_payment->payment_url) ? $resource->hosted_payment->payment_url : null,
            ];
        } catch (\Throwable $e) {
            PayplugGateway::log(
                sprintf('MCP error while processing order #%s : %s', $order->get_id(), $e->getMessage()),
                'error'
            );

            $order->update_status('failed', __('Payplug payment link creation failed.', 'payplug'));

            return [
                'result' => false,
                'code' => 500,
                'message' => __('Payment processing failed. Please retry.', 'payplug'),
                'order_id' => $order->get_id(),
                'resource_id' => null,
                'payment_url' => null,
            ];
        }
    }
}
