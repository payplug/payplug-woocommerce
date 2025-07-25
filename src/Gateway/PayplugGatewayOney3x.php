<?php

namespace Payplug\PayplugWoocommerce\Gateway;

use Payplug\PayplugWoocommerce\Controller\PayplugGenericGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Authentication;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPlug WooCommerce Gateway.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class PayplugGatewayOney3x extends PayplugGenericGateway
{
    const ONEY_UNAVAILABLE_CODE_COUNTRY_NOT_ALLOWED = 2;
    const ONEY_UNAVAILABLE_CODE_CART_SIZE_TOO_HIGH = 3;
	const ONEY_DISALBE_CHECKOUT_OPTIONS = 4;

    const ONEY_PRODUCT_QUANTITY_MAXIMUM = 1000;

    protected $oney_response;
	const ENABLE_ON_TEST_MODE = true;

    public $allowed_country_codes = [];

    public function __construct()
    {
        parent::__construct();
        $this->id                 = 'oney_x3_with_fees';
        $this->method_title       = _x('PayPlug Oney 3x', 'Gateway method title', 'payplug');
        $this->method_description = __('Enable PayPlug Oney 3x for your customers.', 'payplug');
        $this->title              = __('Pay by card in 3x with Oney', 'payplug');
		$this->has_fields = false;

        add_action('woocommerce_order_item_add_action_buttons', [$this, 'oney_refund_text']);
		add_action('woocommerce_after_checkout_validation', [$this, 'validate_checkout'], 10);

        self::set_oney_configuration();

		if (is_checkout()) {
			if ($this->check_oney_is_available() === self::ONEY_DISALBE_CHECKOUT_OPTIONS) {
				$this->enabled = 'no';
			}
		}

	}

	public function validate_checkout(){

		$posted_data = $this->get_post_data();

		if( in_array($posted_data['payment_method'], ["oney_x3_with_fees","oney_x4_with_fees", "oney_x3_without_fees", "oney_x4_without_fees"] ) ){
			if ($this->check_oney_is_available() === self::ONEY_UNAVAILABLE_CODE_COUNTRY_NOT_ALLOWED) {
				throw new \Exception(__('Unavailable for the specified country.'));

			} else if ($this->check_oney_is_available() === self::ONEY_UNAVAILABLE_CODE_CART_SIZE_TOO_HIGH) {
				throw new \Exception(sprintf(__('The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug'), self::ONEY_PRODUCT_QUANTITY_MAXIMUM));

			} else if(!$this->check_oney_is_available()){
				throw new \Exception(sprintf(__('The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug'), $this->oney_thresholds_min , $this->oney_thresholds_max ));
			}
		}
	}

	/**
     * Set oney settings
     *
     * @return void
     */
    private function set_oney_configuration()
    {
        $account = PayplugWoocommerceHelper::get_account_data_from_options();
        if ($account) {
            $oney_configuration = $account['configuration']['oney'];
            $this->min_oney_price = $oney_configuration['min_amounts']['EUR'] / 100;
            $this->max_oney_price = $oney_configuration['max_amounts']['EUR'] / 100;
            $this->allowed_country_codes = $oney_configuration['allowed_countries'];
        }
    }

    /**
     * Get payment icons.
     *
     * @return string
     */
    public function get_icon()
    {

		$disable='';
        if ($this->check_oney_is_available() === true) {
            $total_price = floatval(WC()->cart->total);
            $this->oney_response = $this->api->simulate_oney_payment($total_price, 'with_fees');
            $currency = get_woocommerce_currency_symbol(get_option('woocommerce_currency'));
	        $total_price_oney = floatval($this->oney_response['x3_with_fees']['down_payment_amount']);
			foreach ($this->oney_response['x3_with_fees']['installments'] as $installment) {
				$total_price_oney = $total_price_oney + floatval($installment['amount']);
			}
            $f = function ($fn) {
                return $fn;
            };

			$tax_cost = floatval($this->oney_response['x3_with_fees']['total_cost']) / 100;

            if(is_array($this->oney_response)) {
                $this->description = <<<HTML
                <p>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('Bring', 'payplug'))}:</div>
                        <div>{$this->oney_response['x3_with_fees']['down_payment_amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
					<small>( {$f(__('oney_financing_cost', 'payplug'))} <b>{$tax_cost} {$currency}</b> TAEG : <b>{$this->oney_response['x3_with_fees']['effective_annual_percentage_rate']} %</b> )</small>
				</div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('1st monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x3_with_fees']['installments'][0]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div>{$f(__('2nd monthly payment', 'payplug'))}:</div>
                        <div>{$this->oney_response['x3_with_fees']['installments'][1]['amount']} {$currency}</div>
                    </div>
                    <div class="payplug-oney-flex">
                        <div><b>{$f(__('oney_total', 'payplug'))}</b></div>
                        <div><b>{$total_price_oney} {$currency}</b></div>
                    </div>
                </p>
HTML;
            } else {
                $this->description = $this->oney_response;
            }

        } else {
			$disable='disable-checkout-icons';
        }

		$available_img = 'x3_with_fees.svg';

        $icons = apply_filters('payplug_payment_icons', [
            'payplug' => sprintf('<img src="%s" alt="Oney 3x" class="payplug-payment-icon ' . $disable . '" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $available_img)),
        ]);
        $icons_str = '';
        foreach ($icons as $icon) {
            $icons_str .= $icon;
        }
        return $icons_str;
    }

    /**
     * Check if Oney is available
     *
     * @return false
	 */
    public function check_oney_is_available()
    {
        $cart = WC()->cart;

		//for backend orders
		if( !empty(get_query_var('order-pay')) ){
			$order = wc_get_order(get_query_var('order-pay'));

			//wc_get_order @return bool|WC_Order|WC_Order_Refund
			if($order === false){
				return false;
			}

			$items = $order->get_items();

			$country_code_shipping = $order->get_shipping_country();
			$country_code_billing = $order->get_billing_country();

			$this->order_items_to_cart($cart, $items);
		}

		if(empty($cart->total)){
			return false;
		}

		if(empty($cart->cart_contents_count)){
			return false;
		}

        $total_price = floatval($cart->total);
		$products_qty = (int) $cart->cart_contents_count;

		// Min and max
        if ($total_price < $this->oney_thresholds_min || $total_price > $this->oney_thresholds_max) {
            $this->description = '<div class="payment_method_oney_x3_with_fees_disabled">'.sprintf(__('The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug'), $this->oney_thresholds_min , $this->oney_thresholds_max ).'</div>';
            return false;
        }

        // Cart check
        if ($products_qty >= self::ONEY_PRODUCT_QUANTITY_MAXIMUM) {
            $this->description = '<div class="payment_method_oney_x3_with_fees_disabled">'.sprintf(__('The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug'), self::ONEY_PRODUCT_QUANTITY_MAXIMUM).'</div>';
            return self::ONEY_UNAVAILABLE_CODE_CART_SIZE_TOO_HIGH;
        }

        // Country check
		if( empty($country_code_shipping) || empty($country_code_shipping) ){
			$country_code_shipping = WC()->customer->get_shipping_country();
			$country_code_billing = WC()->customer->get_billing_country();
		}


		//WOOC-663 exception for the description to be visible
		//billing allowed?
		if ( $this->allowed_country($country_code_billing, $this->allowed_country_codes) ) {

			//if shipping is different from billing and billing is accepted
			if(!$this->validate_shipping_billing_country($country_code_shipping, $country_code_billing)){
				$this->description = '<div class="payment_method_oney_x3_with_fees_disabled">'.__('Unavailable for the specified country.', 'payplug').'</div>';
				return self::ONEY_UNAVAILABLE_CODE_COUNTRY_NOT_ALLOWED;
			}
		}else{
			return self::ONEY_DISALBE_CHECKOUT_OPTIONS;
		}

        return true;
    }

    /**
     * Check the order amount to ensure it's on the allowed range.
     *
     * @param int $amount
     *
     * @return int|\WP_Error
     */
    public function validate_order_amount($amount)
    {
        if ($amount / 100 < $this->min_oney_price || $amount / 100 > $this->max_oney_price) {
            return new \WP_Error(
                'invalid order amount',
                sprintf(__('The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug'), $this->min_oney_price, $this->max_oney_price)
            );
        }

        return $amount;
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
            if (!$this->check_oney_is_available()) {
                throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
            } elseif ($this->check_oney_is_available() === self::ONEY_UNAVAILABLE_CODE_COUNTRY_NOT_ALLOWED) {
                $country_code = WC()->customer->get_shipping_country();
                throw new \Exception(__('Unavailable for the specified country.'));
            } elseif ($this->check_oney_is_available() === self::ONEY_UNAVAILABLE_CODE_CART_SIZE_TOO_HIGH) {
                throw new \Exception(sprintf(__('The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug'), self::ONEY_PRODUCT_QUANTITY_MAXIMUM));
            }

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
                    'expected_delivery_date' => date('Y-m-d'),
                    'total_amount' => (int) $total,
                    'price' =>  round($total / $data['quantity']),
                    'quantity' =>  $data['quantity']
                ];
            }

            $payment_data = [
                'authorized_amount' => $amount,
                'auto_capture'     => true,
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
            $payment      = $this->api->payment_create($payment_data);

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


    /**
     * Check if the gatteway is allowed for the order amount
     *
     * @param array
     * @return array
     */
    public function check_gateway($gateways)
    {
        if (isset($gateways[$this->id]) && $gateways[$this->id]->id == $this->id) {

	        //remove gateway if thresholds/country criteria are not met
	        if( $this->check_oney_is_available() === false){
		        unset($gateways[$this->id]);
	        }

			//remove gateway if account doesn't have permission
            if (!PayplugWoocommerceHelper::is_oney_available()) {
                unset($gateways[$this->id]);

            } else {
				$gateways = parent::check_gateway($gateways);
            }
        }
        return $gateways;
    }

    /**
     * Show Oney refund text
     *
     * @return void
     */
    public function oney_refund_text($order)
    {
        if ($this->id === $order->get_payment_method() && parent::can_refund_order($order) && $order->get_status() !== "refunded" && $this->api) {
            $order_metadata = $order->get_meta('_payplug_metadata');

			if ( is_array($order_metadata) && !empty($order_metadata['transaction_id']) ){
	            $payment  = $this->api->payment_retrieve($order_metadata['transaction_id']);
    	        $today = current_time('Y-m-d H:i:s');
        	    $can_refund_date = date('Y-m-d H:i:s', $payment->__get('refundable_after'));
				if ($can_refund_date >= $today) {
					echo "<p style='color: red;'>" . __('Refund will be possible 48 hours after the last payment or refund transaction.', 'payplug') . "</p>";
				}
			}

        }
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if (!empty($description)) {
            echo wpautop(wptexturize($description));
        }
    }

	/**
	 *
	 * Billing and shipping addresses should have the same country and allowed by Oney
	 * https://payplug-prod.atlassian.net/browse/WOOC-227
	 *
	 * @param $order
	 * @return bool
	 *
	 */
	public function validate_shipping_billing_country($shipping_country, $billing_country)
	{
		if($billing_country === $shipping_country)
			return true;

		return false;
	}

	/**
	 * Country of billing or shipping at oney payments should be on the allowedCountry that comes from pauyplug-api /account
	 *
	 * @param string $country
	 * @param array $allowed
	 * @return bool
	 */
	public function allowed_country($country, $allowed)
	{
		if( in_array($country, $allowed))
			return true;

		return false;
	}

	/**
	 * Empty the cart and add all order_items
	 *
	 * @param $cart
	 * @param $items
	 * @return void
	 */
	private function order_items_to_cart($cart, $items){
		$cart->empty_cart();
		foreach ($items as $item){
			$cart->add_to_cart($item->get_product_id(), $item->get_quantity(), $item->get_variation_id());
		}
	}

	public function checkGateway() {

		$options = PayplugWoocommerceHelper::get_payplug_options();

		if ($options['oney'] === 'no') {
			return false;
		}

		return true;

	}

}
