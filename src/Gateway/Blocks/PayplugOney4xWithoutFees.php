<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugOney4xWithoutFees extends PayplugOney3xWithoutFees {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = "oney_x4_without_fees";

	protected $icon = 'x4_without_fees_';

	public function get_payment_method_data() {

		$data = parent::get_payment_method_data();

		$data['translations']['3rd_monthly_payment'] = __( '3rd monthly payment', 'payplug' );

		return $data;
	}

}
