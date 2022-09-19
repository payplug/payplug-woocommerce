<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;

class AmericanExpress extends PayplugGateway
{

	public function __construct() {

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id = 'american_express';

		/** @var \WC_Payment_Gateway overwrite for apple pay settings */
		$this->method_title = __('payplug_amex_title', 'payplug');
		$this->method_description = "";

		$this->title = __('payplug_amex_title', 'payplug');
		$this->description = '';

		if(!$this->checkAmericanExpress())
			$this->enabled = 'no';

	}

	/**
	 *
	 * Check American Express Authorization
	 *
	 * @return bool
	 */
	private function checkAmericanExpress(){
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if (isset($account['payment_methods']['american_express']['enabled']) ) {

			if( !empty($account['american_express']) && $account['american_express'] === 'yes' )
				return  $account['payment_methods']['american_express']['enabled'];

		}

		return false;
	}

	/**
	 * @return bool|void
	 */
	public function process_admin_options() {
		$data = $this->get_post_data();
		if (isset($data['woocommerce_payplug_mode'])) {
			if ($data['woocommerce_payplug_mode'] === '0') {
				$options = get_option('woocommerce_payplug_settings', []);
				$options['american_express'] = 'no';
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
			}
		}

		if (isset($data['woocommerce_payplug_american_express'])) {
			if (($data['woocommerce_payplug_american_express'] == 1) && (!$this->checkAmericanExpress())) {
				$options = get_option('woocommerce_payplug_settings', []);
				$options['american_express'] = 'no';
				update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $options) );
				add_action( 'admin_notices', [$this ,"display_notice"] );
			}
		}

	}

	/**
	 * Display unauthorized error
	 *
	 * @return void
	 */
	public static function display_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo __( 'payplug_amex_unauthorized_message', 'payplug' ); ?></p>
		</div>
		<?php
	}

	/**
	 *
	 * Get Amex payment icon
	 *
	 * @return string
	 */

	public function get_icon() {
	$available_img = 'Amex_logo_color.svg';
	$icons = apply_filters('payplug_payment_icons', [
		'payplug' => sprintf('<img src="%s" alt="Amex Icon class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/' . $available_img)),
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
			if (!$this->checkAmericanExpress()) {
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

}
