<?php

namespace Payplug\PayplugWoocommerce\Gateway\PPRO;
use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class Scalapay extends PayplugGenericGateway
{
	protected $allowed_country_codes = [];
	protected $enable_refund = true;
	const ENABLE_ON_TEST_MODE = false;

	public function __construct()
	{

		parent::__construct();

		//since we're calling the parent construct we need to redefine the payment properties
		//once we detach the cc from default payment method, this will be no longer needed
		$this->id = 'scalapay';
		$this->method_title = __("pay_with_scalapay", "payplug");
		$this->title = __("pay_with_scalapay", "payplug");
		$this->method_description = "";
		$this->description = "";
		$this->image = 'scalapay.svg';

		//WOOCO FIELDS
		$this->has_fields = false;
		$this->enabled = 'yes';

		if (!$this->checkGateway()) {
			$this->enabled = 'no';
		}


		add_action('woocommerce_order_item_add_action_buttons', [$this, 'refund_not_available']);

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
            $country = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
            $phone   = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_phone : $order->get_billing_phone();
            $billing_email      = PayplugWoocommerceHelper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
            $phone_number_util = PhoneNumberUtil::getInstance();
            $phone_number      = $phone_number_util->parse( $phone, $country );
            if ( PhoneNumberType::MOBILE !== $phone_number_util->getNumberType( $phone_number ) ) {
                throw new \Exception(__('Mobile phone number fullfilled is invalid. Please retry.', 'payplug'));
            }

            if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL) || strpos($billing_email,'+') !== false) {
                throw new \Exception(__("Your email address is too long and the + character is not valid, please change it to another address (max 100 characters).", 'payplug'));
            }

            $address_data = PayplugAddressData::from_order($order);

	        $return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(substr( $return_url, 0, 4 ) === "http")) {
		        $return_url = get_site_url().$return_url;
	        }

            $cart_items = [];
            $items = $order->get_items();
            foreach($items as $item) {
                $data = $item->get_data();
                $total = floatval(round($data['total'], 2)) * 100;
                $cart_items[] = [
                    'delivery_label' => 'storepickup',
                    'delivery_type' => 'storepickup',
                    'brand' => 'Woocommerce',
                    'merchant_item_id' => 'cart-'.$data['id'].'-'.$data['product_id'],
                    'name' => $data['name'],
                    'expected_delivery_date' => date('Y-m-d', strtotime('+1 week')),
                    'total_amount' => (int) $total,
                    'price' =>  round($total / $data['quantity']),
                    'quantity' =>  $data['quantity']
                ];
            }

            $payment_data = [
                'amount' => $amount,
                'currency'         => get_woocommerce_currency(),
                'payment_method'   => $this->id,
                'billing'          => $address_data->get_billing(),
                'shipping'         => $address_data->get_shipping(),
                'payment_context'   => [
                    "cart" => $cart_items
                ],
                'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
                'hosted_payment'   => [
                    'return_url' => $return_url,
                    'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
                ],
                'metadata'         => [
                    'order_id'    => $order_id,
                    'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
                    'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
                ],
            ];

	        if (PayplugWoocommerceHelper::is_checkout_block() && is_checkout()) {
		        $payment_data['metadata']['woocommerce_block'] = "CHECKOUT";

	        } elseif (PayplugWoocommerceHelper::is_cart_block() && is_cart()) {
		        $payment_data['metadata']['woocommerce_block'] = "CART";
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
            $payment      = $this->payplug_api->payment_create($payment_data);

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
        } catch (\HttpException $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
            throw new \Exception(__($e->getMessage(), 'payplug'));
        } catch (\Exception $e) {
            PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
            throw new \Exception(__($e->getMessage(), 'payplug'));
        }
    }
}

