<?php
namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Controller\ApplePay as ApplePayGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use function is_cart;

class ApplePay {

	public function __construct() {
		add_action( 'woocommerce_after_cart_totals', [ $this, 'applepayButton' ] );

		//routes for apple_pay on cart
		add_action( 'wc_ajax_applepay_get_shippings', [ $this, 'applepay_get_shippings' ] );
		add_action( 'wc_ajax_place_order_with_dummy_data', [ $this, 'place_order_with_dummy_data' ] );
		add_action( 'wc_ajax_update_applepay_order', [ $this, 'update_applepay_order' ] );
		add_action( 'wc_ajax_update_applepay_payment', [ $this, 'update_applepay_payment' ] );
		add_action( 'wc_ajax_applepay_cancel_order', [ $this, 'applepay_cancel_order' ] );
	}

	/**
	 * Add button and Scripts
	 */
	public function applepayButton() {

		if (is_cart()) {
			$apple_pay = new ApplePayGateway();
			wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
			wp_enqueue_script('payplug-apple-pay-cart', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay-cart.js', ['jquery', 'apple-pay-sdk'], PAYPLUG_GATEWAY_VERSION, true);
			wp_localize_script( 'payplug-apple-pay-cart', 'apple_pay_params',
				array(
					'ajax_url_applepay_get_shippings' => \WC_AJAX::get_endpoint('applepay_get_shippings'),
					'ajax_url_place_order_with_dummy_data' => \WC_AJAX::get_endpoint('place_order_with_dummy_data'),
					'ajax_url_update_applepay_order' => \WC_AJAX::get_endpoint('update_applepay_order'),
					'ajax_url_update_applepay_payment' => \WC_AJAX::get_endpoint('update_applepay_payment'),
					'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
					'ajax_url_applepay_cancel_order' => \WC_AJAX::get_endpoint('applepay_cancel_order'),

					'countryCode' => WC()->customer->get_billing_country(),
					'currencyCode' => get_woocommerce_currency(),
					'apple_pay_domain' => $_SERVER['HTTP_HOST']
				)
			);
			$apple_pay->add_apple_pay_css();

			if ( $apple_pay->enabled ) {
				echo $apple_pay->description;
			}

		}
	}

	public function applepay_get_shippings() {
		WC()->customer->set_shipping_country('FR');
		WC()->customer->set_shipping_city('Paris');
		WC()->customer->set_shipping_postcode('12345');
		WC()->customer->set_shipping_address('Dummy Address');

		$packages = WC()->cart->get_shipping_packages();
		$shippings = [];

		foreach ( $packages as $package_key => $package ) {
			$shipping_methods = $this->get_shipping_methods_from_package($package);

			foreach ( $shipping_methods as $shipping_method ) {

				if (!$shipping_method->supports('shipping-zones') || !$shipping_method->is_enabled()) {
					continue;
				}

				$rates = $shipping_method->get_rates_for_package($package);
				if($this->checkApplePayShipping($shipping_method)){
					$shipping_rate = $rates[$shipping_method->get_rate_id()];

					array_push($shippings, [
						'identifier' => $shipping_method->id,
						'label' => $shipping_method->method_title,
						'detail' => $shipping_method->method_description,
						'amount' =>$shipping_rate->get_cost()+$shipping_rate->get_shipping_tax()
					]);
				}
			}
		}
		wp_send_json_success($shippings);
	}

	/**
	 * @param $shipping
	 * @return bool
	 */
	private function checkApplePayShipping($shipping = []){
		if(empty($shipping)){
			return false;
		}

		$apple_pay_options = PayplugWoocommerceHelper::get_applepay_options();
		$apple_pay_carriers = $apple_pay_options['carriers'];

		$exists = false;
		foreach($apple_pay_carriers as $carrier => $carrier_id){
			if($carrier_id === $shipping->id){
				return true;
			}
		}

		return $exists;
	}


	private function get_shipping_methods_from_package($package){
		$shipping_zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
		return $shipping_zone->get_shipping_methods( true );
	}

	public function place_order_with_dummy_data() {
		$apple_pay = new ApplePayGateway();
		if ( is_admin() ) {
			return;
		}

		$cart = WC()->cart;


		if ( ! $cart->is_empty() ) {

			$checkout = WC()->checkout();

			$order_id = $checkout->create_order(array('payment_method' => $apple_pay->id));
			$order = wc_get_order($order_id);


			$order->set_address( [
				'first_name' => 'payplug_applepay_first_name',
				'last_name'  => 'payplug_applepay_last_name',
				'address_1'  => 'payplug_applepay_address',
				'address_2'  => '',
				'city'       => 'payplug_applepay_city',
				'postcode'   => 'payplug_applepay_psotcode',
				'country'    => WC()->countries->get_base_country(),
				'email'      => 'payplug_applepay_email@payplug.com'
			], 'billing' );
			$order->set_address( [
				'first_name' => 'payplug_applepay_first_name',
				'last_name'  => 'payplug_applepay_last_name',
				'address_1'  => 'payplug_applepay_address',
				'address_2'  => '',
				'city'       => 'payplug_applepay_city',
				'postcode'   => 'payplug_applepay_psotcode',
				'country'    => WC()->countries->get_base_country(),
				'email'      => 'payplug_applepay_email@payplug.com'
			], 'shipping' );

			$order->set_payment_method($apple_pay);

			$packages = WC()->cart->get_shipping_packages();

			WC()->shipping()->reset_shipping();

			foreach ( $packages as $package_key => $package ) {

				$shipping_methods = $this->get_shipping_methods_from_package($package);

				foreach ( $shipping_methods as $shipping_method ) {

					if ( ! $shipping_method->supports( 'shipping-zones' ) || ! $shipping_method->is_enabled() ) {
						continue;
					}

					if($this->checkApplePayShipping($shipping_method)) {
						$shipping_method->calculate_shipping($package);
						$rates = $shipping_method->get_rates_for_package($package);
						if (!empty($rates)) {
							$rate = reset($rates);
							$shipping = new \WC_Order_Item_Shipping();
							$shipping->set_method_title($rate->get_label());
							$shipping->set_method_id($rate->get_id());
							$shipping->set_total($rate->get_cost());

							$shipping_taxes = \WC_Tax::calc_shipping_tax(
								$rate->cost,
								\WC_Tax::get_shipping_tax_rates()
							);

							$shipping->set_taxes([
								'total' => $shipping_taxes
							]);

							$shipping->save();
							$order->add_item($shipping);
							break;
						}
					}
				}
			}

			$order->calculate_taxes();

			$order->calculate_totals();

			$order->save();

			$cart->empty_cart();

			$this->process_cart_payment($order, $apple_pay);

		} else {
			wp_send_json_error();
		}
	}

	public function process_cart_payment($order ,$gateway) {
		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		$customer_id = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
		$amount      = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_total() - ($order->get_shipping_tax() + $order->get_shipping_total()));
		$amount      = $gateway->validate_order_amount($amount);
		wp_send_json([
			'total' => $amount,
			'order_id' => $order_id,
			'payment_data' => $gateway->process_standard_payment($order, $amount, $customer_id)
		]);

	}

