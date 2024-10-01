<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

class PayplugOney4x extends PayplugOney {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "oney_x4_with_fees";

	protected $icon = 'x4_with_fees.svg';


	public function get_payment_method_data() {

		$data = parent::get_payment_method_data();

		$data['translations']['3rd_monthly_payment'] = __( '3rd monthly payment', 'payplug' );

		return $data;
	}


}
