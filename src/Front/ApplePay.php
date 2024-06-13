<?php
namespace Payplug\PayplugWoocommerce\Front;

use _HumbugBox7eb78fbcc73e\___PHPSTORM_HELPERS\this;
use Payplug\PayplugWoocommerce\Controller\ApplePay as Gateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Resource\Payment as PaymentResource;
use function is_cart;
use function is_checkout;
use function is_product;

class ApplePay {

	public function __construct() {
		add_action( 'woocommerce_after_cart_totals', [ $this, 'applepayButton' ] );
		add_action( 'wc_ajax_applepay_get_shippings', [ $this, 'applepay_get_shippings' ] );
		add_action( 'wc_ajax_place_order_with_dummy_data', [ $this, 'place_order_with_dummy_data' ] );
		add_action( 'wc_ajax_update_applepay_order', [ $this, 'update_applepay_order' ] );
		add_action( 'wc_ajax_update_applepay_payment', [ $this, 'update_applepay_payment' ] );
	}

	public function applepayButton() {
		if (is_cart()) {
			$apple_pay = new Gateway();
			wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
			wp_enqueue_script('payplug-apple-pay-card', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay-card.js',
				[
					'jquery',
					'apple-pay-sdk'
				], PAYPLUG_GATEWAY_VERSION, true);
			wp_localize_script( 'payplug-apple-pay-card', 'apple_pay_params',
				array(
					'ajax_url_payplug_create_order' => \WC_AJAX::get_endpoint('payplug_create_order'),
					'ajax_url_applepay_update_payment' => \WC_AJAX::get_endpoint('applepay_update_payment'),
					'ajax_url_applepay_get_order_totals' => \WC_AJAX::get_endpoint('applepay_get_order_totals'),
					'ajax_url_applepay_get_shippings' => \WC_AJAX::get_endpoint('applepay_get_shippings'),
					'ajax_url_place_order_with_dummy_data' => \WC_AJAX::get_endpoint('place_order_with_dummy_data'),
					'ajax_url_update_applepay_order' => \WC_AJAX::get_endpoint('update_applepay_order'),
					'ajax_url_update_applepay_payment' => \WC_AJAX::get_endpoint('update_applepay_payment'),
					'countryCode' => WC()->customer->get_billing_country(),
					'currencyCode' => get_woocommerce_currency(),
					'total'  => WC()->cart->total,
					'apple_pay_domain' => $_SERVER['HTTP_HOST']
				)
			);
			$apple_pay->add_apple_pay_css();
			if ($apple_pay->checkDeviceComptability()) {
				echo $apple_pay->description;
			}

		}
	}

	public function applepay_get_shippings() {
		$dummy_address = array(
			'country'   => 'FR',
			'state'     => '',
			'city'      => 'Parisy',
			'postcode'  => '12345',
			'address_1' => '123 Main St',
		);
		WC()->customer->set_shipping_country('FR');
		WC()->customer->set_shipping_city('Paris');
		WC()->customer->set_shipping_postcode('12345');
		WC()->customer->set_shipping_address('sadsa dsadsa');

		$applepay_shippings = PayplugWoocommerceHelper::get_applepay_options()['carriers'];
		$packages = WC()->cart->get_shipping_packages();
		$available_shippings = WC()->shipping()->calculate_shipping($packages);
		$shippings = [];

		foreach ($available_shippings[0]['rates'] as $shipping) {
			array_push($shippings, [
				'identifier' => $shipping->get_method_id(),
				'label' => $shipping->get_label(),
				'detail' => WC()->shipping()->shipping_methods[$shipping->get_method_id()]->method_description,
				'amount' =>$shipping->get_cost() + $shipping->get_shipping_tax()
			]);
		}

		wp_send_json_success($shippings);

	}

