<?php
namespace Payplug\PayplugWoocommerce\Admin;
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\Gateway\PayplugResponse;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
/**
 * Custom WooCommerce actions.
 *
 * @package Payplug\PayplugWoocommerce\Admin
 */
class WoocommerceActions {
	const WC_PAYPLUG_RETRIEVE_ACTION = 'retrieve_payplug_transaction';
	public function __construct() {
		add_filter( 'woocommerce_order_actions', [ $this, 'add_payment_retrieve_action' ] );
		add_action( 'woocommerce_order_action_' . self::WC_PAYPLUG_RETRIEVE_ACTION, [
			$this,
			'handle_payment_retrieve_action'
		] );
	}
	/**
	 * Add custom PayPlug action.
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public function add_payment_retrieve_action( $actions = [] ) {
		/* @var \WC_Order $theorder */
		global $theorder;
		$order_id       = PayplugWoocommerceHelper::is_pre_30() ? $theorder->id : $theorder->get_id();
		$transaction_id = PayplugWoocommerceHelper::is_pre_30() ? get_post_meta( $order_id, '_transaction_id', true ) : $theorder->get_transaction_id();
		$payment_method = PayplugWoocommerceHelper::is_pre_30() ? $theorder->payment_method : $theorder->get_payment_method();
		if (!in_array($payment_method, ['oney_x3_with_fees', 'oney_x4_with_fees', 'payplug']) || empty($transaction_id)) {
			return $actions;
		} 
		$actions[ self::WC_PAYPLUG_RETRIEVE_ACTION ] = __( 'Update PayPlug transaction data', 'payplug' );
		return $actions;
	}
	/**
	 * Handle PayPlug custom action.
	 *
	 * Retrieve transaction data from PayPlug for the order.
	 *
	 * @param \WC_Order $order
	 *
	 * @return void
	 * @throws \WC_Data_Exception
	 */
	public function handle_payment_retrieve_action( $order ) {
		$order_id       = PayplugWoocommerceHelper::is_pre_30() ? $order->id : $order->get_id();
		$payment_method = PayplugWoocommerceHelper::is_pre_30() ? $order->payment_method : $order->get_payment_method();
		if (!in_array($payment_method, ['oney_x3_with_fees', 'oney_x4_with_fees', 'payplug'])) {
			return;
		}
		PayplugGateway::log( sprintf( 'Order #%s : Starting retrieve action process.', $order_id ), 'info' );
		$transaction_id = PayplugWoocommerceHelper::is_pre_30() ? get_post_meta( $order_id, '_transaction_id', true ) : $order->get_transaction_id();
		if ( empty( $transaction_id ) ) {
			PayplugGateway::log( sprintf( 'Order #%s : Missing transaction id.', $order_id ), 'error' );
			\WC_Admin_Meta_Boxes::add_error( __( 'No transaction ID was found for this order.', 'payplug' ) );
			return;
		}
		/* @var PayplugGateway $gateway */
		$gateway = wc_get_payment_gateway_by_order( $order );
		if (
			! $gateway
			|| ! $gateway->is_available()
			|| ! ( $gateway->response instanceof PayplugResponse )
		) {
			PayplugGateway::log( sprintf( 'Order #%s : Error with PayPlug gateway. PayPlug gateway not found or PayPlugResponse class not available.', $order_id ), 'error' );
			\WC_Admin_Meta_Boxes::add_error( __( 'An error occured with PayPlug gateway. Please make sure PayPlug settings are correct.', 'payplug' ) );
			return;
		}
		try {
			PayplugGateway::log( sprintf( 'Order #%s : Retrieve payment data for transaction %s.', $order_id, $transaction_id ), 'info' );
			$payment = $gateway->api->payment_retrieve( $transaction_id );
			PayplugGateway::log( sprintf( 'Order #%s : Process payment data.', $order_id ), 'info' );
			$gateway->response->process_payment( $payment );
		} catch ( \Exception $e ) {
			PayplugGateway::log(
				sprintf( 'Order #%s : An error occurred while retrieving the payment data with the message : %s',
					$order_id,
					$e->getMessage()
				)
			);
			\WC_Admin_Meta_Boxes::add_error( __( 'An error occurred while collecting payment information from PayPlug.', 'payplug' ) );
			return;
		}
		PayplugGateway::log( sprintf( 'Order #%s : Payment data updated.', $order_id ), 'info' );
		try {
			PayplugGateway::log( sprintf( 'Order #%s : Retrieve refunds for transaction %s', $order_id, $transaction_id ), 'info' );
			$refunds = $gateway->api->refund_list( $transaction_id );
			if ( ! empty( $refunds ) ) {
				PayplugGateway::log( sprintf( 'Order #%s : Found %d refund(s) for the transaction.', $order_id, count( $refunds ) ), 'info' );
				foreach ( $refunds as $refund ) {
					$gateway->response->process_refund( $refund, false );
				}
			}
		} catch ( \Exception $e ) {
			PayplugGateway::log(
				sprintf( 'Order #%s : An error occurred while retrieving the refund data with the message : %s',
					$order_id,
					$e->getMessage()
				)
			);
			\WC_Admin_Meta_Boxes::add_error( __( 'An error occurred while collecting refund information from PayPlug.', 'payplug' ) );
			return;
		}
		PayplugGateway::log( sprintf( 'Order #%s : Refunds data updated.', $order_id ), 'info' );
	}
}