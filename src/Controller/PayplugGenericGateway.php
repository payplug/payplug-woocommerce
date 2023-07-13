<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Interfaces\PayplugGatewayBuilder;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;


use Payplug\Authentication;
use Payplug\Exception\ConfigurationException;
use Payplug\Exception\HttpException;
use Payplug\Exception\ForbiddenException;
use Payplug\Payplug;

class PayplugGenericGateway extends PayplugGateway implements PayplugGatewayBuilder
{

	public function __construct()
	{
		parent::__construct();

		//this is only for PPRo payments
		add_action('woocommerce_after_order_itemmeta', [$this, 'hide_wc_refund_button']);
		//TODO:: add requirements here

	}

	/**
	 * Generic code to fetch payment gateway specific image
	 * @return string
	 */
	public function get_icon()
	{

		//get object $image
		$icons = apply_filters('payplug_payment_icons', [
			'payplug' => sprintf('<img src="%s" alt="%s" class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->image), $this->id . " Icon"),
		]);

		$icons_str = '';
		foreach ($icons as $icon) {
			$icons_str .= $icon;
		}

		return $icons_str;
	}

	/**
	 * @return void
	 */
	public function display_notice()
	{
		$error_message = 'payplug_' . $this->id . '_unauthorized_message';
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo __( $error_message, 'payplug' ); ?></p>
		</div>
		<?php
	}

	public function checkGateway()
	{

		//TODO:: MISSING saved configurations
		$account = PayplugWoocommerceHelper::generic_get_account_data_from_options( $this->id );

		//account doesnt have permissions
		if ( ( isset( $account["payment_methods"] ) ) && ( empty( $account["payment_methods"][ $this->id ] ) ) && ( ! $account["payment_methods"][ $this->id ]['enabled'] ) ) {
			return false;
		}

		//check if it's activated on the BO
		if ( ! isset( $account['permissions'][ $this->id ] ) || ( isset( $account['permissions'][ $this->id ] ) && ! $account['permissions'][ $this->id ] ) ) {
			return false;
		}

		if (is_checkout()) {
			if ( empty( WC()->cart ) ) {
				return false;
			}

			//for backend orders
			if ( ! empty( get_query_var( 'order-pay' ) ) ) {
				$order = wc_get_order( get_query_var( 'order-pay' ) );
				$items = $order->get_items();

				$country_code_shipping = $order->get_shipping_country();
				$country_code_billing  = $order->get_billing_country();

				$this->order_items_to_cart( WC()->cart, $items );
			}

			$order_amount = $this->get_order_total();

			$this->allowed_country_codes = $account["payment_methods"][ $this->id ]['allowed_countries'];
			$this->get_thresholds_values( $account );


			//threshold validations
			if ( ( ! empty( $this->min_thresholds ) && $order_amount < $this->min_thresholds ) || ( ! empty( $this->max_thresholds ) && $order_amount > $this->max_thresholds ) ) {
				$this->description = '<div class="payment_method_oney_x3_with_fees_disabled">' . __( $this->id . '_threshold.', 'payplug' ) . '</div>';

				return false;
			}

			if ( empty( $country_code_shipping ) || empty( $country_code_shipping ) ) {
				$country_code_shipping = WC()->customer->get_shipping_country();
				$country_code_billing  = WC()->customer->get_billing_country();
			}

			if ( $this->allowed_country_codes === "ALL" || empty( $this->allowed_country_codes ) ) {
				return true;
			}

			//check if country is allowed
			if ( in_array( $country_code_billing, $this->allowed_country_codes ) ) {

				//billing and shipping should be the same country
				if ( ! $this->validate_shipping_billing_country( $country_code_shipping, $country_code_billing ) ) {
					$this->description = '<div class="payment_method_oney_x3_with_fees_disabled">' . __( 'Unavailable for the specified country.', 'payplug' ) . '</div>';

					return false;
				}

				return true;

			} else {
				return false;
			}
		} else {
			//check if it's activated on the BO
			if ( ! isset( $account['permissions'][ $this->id ] ) || ( isset( $account['permissions'][ $this->id ] ) && ! $account['permissions'][ $this->id ] ) ) {
				return false;
			} else {
				return true;
			}
		}
	}

	public function process_standard_payment($order, $amount, $customer_id)
	{
		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		try {

			//if there's no auth to process payment
			if (!$this->checkGateway()) {
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			}

			$address_data = PayplugAddressData::from_order($order);

			$return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(substr( $return_url, 0, 4 ) === "http")) {
				$return_url = get_site_url().$return_url;
			}

			$payment_data = [
				'amount'           => $amount,
				'currency'         => get_woocommerce_currency(),
				'payment_method'   => $this->id,
				'billing'          => $address_data->get_billing(),
				'shipping'         => $address_data->get_shipping(),
				'hosted_payment'   => [
					'return_url' => $return_url,
					'cancel_url' => esc_url_raw($order->get_cancel_order_url_raw()),
				],
				'notification_url' => esc_url_raw(WC()->api_request_url('PayplugGateway')),
				'metadata'         => [
					'order_id'    => $order_id,
					'customer_id' => ((int) $customer_id > 0) ? $customer_id : 'guest',
					'domain'      => $this->limit_length(esc_url_raw(home_url()), 500),
				],
				"save_card"=> false,
				"force_3ds"=> false
			];

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

		} catch (HttpException $e) {
			PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, wc_print_r($e->getErrorObject(), true)), 'error');
			throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
		} catch (\Exception $e) {
			PayplugGateway::log(sprintf('Error while processing order #%s : %s', $order_id, $e->getMessage()), 'error');
			throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
		}

	}

	/**
	 * Process refund for an order paid with PayPlug gateway.
	 *
	 * @param int $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool|\WP_Error
	 */
	public function process_refund($order_id, $amount = null, $reason = ''){

		PayplugGateway::log(sprintf('Processing refund for order #%s', $order_id));

		if( !$this->user_logged_in()){
			PayplugGateway::log(__('You must be logged in with your PayPlug account.', 'payplug'), 'error');
			return new \WP_Error('process_refund_error', __('You must be logged in with your PayPlug account.', 'payplug'));
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof \WC_Order) {
			PayplugGateway::log(sprintf('The order #%s does not exist.', $order_id), 'error');

			return new \WP_Error('process_refund_error', sprintf(__('The order %s does not exist.', 'payplug'), $order_id));
		}

		if ($order->get_status() === "cancelled") {
			PayplugGateway::log(sprintf('The order #%s cannot be refund.', $order_id), 'error');

			return new \WP_Error('process_refund_error', sprintf(__('The order %s cannot be refund.', 'payplug'), $order_id));
		}

		$transaction_id = PayplugWoocommerceHelper::is_pre_30() ? get_post_meta($order_id, '_transaction_id', true) : $order->get_transaction_id();
		if (empty($transaction_id)) {
			PayplugGateway::log(sprintf('The order #%s does not have PayPlug transaction ID associated with it.', $order_id), 'error');

			return new \WP_Error('process_refund_error', __('No PayPlug transaction was found for this order. The refund could not be processed.', 'payplug'));
		}

		add_action('admin_head', [$this, 'hide_wc_refund_button'] );

		//TODO:: add the refund process, for now we don't have any scenario where we allow refund for the new gateways
		PayplugGateway::log(__('payplug_refund_disabled_error', 'payplug'), 'error');
		return new \WP_Error('process_refund_error', __('payplug_refund_disabled_error', 'payplug'));

	}


	public function hide_wc_refund_button(){
		global $post;
		$payment_methods = ['giropay', 'satispay', 'sofort', 'ideal', 'mybank'];
		$order = new \WC_Order($post->ID);
		if (in_array($order->get_payment_method(), $payment_methods)) {
		?>
			<script>
			jQuery(function () {
				jQuery('.refund-items').attr("disabled", true);
			});
		</script>
		<?php
		}
	}

	/**
	 * refund not possible for PPRO payments
	 *
	 * @return void
	 */
	public function refund_not_available($order)
	{
		if ($this->id === $order->get_payment_method() ) {
			echo "<p style='color: red;'>" . __('payplug_refund_disabled_error', 'payplug') . "</p>";
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
	private function validate_shipping_billing_country($shipping_country, $billing_country)
	{
		if($billing_country === $shipping_country)
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
			$cart->add_to_cart($item->get_product_id(), $item->get_quantity());
		}
	}

	/**
	 * get threshold values for the current payment method
	 * @param $account
	 * @return void
	 */
	private function get_thresholds_values($account){

		if(!empty($account["payment_methods"][$this->id]['min_amounts']['EUR'])){
			$this->min_thresholds = floatval($account["payment_methods"][$this->id]['min_amounts']['EUR']/100);

		}

		if(!empty($account["payment_methods"][$this->id]['max_amounts']['EUR'])){
			$this->max_thresholds =  floatval($account["payment_methods"][$this->id]['max_amounts']['EUR']/100);

		}

	}

}
