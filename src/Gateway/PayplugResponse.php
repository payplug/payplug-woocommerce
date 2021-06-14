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
	public function process_payment( $resource, $is_payment_with_token = false ) {
		$order_id = wc_clean( $resource->metadata['order_id'] );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			PayplugGateway::log( sprintf( 'Coudn\'t find order #%s (Transaction %s).', $order_id, wc_clean( $resource->id ) ), 'error' );

			return;
		}

		PayplugGateway::log( sprintf( 'Order #%s : Begin processing payment IPN %s', $order_id, $resource->id ) );

		// Ignore paid orders
		if ( $order->is_paid() ) {
			PayplugGateway::log( sprintf( 'Order #%s : Order is already complete. Ignoring IPN.', $order_id ) );

			return;
		}

		// Ignore cancelled orders
		if ( $order->has_status( 'refunded' ) ) {
			PayplugGateway::log( sprintf( 'Order #%s : Order has been refunded. Ignoring IPN', $order_id ) );

			return;
		}

		$metadata = PayplugWoocommerceHelper::extract_transaction_metadata( $resource );
		$order_metadata = $order->get_meta('_payplug_metadata', true);

		if (is_array($order_metadata) && array_key_exists('transaction_in_progress', $order_metadata)) {
			PayplugGateway::log(sprintf('Order #%s : Order Oney IPN already in progress. Ignoring IPN', $order_id));
			return;
		}

		if ( $resource->is_paid ) {
			PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, true);
			if ( ! $is_payment_with_token ) {
				$this->maybe_save_card( $resource );
			}

			$this->maybe_save_address_hash( $resource );

			$order->add_order_note( sprintf( __( 'PayPlug IPN OK | Transaction %s', 'payplug' ), wc_clean( $resource->id ) ) );
			$order->payment_complete( wc_clean( $resource->id ) );
			if ( PayplugWoocommerceHelper::is_pre_30() ) {
				$order->reduce_order_stock();
			}

			/**
			 * Fires once a payment response has been processed.
			 *
			 * @param int             $order_id Order ID
			 * @param PaymentResource $resource Payment resource
			 */
			\do_action( 'payplug_gateway_payment_response_processed', $order_id, $resource );
			PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, false);
			PayplugGateway::log( sprintf( 'Order #%s : Payment IPN %s processing completed.', $order_id, $resource->id ) );

			return;
		}

		if ( ! empty( $resource->failure ) ) {
			PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, true);
			$order->update_status(
				'failed',
				sprintf( __( 'PayPlug IPN OK | Transaction %s failed : %s', 'payplug' ), $resource->id, wc_clean( $resource->failure->message ) )
			);

			/** This action is documented in src/Gateway/PayplugResponse */
			\do_action( 'payplug_gateway_payment_response_processed', $order_id, $resource );
			PayplugWoocommerceHelper::set_flag_ipn_order($order, $metadata, false);
			PayplugGateway::log( sprintf( 'Order #%s : Payment IPN %s processing completed.', $order_id, $resource->id ) );

			return;
		}
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