	public function update_applepay_order() {
		$order_id = $_POST['order_id'];
		$order = wc_get_order( $order_id );

		$selected_shipping_method = $_POST['shipping_method'];
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
			foreach ( $_POST['billing'] as $key => $data ) {
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

		$shipping_address= $address_data->get_shipping();
		$shipping_address['state'] = '';

		$package = array(
			'contents'        => array(),
			'contents_cost'   => 0,
			'destination'     => $shipping_address,
			'applied_coupons' => $order->get_used_coupons(),
			'user'            => array(
				'ID' => $order->get_customer_id()
			)
		);

		foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
			$order->remove_item($item_id);
		}


		$shipping_zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
		if ( $shipping_zone ) {

			$shipping_methods = $shipping_zone->get_shipping_methods( true );

			foreach ( $shipping_methods as $shipping_method ) {
				if ( ! $shipping_method->supports( 'shipping-zones' ) || ! $shipping_method->is_enabled() ) {
					continue;
				}

				if ($shipping_method->id === $selected_shipping_method) {

					$shipping_method->calculate_shipping( $package );
					$rates = $shipping_method->get_rates_for_package($package);

					if ( ! empty( $rates ) ) {

						$rate = reset( $rates );
						$item = new \WC_Order_Item_Shipping();
						$item->set_method_title( $rate->get_label() );
						$item->set_method_id( $rate->get_id() );
						$item->set_total( $rate->get_cost() );

						$shipping_taxes = \WC_Tax::calc_shipping_tax(
							$rate->cost,
							\WC_Tax::get_shipping_tax_rates()
						);
						$item->set_taxes( [
							'total' => $shipping_taxes,
						] );

						$item->calculate_taxes();

						$item->save();

						$order->add_item( $item );
						break;
					}
				}
			}
		}

		$order->calculate_taxes();
		$order->calculate_totals();
		$order->save();

		wp_send_json($order);

	}

	/**
	 * update the payment since customers can change their options during the payment
	 * @return void
	 * @throws \Payplug\Exception\ConfigurationNotSetException
	 */
	public function update_applepay_payment() {

		$applepay = new ApplePayGateway();

		$payment_id = $_POST['payment_id'];
		$order_id = $_POST['order_id'];
		$payment_token = $_POST['payment_token'];
		$amount = $_POST['amount']/100;


		$payment = \Payplug\Payment::retrieve($payment_id);

		$order = wc_get_order( $order_id );
		$amount      = (int) PayplugWoocommerceHelper::get_payplug_amount($amount);
		$amount      = $applepay->validate_order_amount($amount);

		$address_data = PayplugAddressData::from_order($order);

		// delivery_type must be removed in Apple Pay
		$billing = $address_data->get_billing();
		unset($billing['delivery_type']);
		$shipping = $address_data->get_shipping();
		unset($shipping['delivery_type']);

		$data = ['apple_pay' => array(
			"amount" => $amount,
			"payment_token" => $payment_token,
			"billing"          => $billing,
			"shipping"       => $shipping,
		)];

		$update = $payment->update($data);

		wp_send_json_success([
			'amount' => $amount,
			'update' => $update
		]);
	}

	public function applepay_cancel_order() {
		$applepay = new ApplePayGateway();
		$payment_id = $_POST['payment_id'];
		$order_id = $_POST['order_id'];

		$order = wc_get_order($order_id);
		$items = $order->get_items();
		foreach ($items as $item) {
			$product_id = $item->get_product_id();
			$quantity = $item->get_quantity();
			$variation_id = $item->get_variation_id();
			$variations = array();

			if ($variation_id) {
				$product = wc_get_product($variation_id);
				$variations = $product->get_variation_attributes();
			}

			WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations);
		}

		$payment = \Payplug\Payment::retrieve($payment_id);

		$payment->abort();

		if ($order->delete(true)) {
			wp_send_json_success([
				'message' => __('Your order was cancelled.', 'woocommerce')
			]);
		} else {
			wp_send_json_error();
		}


	}

}
