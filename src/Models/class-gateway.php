<?php
/**
 * Base class for gateway classes.
 *
 * @package Payplug\PayplugWoocommerce\Models
 */

namespace Payplug\PayplugWoocommerce\Models;

use WC_Payment_Gateway_CC;
/**
 * PayPlug WooCommerce Gateway.
 */
if ( ! class_exists( 'Gateway' ) ) {
	/**
	 * PayPlug WooCommerce Gateway.
	 */
	class Gateway extends WC_Payment_Gateway_CC {

	}
}
