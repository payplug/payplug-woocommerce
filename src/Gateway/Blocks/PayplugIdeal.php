<?php

namespace Payplug\PayplugWoocommerce\Gateway\Blocks;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugIdeal extends PayplugGenericBlock
{

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'ideal';


	public function is_active()
	{
		$options = PayplugWoocommerceHelper::generic_get_account_data_from_options( $this->name );

		if (isset($options['permissions'][$this->get_name()]) && ($options['permissions'][$this->get_name()] == true)) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Returns an associative array of data to be exposed for the payment method's client side.
	 */
	public function get_payment_method_data() {
		$data = parent::get_payment_method_data();
		$data['icon'] =  [
			"src" => esc_url(PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/checkout/' . $this->gateway->image),
			'icon_alt' => $data['name'],
		];

		return $data;
	}






}
