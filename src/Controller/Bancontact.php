<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Model\Payment;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;

class Bancontact extends PayplugGateway
{

	public function __construct()
	{

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id                 = 'bancontact';

		/** @var \WC_Payment_Gateway overwrite for bancontact settings  */
		$this->method_title       = __('payplug_bancontact_title', 'payplug');
		$this->method_description = __('payplug_bancontact_description', 'payplug');

		$this->title = __('payplug_bancontact_title', 'payplug');
		$this->description = __('payplug_bancontact_description', 'payplug');

		if(!$this->checkBancontact())
			$this->enabled = 'no';

	}

	private function checkBancontact(){
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if (isset($account['payment_methods']['bancontact']['enabled'])) {
			return  $account['payment_methods']['bancontact']['enabled'];
		}
		return true;
	}

	/**
	 * Get payment icons.
	 *
	 * @return string
	 */
	public function get_icon()
	{
		$available_img = 'lg-bancontact-checkout.png';
		$icons = apply_filters('payplug_payment_icons', [
			'payplug' => sprintf('<img src="%s" alt="Oney 4x" class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/' . $available_img)),
		]);
		$icons_str = '';
		foreach ($icons as $icon) {
			$icons_str .= $icon;
		}
		return $icons_str;
	}

	/**
	 * @param \WC_Order $order
	 * @param int $amount
	 * @param int $customer_id
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function process_standard_payment($order, $amount, $customer_id)
	{
		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		try {
			if (!$this->checkBancontact()) {
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			}

			$payment = new Payment($this->id, $order, $customer_id, $amount);
			$payment      = $this->api->payment_create($payment->data());

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
				'result'   => 'success',
				'redirect' => $payment->hosted_payment->payment_url,
				'cancel'   => $payment->hosted_payment->cancel_url,
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
