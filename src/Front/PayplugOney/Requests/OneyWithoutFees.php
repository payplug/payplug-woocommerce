<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Requests;

use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class OneyWithoutFees extends OneyBase
{

	public function __construct()
	{
		$this->setCountry(PayplugWoocommerceHelper::get_payplug_options());

		/** @var $oney \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyFR */
		$class = "\\Payplug\\PayplugWoocommerce\\Front\\PayplugOney\\Country\\Oney" . $this->getCountry();
		$oney = new $class();
		$oney->setOneyType("without_fees");
		$oney->setIcon();
		$oney->setSimulatedClass('Payplug\\PayplugWoocommerce\\Gateway\\PayplugGatewayOney3xWithoutFees');
		$this->setOney($oney);

		parent::__construct();
	}

}
