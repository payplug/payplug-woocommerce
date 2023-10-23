<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Resource\IVerifiableAPIResource;
use Payplug\Resource\Payment as PaymentResource;
use Payplug\Resource\Refund as RefundResource;
use WC_Payment_Tokens;
use WC_Payment_Token_CC;

/**
 * Process responses from PayPlug.
 *
 * @package Payplug\PayplugWoocommerce\Gateway
 */
class PayplugResponse {

	private $gateway;

	public function __construct( PayplugGateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Process payment.
	 *
	 * @param $resource
	 * @param $is_payment_with_token
	 *
	 * @return void
	 * @throws \WC_Data_Exception
	 */
	public function process_payment($resource, $is_payment_with_token = false, $source = null)
	{
		$order_id = wc_clean($resource->metadata['order_id']);

		$order = wc_get_order($order_id);
		$gateway_id = $order->get_payment_method();
		$metadata = PayplugWoocommerceHelper::extract_transaction_metadata($resource);

		// Ignore undefined orders
		if (!$order) {
			PayplugGateway::log(sprintf('Coudn\'t find order #%s (Transaction %s).', $order_id, wc_clean($resource->id)), 'error');
			return;
		}

		// Third party hook
		\do_action('payplug_gateway_payment_response_third', $order_id, $resource, $is_payment_with_token, $source);

		/**
		 *
		 * Checking if it is coming from the order confirmation page or the IPN
		 *
		 */

		if (($gateway_id == $this->gateway->id) || (!empty($source) && ($source === "ipn"))) {

			// Ignore cancelled orders
			if ($order->has_status('refunded')) {
				PayplugGateway::log(sprintf('Order #%s : '. $this->gateway_name($gateway_id) .' order has been refunded. Ignoring IPN', $order_id));
				//$lock->deleteLock($resource->id);
				return;
			}

			// Ignore paid orders
			if ($order->is_paid()) {
				PayplugGateway::log(sprintf('Order #%s : '. $this->gateway_name($gateway_id) .' order is already complete. Ignoring IPN.', $order_id));
				//$lock->deleteLock($resource->id);
				return;
			}

			// Handle failed payments
			if (!empty($resource->failure)) {
				PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, true);
				$order->update_status(
					'failed',
					sprintf(__('PayPlug IPN OK | Transaction %s failed : %s', 'payplug'), $resource->id, wc_clean($resource->failure->message))
				);
				/** This action is documented in src/Gateway/PayplugResponse */
				\do_action('payplug_gateway_payment_response_processed', $order_id, $resource);
				PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, false);
				PayplugGateway::log(sprintf('Order #%s : '. $this->gateway_name($gateway_id) .' payment IPN %s processing completed but failed.', $order_id, $resource->id));
				wc_increase_stock_levels($order);

				//$lock->deleteLock($resource->id);
				return;
			}

			elseif (( $resource->failure == null ) && !empty($resource->payment_method['is_pending'])
				&& ( $resource->payment_method['is_pending'] == true )) {
				$this->handle_pending_oney( $order, $resource );
			}

			// Save Logs of the payment for the different payment gateways:
			// For Oney 4 gateways & Bancontact gateway: "$resource->payment_method" exists and is an array
			// but not for Payplug credit card gateway (in this case we check the payment_method from the order itself not the $resource)
			if (isset($resource->payment_method) && is_array($resource->payment_method)) {
				$gateway_id = $resource->payment_method['type'];

				switch ($gateway_id) {
					case substr( $gateway_id, 0, 5 ) === "oney_" :
						$this->oney_ipn($resource);
						break;
					case "bancontact" :
						$this->bancontact_ipn($resource);
						break;
					case "apple_pay" :
						$this->apple_pay_ipn($resource);
						break;
					case "american_express" :
						$this->amex_ipn($resource);
						break;
				}

			} elseif ($gateway_id == "payplug") {
				$this->payplug_ipn($resource);
			}

			// Handle successful payments
			if ($resource->is_paid) {
				PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, true);
				if (!$is_payment_with_token) {
					$this->maybe_save_card($resource);
				}
				$this->maybe_save_address_hash($resource);
				$order->add_order_note(sprintf(__('PayPlug IPN OK | Transaction %s', 'payplug'), wc_clean($resource->id)));
				$order->payment_complete(wc_clean($resource->id));
				if (PayplugWoocommerceHelper::is_pre_30()) {
					$order->reduce_order_stock();
				}
				/**
				 * Fires once a payment response has been processed.
				 *
				 * @param int $order_id Order ID
				 * @param PaymentResource $resource Payment resource
				 */
				\do_action('payplug_gateway_payment_response_processed', $order_id, $resource);
				PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, false);
				PayplugGateway::log(sprintf('Order #%s : '. $this->gateway_name($gateway_id) .' payment IPN %s processing completed successfully.', $order_id, $resource->id));
			}

		}
	}

	/**
	 *
	 * Handle pending oney payments orders
	 *
	 * @param $order
	 * @param $resource
	 *
	 * @return void
	 */

	public function handle_pending_oney($order, $resource) {
		$order->add_order_note(sprintf(__('PayPlug IPN OK | Transaction %s | Payment PENDING to be checked by an Oney agent', 'payplug'), wc_clean($resource->id)));
		wc_reduce_stock_levels( $order );
	}

	/**
	 * Payplug IPN
	 *
	 * @param $resource
	 */
	public function payplug_ipn($resource) {
		$order_id = wc_clean( $resource->metadata['order_id'] );
		PayplugGateway::log( sprintf( 'Order #%s : Begin processing Payplug payment IPN %s', $order_id, $resource->id ) );
	}

	/**
	 * Bancontact IPN
	 *
	 * @param $resource
	 */
	public function bancontact_ipn($resource) {
		$order_id = wc_clean( $resource->metadata['order_id'] );
		PayplugGateway::log( sprintf( 'Order #%s : Begin processing Bancontact payment IPN %s', $order_id, $resource->id ) );
	}

	/**
	 * Apple Pay IPN
	 *
	 * @param $resource
	 */
	public function apple_pay_ipn($resource) {
		$order_id = wc_clean( $resource->metadata['order_id'] );
		PayplugGateway::log( sprintf( 'Order #%s : Begin processing Apple Pay payment IPN %s', $order_id, $resource->id ) );
	}

	/**
	 * American Express IPN
	 *
	 * @param $resource
	 */
	public function amex_ipn($resource) {
		$order_id = wc_clean( $resource->metadata['order_id'] );
		PayplugGateway::log( sprintf( 'Order #%s : Begin processing Amex payment IPN %s', $order_id, $resource->id ) );
	}

	/**
	 * Oney IPN
	 *
	 * @param $resource
	 */
	public function oney_ipn( $resource ) {
		$gateway_id = $resource->payment_method['type'];
		$order_id = wc_clean( $resource->metadata['order_id'] );

		if ( ( $resource->is_paid == true ) && ( $resource->failure == null ) ) {
			PayplugGateway::log( sprintf( 'Order #%s : '. $this->gateway_name($gateway_id) .' payment SUCCESS', $order_id ) );
		}

		if ( ( $resource->is_paid == false ) && ( $resource->failure != null ) ) {
			PayplugGateway::log( sprintf( 'Order #%s : '. $this->gateway_name($gateway_id) .' payment FAILED', $order_id ) );
		}

		if ( ( $resource->is_paid == false ) && ( $resource->failure == null ) && ( $resource->payment_method['is_pending'] == true ) ) {
			PayplugGateway::log( sprintf( 'Order #%s : '. $this->gateway_name($gateway_id) .' payment PENDING and checked by an Oney agent', $order_id ) );
		}
	}

	/**
	 * Get the gateway name from the gateway_id
	 *
	 * @param $gateway_id
	 */
	private function gateway_name($gateway_id){
		return ucwords(str_replace("_", " ", $gateway_id));
	}

	/**
	 * Process refund.
	 *
	 * @param $resource
	 * @param bool $ignore_woocommerce_refund Flag to determine if IPN notification for refunds created by WooCommerce
	 *                                        should be ignored or not. Default to true.
	 *
	 * @throws \Exception
	 */
	public function process_refund( $resource, $ignore_woocommerce_refund = true ) {
		$refund_id      = wc_clean( $resource->id );
		$transaction_id = wc_clean( $resource->payment_id );
		$order          = $this->get_order_from_transaction_id( $transaction_id );
		if ( ! $order ) {
			PayplugGateway::log( sprintf( 'Coudn\'t find order for transaction %s (Refund %s).', wc_clean( $resource->payment_id ), wc_clean( $resource->id ) ), 'error' );

			return;
		}
		$order_id = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();

		PayplugGateway::log( sprintf( 'Order #%s : Begin processing refund IPN %s', $order_id, $resource->id ) );

		$refund_exist = $this->refund_exist_for_order( $order_id, $refund_id );
		if ( $refund_exist ) {
			PayplugGateway::log( sprintf( 'Order #%s : Refund has already been processed. Ignoring IPN.', $order_id ) );

			return;
		}

		// Since refund notification doesn't contain the full resource, we need to retrieve
		// the refund resource to access its metadata.
		try {
			$refund = $this->gateway->api->refund_retrieve( $transaction_id, $refund_id );
		} catch ( \Exception $e ) {
			PayplugGateway::log( sprintf( 'Order #%s : Fail to retrieve refund data with error %s', $order_id, $e->getMessage() ) );

			return;
		}

		if (
			$ignore_woocommerce_refund
			&& isset( $refund->metadata['refund_from'] )
			&& 'woocommerce' === $refund->metadata['refund_from']
		) {
			PayplugGateway::log( sprintf( 'Order #%s : Refund created by WooCommerce. Ignoring IPN.', $order_id ) );

			return;
		}

		$refund = wc_create_refund( [
			'amount'         => ( (int) $resource->amount ) / 100,
			'reason'         => isset( $resource->metadata['reason'] ) ? $resource->metadata['reason'] : null,
			'order_id'       => (int) $order_id,
			'refund_id'      => 0,
			'refund_payment' => false,
		] );
		if ( is_wp_error( $refund ) ) {
			PayplugGateway::log( $refund->get_error_message() );
		}

		$refund_meta_key = sprintf( '_pr_%s', wc_clean( $resource->id ) );
		if ( PayplugWoocommerceHelper::is_pre_30() ) {
			update_post_meta( $order_id, $refund_meta_key, $resource->id );
		} else {
			$order->add_meta_data( $refund_meta_key, $resource->id, true );
			$order->save();
		}

		$note = sprintf( __( 'Refund %s : Refunded %s', 'payplug' ), wc_clean( $resource->id ), wc_price( ( (int) $resource->amount ) / 100 ) );
		if ( ! empty( $resource->metadata['reason'] ) ) {
			$note .= sprintf( ' (%s)', esc_html( $resource->metadata['reason'] ) );
		}
		$order->add_order_note( $note );

		/**
		 * Fires once a refund response has been processed.
		 *
		 * @param int            $order_id Order ID
		 * @param RefundResource $resource Refund resource
		 */
		\do_action( 'payplug_gateway_refund_response_processed', $order_id, $resource );

		try {
			$payment = $this->gateway->api->payment_retrieve( $transaction_id );
			$metadata = PayplugWoocommerceHelper::extract_transaction_metadata( $payment );
			PayplugWoocommerceHelper::save_transaction_metadata( $order, $metadata );
		} catch ( \Exception $e ) {}

		PayplugGateway::log( sprintf( 'Order #%s : Refund IPN %s processing completed.', $order_id, $resource->id ) );
	}

	/**
	 * Get an order from its transaction id.
	 *
	 * @param int $transaction_id
	 *
	 * @return bool|\WC_Order
	 */
	protected function get_order_from_transaction_id( $transaction_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT post_id
				FROM $wpdb->postmeta
				WHERE meta_key = '_transaction_id'
				AND meta_value = %s
				",
				$transaction_id
			)
		);

		return ! is_null( $order_id ) ? wc_get_order( $order_id ) : false;
	}

	/**
	 * Check if a refund id already exist for the order.
	 *
	 * @param string $refund_id
	 *
	 * @return bool
	 */
	protected function refund_exist_for_order( $order_id, $refund_id ) {
		global $wpdb;

		$sql = "
			SELECT p.ID
			FROM $wpdb->posts p
			INNER JOIN $wpdb->postmeta pm
				ON p.ID = pm.post_id
			WHERE 1=1
			AND p.post_type = %s
			AND p.ID = %d
			AND pm.meta_key LIKE '_pr_" . esc_sql( $refund_id ) . "'
			AND pm.meta_value = %s
			LIMIT 1
		";

		$results = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				'shop_order',
				(int) $order_id,
				$refund_id
			)
		);

		return ! empty( $results ) ? true : false;
	}

	/**
	 * Save card from the transaction.
	 *
	 * @param IVerifiableAPIResource $resource
	 *
	 * @return bool
	 */
	protected function maybe_save_card( $resource ) {

		if ( ! isset( $resource->card ) || empty( $resource->card->id ) ) {
			return false;
		}

		if ( ! isset( $resource->metadata['customer_id'] ) ) {
			return false;
		}

		$customer = get_user_by( 'id', $resource->metadata['customer_id'] );
		if ( ! $customer || 0 === (int) $customer->ID ) {
			return false;
		}

		$merchant_id = $this->gateway->get_merchant_id();
		if ( empty( $merchant_id ) ) {
			return false;
		}

		PayplugGateway::log( sprintf( 'Saving card from transaction %s for customer %s', wc_clean( $resource->id ), $customer->ID ) );

        $token = new WC_Payment_Token_CC();
        $existing_tokens = WC_Payment_Tokens::get_customer_tokens( $customer->ID , $this->gateway->id );
        $set_token = wc_clean( $resource->card->id );
        $set_last4 = wc_clean( $resource->card->last4 );
        $set_expiry_year =  wc_clean( $resource->card->exp_year ) ;
        $set_expiry_month = zeroise( (int) wc_clean( $resource->card->exp_month ), 2 ) ;
        $set_card_type =  \strtolower( wc_clean( $resource->card->brand ) ) ;
        if(!empty($existing_tokens)) {
            foreach($existing_tokens as $token_id => $existing_token) {
                $current_data = $existing_token->get_data();
                if( $current_data['token'] === $set_token &&
                    $current_data['last4'] === $set_last4 &&
                    $current_data['expiry_year'] === $set_expiry_year &&
                    $current_data['expiry_month'] === $set_expiry_month &&
                    $current_data['card_type'] === $set_card_type) {
                    $token->set_id($current_data['id']);
                }
            }
        }

		$token->set_token( $set_token );
		$token->set_gateway_id( 'payplug' );
		$token->set_last4( $set_last4 );
		$token->set_expiry_year( $set_expiry_year );
		$token->set_expiry_month( $set_expiry_month );
		$token->set_card_type( $set_card_type );
		$token->set_user_id( $customer->ID );
		$token->add_meta_data( 'mode', $resource->is_live ? 'live' : 'test', true );
		$token->add_meta_data( 'payplug_account', \wc_clean( $merchant_id ), true );
		$token->save();

		PayplugGateway::log( sprintf( 'Payment card saved', wc_clean( $resource->id ), $customer->ID ) );

		return true;
	}

	/**
	 * Save shipping address.
	 *
	 * @param IVerifiableAPIResource $resource
	 *
	 * @return bool
	 */
	protected function maybe_save_address_hash( $resource ) {

		if ( ! isset( $resource->metadata['customer_id'] ) ) {
			return false;
		}

		$customer = get_user_by( 'id', $resource->metadata['customer_id'] );
		if ( ! $customer || 0 === (int) $customer->ID ) {
			return false;
		}

		$shipping = [];
		foreach ( PayplugAddressData::$address_fields as $field ) {
			$shipping[ $field ] = $resource->shipping->{$field};
		}

		$shipping_hash = PayplugAddressData::hash_address( $shipping );
		$hash_list     = PayplugAddressData::get_customer_addresses_hash( $customer->ID );
		$hash_list[]   = $shipping_hash;
		$hash_list     = array_unique( $hash_list );
		PayplugAddressData::update_customer_addresses_hash( $customer->ID, $hash_list );

		return true;
	}
}
