<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Exception\HttpException;
use Payplug\PayplugWoocommerce\Gateway\PayplugAddressData;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\Resource\Payment as PaymentResource;

class ApplePay extends PayplugGateway
{

	protected $enable = false;
	protected $domain_name = "";

	public function __construct()
	{

		parent::__construct();

		/** @var \WC_Settings_API  override $id */
		$this->id                 = 'apple_pay';

		/** @var \WC_Payment_Gateway overwrite for apple pay settings  */
		$this->method_title       = __('payplug_apple_pay_title', 'payplug');
		$this->method_description = __('payplug_apple_pay_description', 'payplug');

		$this->title = __('payplug_apple_pay_title', 'payplug');
		$this->description = '';

		if (!$this->checkApplePay()) {
			$this->enabled = 'no';

		} else {
			add_action('wp_enqueue_scripts', [$this, 'add_apple_pay_css']);
			add_action('wp_enqueue_scripts', [$this, 'add_apple_pay_js']);
		}

	}

	/**
	 * @return bool|void
	 */
	public function process_admin_options() {
		$data = $this->get_post_data();

		if (isset($data['woocommerce_payplug_apple_pay'])) {
			if (($data['woocommerce_payplug_apple_pay'] == 1) && (!$this->checkApplePay())) {
				add_action( 'woocommerce_settings_saved', [$this ,"display_notice"] );
			}
		}

	}

	/**
	 *
	 * Check Apple Pay Authorization
	 *
	 * @return bool
	 */
	private function checkApplePay() {
		$account = PayplugWoocommerceHelper::get_account_data_from_options();

		if ( isset( $account['payment_methods']['apple_pay']['enabled'] ) ) {

			if ( ! empty( $account['apple_pay'] ) && $account['apple_pay'] === 'yes' ) {
				$applepay = false;
				if ( $account['payment_methods']['apple_pay']['enabled'] ) {
					if ( in_array( strtr( get_site_url(), array(
						"http://" => "",
						"http://" => ""
					) ), $account['payment_methods']['apple_pay']['allowed_domain_names'] ) ) {
						$applepay = true;
					}
				}

				return $applepay && $this->checkDeviceComptability();
			}
		}

		return false;
	}
	/**
	 * Check User-Agent to make sure it is on Mac OS and in Safari Browser
	 *
	 * @return bool
	 */
	private function checkDeviceComptability(){
		$user_agent = $_SERVER['HTTP_USER_AGENT'];

		// Check if the Browser is Safari
		if (stripos( $user_agent, 'Chrome') !== false) {
			return false;
		} elseif (stripos( $user_agent, 'Safari') !== false) {
			// Check if the OS is Mac
			if(!preg_match('/macintosh|mac os x/i', $user_agent)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add CSS
	 *
	 */
	public function add_apple_pay_css() {
		wp_enqueue_style('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-apple-pay.css', [], PAYPLUG_GATEWAY_VERSION);
	}

	/**
	 * Add JS
	 *
	 */
	public function add_apple_pay_js() {
		wp_enqueue_script( 'apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), false, true );
		wp_enqueue_script('payplug-apple-pay', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-apple-pay.js',
		[
			'jquery',
			'apple-pay-sdk'
		], PAYPLUG_GATEWAY_VERSION, true);
		wp_localize_script( 'payplug-apple-pay', 'apple_pay_params',
			array(
				'ajax_url_payplug_create_order' => \WC_AJAX::get_endpoint('payplug_create_order'),
				'chosen_payment_method'=> WC()->session->get( 'chosen_payment_method')
			)
		);
	}
	/**
	 * Display unauthorized error
	 *
	 * @return void
	 */

	public static function display_notice() {
		?>
		<div class="notice notice-error is-dismissible">
		<p><?php _e( 'You don\'t have access to this feature yet. To activate Apple Pay, please contact support@payplug.com', 'payplug' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Get payment icons.
	 *
	 * @return string
	 */
	public function get_icon()
	{
		$available_img = 'apple-pay-checkout.svg';
		$icons = apply_filters('payplug_payment_icons', [
			'payplug' => sprintf('<img src="%s" alt="Apple Pay" class="payplug-payment-icon" />', esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/' . $available_img)),

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
			if (!$this->checkApplePay()) {
				throw new \Exception(__('Payment processing failed. Please retry.', 'payplug'));
			}

			$address_data = PayplugAddressData::from_order($order);

			$return_url = esc_url_raw($order->get_checkout_order_received_url());

			if (!(str_starts_with($return_url, "http"))) {
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
