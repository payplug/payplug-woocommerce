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

	protected $icon = 'x3_without_fees_';


	public function oney_enabled() {
		$data    = parent::oney_enabled();
		$country = PayplugWoocommerceHelper::getISOCountryCode();
		if ( file_exists( PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/'.$this->icon . $country . '.svg' ) ) {
			$image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/'. $this->icon. $country . '.svg';
		} else {
			$image = PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/images/checkout/'.$this->icon.'FR.svg';
		}
		$data['icon'] = [
			'src'   => $image,
			'class' => 'payplug-payment-icon',
			'alt'   => $this->gateway->title
		];

		$data['oney_response'] = $this->gateway->api->simulate_oney_payment( $this->total_price, 'without_fees' );

		return $data;
	}


}
