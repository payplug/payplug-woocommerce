<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugOney4x extends PayplugOney3x {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "oney_x4_with_fees";

	protected $icon = 'x4_with_fees.svg';


	public function oney_enabled() {

		$data = parent::oney_enabled();

		$data['translations']['3rd monthly payment'] = __( '3rd monthly payment', 'payplug' );

		return $data;
	}


}
