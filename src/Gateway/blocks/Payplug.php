<?php

namespace Payplug\PayplugWoocommerce\Gateway\blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Payplug extends AbstractPaymentMethodType
{
	/**
	 * plugin settings.
	 */
	protected $settings;

	/**
	 * plugin settings.
	 */
	public function initialize()
	{
		$this->settings = get_option( 'woocommerce_payplug_settings', [] );
		$gateways = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}


}
