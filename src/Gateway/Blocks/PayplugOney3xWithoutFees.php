<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugOney3xWithoutFees extends PayplugOney {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "oney_x3_without_fees";

	protected $icon = 'x3_without_fees.svg';


	public function oney_enabled() {
		$data    = parent::oney_enabled();
		$country = PayplugWoocommerceHelper::getISOCountryCode();
		if ( file_exists( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_' . $country . '.svg' ) ) {
			$image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_' . $country . '.svg';
		} else {
			$image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_FR.svg';
		}
		$data['icon'] = [
			'src'   => $image,
			'class' => 'payplug-payment-icon',
			'alt'   => $this->gateway->title
		];

		$data['oney_response'] = $this->gateway->api->simulate_oney_payment( $this->total_price, 'without_fees' );

		return $data;
	}

	public function oney_disabled() {
		$data    = parent::oney_disabled();
		$country = PayplugWoocommerceHelper::getISOCountryCode();

		if ( file_exists( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_' . $country . '.svg' ) ) {
			$data['icon']['src'] = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_' . $country . '.svg';
		} else {
			$data['icon']['src'] = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/x3_without_fees_FR.svg';
		}

		return $data;
	}


}