	public function place_order_with_dummy_data() {
		$apple_pay = new Gateway();
		if ( is_admin() ) {
			return;
		}

		$cart = WC()->cart;

		if ( ! $cart->is_empty() ) {

			$cart = WC()->cart;

			$order = wc_create_order();

			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product = $values['data'];
				$quantity = $values['quantity'];
				$item = new \WC_Order_Item_Product();
				$item->set_product( $product );
				$item->set_quantity( $quantity );
				$item->set_subtotal( $values['line_subtotal'] );
				$item->set_total( $values['line_total'] + $values['line_subtotal_tax']  );

				// Calculate and add taxes for each item
				$item_tax_data = \WC_Tax::calc_tax(
					$values['line_total'] + $values['line_subtotal_tax'],
					\WC_Tax::get_rates( $product->get_tax_class() ),
					true // Price is tax inclusive
				);
				$item->set_taxes( [
					'total' => $item_tax_data,
				] );
				$item->calculate_taxes();

				$order->add_item( $item );
			}


			$order->set_address( [
				'first_name' => 'payplug_applepay_first_name',
				'last_name'  => 'payplug_applepay_last_name',
				'address_1'  => 'payplug_applepay_address',
				'address_2'  => '',
				'city'       => 'payplug_applepay_city',
				'postcode'   => 'payplug_applepay_psotcode',
				'country'    => 'payplug_applepay_country',
				'email'      => 'payplug_applepay_email@payplug.com'
			], 'billing' );

			$order->set_address( [
				'first_name' => 'payplug_applepay_first_name',
				'last_name'  => 'payplug_applepay_last_name',
				'address_1'  => 'payplug_applepay_address',
				'address_2'  => '',
				'city'       => 'payplug_applepay_city',
				'postcode'   => 'payplug_applepay_psotcode',
				'country'    => 'payplug_applepay_country',
				'email'      => 'payplug_applepay_email@payplug.com'
			], 'shipping' );

			$order->set_payment_method( "apple_pay" );

			$packages = WC()->cart->get_shipping_packages();

			WC()->shipping()->reset_shipping();

			foreach ( $packages as $package_key => $package ) {

				$shipping_zone = \WC_Shipping_Zones::get_zone_matching_package( $package );
				if ( ! $shipping_zone ) {
					continue;
				}

				$shipping_methods = $shipping_zone->get_shipping_methods( true );

				foreach ( $shipping_methods as $shipping_method ) {

					if ( ! $shipping_method->supports( 'shipping-zones' ) || ! $shipping_method->is_enabled() ) {
						continue;
					}

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

						$item->set_total(intval($item->get_taxes()['total'][1]) + (int)$item->get_total());


						$order->add_item( $item );
						break;
					}
				}
			}

			$order->calculate_taxes();

			$order->calculate_totals();

			$cart->empty_cart();

			$order->save();


			$this->process_cart_payment($order, $apple_pay);
		} else {
			wp_send_json_error();
		}
	}

	public function process_cart_payment($order ,$gateway) {
		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		$customer_id = PayplugWoocommerceHelper::is_pre_30() ? $order->customer_user : $order->get_customer_id();
		$amount      = (int) PayplugWoocommerceHelper::get_payplug_amount($order->get_subtotal());
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

		$address = array(
			'country'   => $order->get_shipping_country(),
			'state'     => $order->get_shipping_state(),
			'postcode'  => $order->get_shipping_postcode(),
			'city'      => $order->get_shipping_city(),
			'address'   => $order->get_shipping_address_1(),
			'address_2' => $order->get_shipping_address_2(),
		);

		$package = array(
			'contents'        => array(),
			'contents_cost'   => 0,
			'destination'     => $address,
			'applied_coupons' => $order->get_used_coupons(),
			'user'            => array(
				'ID' => $order->get_customer_id()
			)
		);

		foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
			$order->remove_item($item_id);
		}

		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			if ($product) {
				$package['contents'][$item_id] = array(
					'data'        => $product,
					'quantity'    => $item->get_quantity(),
					'line_total'  => $item->get_total(),
					'line_tax'    => $item->get_total_tax(),
				);
				$package['contents_cost'] += $item->get_total();
			}
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

							$item->set_total(intval($item->get_taxes()['total'][1]) + (int)$item->get_total());

							$order->add_item( $item );
							break;
						}
					}
				}
			}

		$order->calculate_totals();
		$order->save();


		wp_send_json($order);

	}

	public function update_applepay_payment() {

		$applepay = new Gateway();

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

}
