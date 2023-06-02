<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\Exception\UnknownAPIResourceException;
use Payplug\Notification;
use Payplug\PayplugWoocommerce\Model\Lock;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Resource\IVerifiableAPIResource;
use WC_Payment_Token_CC;

class PayplugIpnResponse {

	private $gateway;

	/**
	 * PayplugIpnResponse constructor.
	 *
	 * @param PayplugGateway $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
		add_action( 'woocommerce_api_paypluggateway', [ $this, 'handle_ipn_response' ] );
	}

	public function handle_ipn_response() {
		$input = file_get_contents( 'php://input' );

		try {
			$resource = Notification::treat( $input );
		} catch ( UnknownAPIResourceException $e ) {
			PayplugGateway::log( sprintf( 'Error while parsing IPN payload : %s', $e->getMessage() ), 'error' );
			exit;
		}

		if ( ! $this->validate_ipn( $resource ) ) {
			PayplugGateway::log( sprintf( 'Resource %s is not supported (Transaction %s).', wc_clean( $resource->object ), wc_clean( $resource->id ) ), 'error' );
			exit;
		}

		if ( ! method_exists( $this, sprintf( 'process_%s_resource', wc_clean( $resource->object ) ) ) ) {
			PayplugGateway::log( sprintf( 'No method found to process resource %s (Transaction %s).', wc_clean( $resource->object ), wc_clean( $resource->id ) ), 'error' );
			exit;
		}

		call_user_func( [ $this, sprintf( 'process_%s_resource', $resource->object ) ], $resource );
		exit;
	}

	/**
	 * Validate IPN notification.
	 *
	 * @param IVerifiableAPIResource $resource
	 *
	 * @return bool
	 */
	public function validate_ipn( $resource ) {
		return isset( $resource->object ) && in_array( $resource->object, [ 'payment', 'refund' ] );
	}

	/**
	 * Process payment notification.
	 *
	 * @param $resource
	 *
	 * @return void
	 * @throws \WC_Data_Exception
	 */
	public function process_payment_resource( $resource ) {

		$lock_id = \Payplug\PayplugWoocommerce\Helper\Lock::handle_insert(true, $resource->id);
		if(!$lock_id){
			return;
		}

		$this->gateway->response->process_payment( $resource, false, "ipn" );

		Lock::delete_lock($lock_id);
		$waiting_requests = Lock::get_lock_by_payment_id($resource->id);

		if($waiting_requests){
			Lock::delete_lock($waiting_requests->id);
			$this->gateway->validate_payment($resource->metadata['order_id'], false);

		};
	}

	/**
	 * Process refund notification.
	 *
	 * @param $resource
	 */
	public function process_refund_resource( $resource ) {
		$this->gateway->response->process_refund( $resource );
	}
}
