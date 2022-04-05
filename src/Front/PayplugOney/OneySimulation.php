<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney;

use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;

class OneySimulation implements InterfaceOneySimulation
{

	/**
	 * Dependency Injection
	 * @var \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyBase
	 */
	private $oney;

	/**
	 * @var
	 */
	public $simulation = [];

	/**
	 * Dependency injection
	 * @param \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyBase $oney
	 */
	public function __construct($oney)
	{
		$this->oney = $oney;
	}

	/**
	 * Check if Oney Simulation is possible if it is returnss simulation data
	 * @return array|false|object
	 */
	public function OneySimulation()
	{
		$this->oney->setTotalPrice($_POST['price']);

		if ($this->oney->getTotalPrice() < $this->oney->get_min_amount() || $this->oney->getTotalPrice() > $this->oney->get_max_amount())
			return false;

		try {
			$this->simulation = $this->requestOneySimulation();

		} catch (\Exception $e) {
			PayplugGateway::log("Simulate Oney " . $this->oney->getOneyType() ." Fees Payment, " . $e->getMessage() );
			return false;

		}

		return $this->simulation;
	}

	/**
	 * return oney simulation data for the order
	 * @param $class
	 * @return array|object
	 */
	public function requestOneySimulation()
	{
		$class = $this->oney->getSimulatedClass();
		$api = new \Payplug\PayplugWoocommerce\Gateway\PayplugApi( new $class() );
		$api->init();
		return $api->simulate_oney_payment($this->oney->getTotalPrice(), $this->oney->getOneyType());
	}

}
