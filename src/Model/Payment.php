<?php

namespace Payplug\PayplugWoocommerce\Model;

use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class Payment {

	public $order;
	public $payment_method = '';
	public $amount = 0;
	public $currency = 'EUR';
	public $billing = '';
	public $shipping = '';
	public $hosted_payment = array('return_url' => '', 'cancel_url' => '');
	public $notification_url = '';
	public $metadata = array('order_id'=> '', 'customer_id' => '', 'domain' => '');

	/* for Oney payments */
	public $authorized_amount = 0;
	public $auto_capture = true;
	public $payment_context = array('cart' => array());

	/* for Bancontact payments */
	public $save_card = false;
	public $force_3ds = false;

	/**
	 * Model Payment Construct.
	 *
	 * @param string $payment_method_id
	 * @param \WC_Order $order
	 * @param int $customer_id
	 * @param int $amount
	 * @param array $cart_items
	 *
	 * @return void
	 */
	public function __construct($payment_method_id, $order, $customer_id, $amount, $cart_items = array()) {
		$this->order = $order;
		$this->payment_method = $payment_method_id;
		$this->amount = $amount;
		$this->currency = get_woocommerce_currency();
		$address_data = PayplugAddressData::from_order($this->order);
		$this->billing = $address_data->get_billing();
		$this->shipping = $address_data->get_shipping();
		$this->hosted_payment = [
			'return_url' => esc_url_raw($this->order->get_checkout_order_received_url()),
			'cancel_url' => esc_url_raw($this->order->get_cancel_order_url_raw()),
		];
		$this->notification_url = esc_url_raw(WC()->api_request_url('PayplugGateway'));
		$this->metadata = [
			'order_id'    => PayplugWoocommerceHelper::is_pre_30() ? $this->order->id : $this->order->get_id(),
			'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
			'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
		];

		/* for Oney payments */
		if($payment_method_id != 'payplug' and $payment_method_id != 'bancontact'){
			$this->authorized_amount = $amount;
			$this->auto_capture = true;
			$this->payment_context = array('cart' => $cart_items);
		}

		/* for Bancontact payments */
		if($payment_method_id == 'bancontact'){
			$this->save_card = false;
			$this->force_3ds = false;
		}
	}

	/**
	 * Limit string length.
	 *
	 * @param string $value
	 * @param int $maxlength
	 *
	 * @return string
	 */
	public function limit_length($value, $maxlength = 100)
	{
		return (strlen($value) > $maxlength) ? substr($value, 0, $maxlength) : $value;
	}

	/**
	 * convert payment opbject to Array for \Payplug\Payment::create.
	 *
	 * @return array
	 */
	public function data(){
		$payment_data = [
			'payment_method' => $this->payment_method,
			'currency' => $this->currency,
			'billing' => $this->billing,
			'shipping' => $this->shipping,
			'hosted_payment' => $this->hosted_payment,
			'notification_url' => $this->notification_url,
			'metadata' => $this->metadata
			];

		/* for Oney payments */
		if($this->payment_method != 'payplug' and $this->payment_method != 'bancontact'){
			$payment_data['authorized_amount'] = $this->authorized_amount;
			$payment_data['auto_capture'] = $this->auto_capture;
			$payment_data['payment_context'] = $this->payment_context;
		}else{
			$payment_data['amount'] = $this->amount;
		}

		/* for Bancontact payments */
		if($this->payment_method == 'bancontact'){
			$payment_data['save_card'] = $this->save_card;
			$payment_data['force_3ds'] = $this->force_3ds;
		}

		/**
		 * Filter the payment data before it's used
		 *
		 * @param array $payment_data
		 * @param int $order_id
		 * @param array $customer_details
		 * @param PayplugAddressData $address_data
		 */
		$address_data = PayplugAddressData::from_order($this->order);
		$payment_data = apply_filters('payplug_gateway_payment_data', $payment_data, $this->metadata['order_id'], [], $address_data);
		return $payment_data;
	}

}