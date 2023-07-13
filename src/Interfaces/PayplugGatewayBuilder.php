<?php

namespace Payplug\PayplugWoocommerce\Interfaces;

interface PayplugGatewayBuilder
{

	public function get_icon();

	public function display_notice();

	public function checkGateway();

	public function process_standard_payment($order, $amount, $customer_id);

	//TODO:: remove this when decouple CC from PayplugGateway only needed for old BO
	public function process_admin_options();

}
