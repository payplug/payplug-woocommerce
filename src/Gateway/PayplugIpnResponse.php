<?php

namespace Payplug\PayplugWoocommerce\Gateway;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Payplug\Exception\UnknownAPIResourceException;
use Payplug\Notification;
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
		$this->gateway->response->process_payment( $resource );
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