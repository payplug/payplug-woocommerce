<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Requests;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class OneyAnimation extends OneyBase {
	public function __construct($oney_type = "with_fees", $class_name = "PayplugGatewayOney3x")
	{
		$this->setCountry(PayplugWoocommerceHelper::get_payplug_options());

		/** @var $oney \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyFR */
		$class = "\\Payplug\\PayplugWoocommerce\\Front\\PayplugOney\\Country\\Oney" . $this->getCountry();
		$oney = new $class();
		$oney->setOneyType($oney_type);
		$oney->setIcon();
		$oney->setSimulatedClass("Payplug\\PayplugWoocommerce\\Gateway\\$class_name");
		$this->setOney($oney);

		parent::__construct();
	}
}
