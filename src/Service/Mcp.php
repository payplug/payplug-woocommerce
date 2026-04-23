<?php

namespace Payplug\PayplugWoocommerce\Service;

use Payplug\PayplugWoocommerce\Traits\ServiceGetter;
use PayplugPluginCore\Models\Entities\PaymentInputDTO;

if (!defined('ABSPATH')) {
	exit;
}

class Mcp
{
	use ServiceGetter;


	/**
	 * @description Create a PaymentInputDTO from given parameters
	 * @param array $params
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
		} catch (\Exception $e) {
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
	 * Creates a payment link for a customer.
	 *
	 * @param array $customer Customer information
	 * @param array $cart Cart with products
	 * @return array Result with payment URL or error
	 */
	public function createByLink(array $customer, array $cart)
	{
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
			];
		}

		// Add products to the order
		if (!empty($cart['products'])) {
			foreach ($cart['products'] as $product) {
				$product_id = (int) $product['product_id'];
				$qty = (int) $product['qty'];
				$variation_id = isset($product['variation_id']) ? (int) $product['variation_id'] : 0;
				$variation = isset($product['variation']) ? $product['variation'] : [];

				$wc_product = wc_get_product($product_id);

				if (!$wc_product) {
					return [
						'result' => false,
						'code' => 404,
						'message' => "Product with ID $product_id not found.",
					];
				}

				// Check if product is variable and needs variation
				if ($wc_product->is_type('variable') && empty($variation_id)) {
					return [
						'result' => false,
						'code' => 400,
						'message' => "Product '$product_id' is a variable product but no variation_id was provided. Please select a variation.",
					];
				}

				$order->add_product($wc_product, $qty, [
					'variation_id' => $variation_id,
					'variation' => $variation,
				]);
			}
		}

		// Set billing address
		$order->set_billing_first_name($customer['customer_address_first_name']);
		$order->set_billing_last_name($customer['customer_address_last_name']);
		$order->set_billing_email($customer['customer_address_email']);
		$order->set_billing_phone($customer['customer_address_mobile_phone_number']);
		$order->set_billing_address_1($customer['customer_address_address1']);
		$order->set_billing_address_2(isset($customer['customer_address_address2']) ? $customer['customer_address_address2'] : '');
		$order->set_billing_city($customer['customer_address_city']);
		$order->set_billing_postcode($customer['customer_address_postcode']);
		$order->set_billing_country($customer['customer_address_country']);

		// Set shipping address (same as billing)
		$order->set_shipping_first_name($customer['customer_address_first_name']);
		$order->set_shipping_last_name($customer['customer_address_last_name']);
		$order->set_shipping_address_1($customer['customer_address_address1']);
		$order->set_shipping_address_2(isset($customer['customer_address_address2']) ? $customer['customer_address_address2'] : '');
		$order->set_shipping_city($customer['customer_address_city']);
		$order->set_shipping_postcode($customer['customer_address_postcode']);
		$order->set_shipping_country($customer['customer_address_country']);

		// Set payment method to PayPlug so IPN works correctly
		$order->set_payment_method('payplug');
		$order->set_payment_method_title('PayPlug');

		// Calculate totals
		$order->calculate_totals();
		$order->save();

		$order_total = $order->get_total();
		$currency = $order->get_currency();

		// Prepare DTO parameters
		$dto_params = [
			'order_id' => $order->get_id(),
			'amount' => (int) ($order_total * 100),
			'currency_iso_code' => $currency,
			'customer' => [
				'identifier' => $customer['customer_id'],
				'billing' => [
					'title' => isset($customer['customer_address_title']) ? $customer['customer_address_title'] : '',
					'first_name' => $customer['customer_address_first_name'],
					'last_name' => $customer['customer_address_last_name'],
					'mobile_phone_number' => $customer['customer_address_mobile_phone_number'],
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
					'mobile_phone_number' => $customer['customer_address_mobile_phone_number'],
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
			];
		}

		try {

			$payment_action = new \PayplugPluginCore\Actions\PaymentAction();
			$payment_object = $payment_action->createAction($dto);

			$resource = $payment_object->getResource();

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
				'payment_url' => $resource->hosted_payment->payment_url,
			];

		} catch (\Exception $e) {
			return [
				'result' => false,
				'code' => 500,
				'message' => 'PayPlug API error: ' . $e->getMessage(),
			];
		}
	}

}

