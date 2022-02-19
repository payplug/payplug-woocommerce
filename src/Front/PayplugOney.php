<?php

namespace Payplug\PayplugWoocommerce\Front;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PayplugOney
{
    private $min_amount = 0;
    private $max_amount = 0;
	private $icon = '';
	private $disable = false;
	private $total_products = 0;
	private $total_price = 0;
	private $simulatedClass = '';
	private $oney_type="with_fees";

	public function __construct()
	{
		$this->handleMinMaxAmount();
	}

	/**
	 * set min and max allowed amount
	 */
	public function handleMinMaxAmount()
	{
		$oney_range = PayplugWoocommerceHelper::get_min_max_oney();
		$this->set_max_amount($oney_range['max']);
		$this->set_min_amount($oney_range['min']);
	}

	public function handleOneySimulation()
	{
		$this->setTotalPrice($_POST['price']);

		if ($this->getTotalPrice() < $this->get_min_amount() || $this->getTotalPrice() > $this->get_max_amount())
			return false;

		try {
			$simulation = $this->requestOneySimulation();

		} catch (\Exception $e) {
			PayplugGatewayOney3x::log("Simulate Oney Without Fees Payment, " . $e->getMessage() );
			return false;

		}

		return $simulation;
	}

	/**
	 * return oney simulation data for the order
	 * @param $class
	 * @return array|object
	 */
	public function requestOneySimulation()
	{
		$class = $this->getSimulatedClass();
		$api = new \Payplug\PayplugWoocommerce\Gateway\PayplugApi( new $class() );
		$api->init();
		return $api->simulate_oney_payment($this->getTotalPrice(), $this->getOneyType());
	}

	/**
	 * SUM totalproducts that are in the cart
	 */
	public function handleTotalProducts()
	{
		$this->addTotalProducts(1);
		if(is_cart()) {
			$this->resetTotalProducts();
			foreach(WC()->cart->cart_contents as $product) {
				$this->addTotalProducts($product['quantity']);
			}
		}
	}

	/**
	 * @param $oney
	 * @param $oney_response
	 * @return string
	 */
	static function simulationPopupContent($oney, $x3oney_response, $x4oney_response ){

		$financing_cost_3x = intval($x3oney_response['total_cost']) / 100;
		$financing_cost_4x = intval($x4oney_response['total_cost']) / 100;

		$f = function($fn) { return $fn; };

		$without_fees_text = "";
		if($oney->getOneyType() === "without_fees" )
			$without_fees_text = "<span class='underline'>{$f(__('WITHOUT FEES', 'payplug'))}</span>";

		$pop_content = <<<HTML
                <div id='oney-popup-close'>
                    <div class='oney-popup-close-mdiv'>
                        <div class='oney-popup-close-md'></div>
                    </div>
                </div>
                <div class='oney-img oney-logo no-margin'></div>
                <div class='oney-title'>
                    <p class='no-margin oney-color'>{$f(__('PAYMENT', 'payplug'))} $without_fees_text </p>
                    <p class='no-margin bold oney-color'>{$f(__('BY CREDIT CARD', 'payplug'))}</p>
                </div>
                <div class='oney-content oney-3x-content'>
                    <div class='oney-img oney-3x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$x3oney_response['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>+2  {$f(__('monthly payment of', 'payplug'))} : {$x3oney_response['installments'][0]['amount']} € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$financing_cost_3x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$x3oney_response['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
                <div class='oney-separator'></div>
                <div class='oney-content oney-4x-content'>
                    <div class='oney-img oney-4x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$x4oney_response['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>+3  {$f(__('monthly payment of', 'payplug'))} : {$x4oney_response['installments'][0]['amount']}  € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$financing_cost_4x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$x4oney_response['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
HTML;

		return $pop_content;
	}

	/**
	 * Setter
	 * @param $amount
	 */
	public function set_min_amount($amount)
	{
		$this->min_amount = (int) $amount;
	}

	/**
	 * Setter
	 * @param $amount
	 */
	public function set_max_amount($amount)
	{
		$this->max_amount = (int) $amount;
	}

	/**
	 * @return int
	 */
	public function get_min_amount()
	{
		return $this->min_amount;
	}

	/**
	 * @return int
	 */
	public function get_max_amount()
	{
		return $this->max_amount;
	}

	/**
	 * @return string
	 */
	public function getIcon()
	{
		return $this->icon;
	}

	/**
	 * @param string $icon
	 */
	public function setIcon($icon)
	{
		$this->icon = $icon;
	}

	/**
	 * @return bool
	 */
	public function isDisable()
	{
		if($this->disable){
			return "disabled";
		}

		return '';
	}

	/**
	 * @param bool $disable
	 */
	public function setDisable($disable)
	{
		$this->disable = $disable;
	}

	/**
	 * @param $qty
	 * @return void
	 */
	public function addTotalProducts($qty)
	{
		$this->total_products += $qty;
	}

	/**
	 * @set total_products to 0
	 */
	public function resetTotalProducts()
	{
		$this->total_products = 0;
	}

	public function getTotalProducts()
	{
		return $this->total_products;
	}

	/**
	 * @return int
	 */
	public function getTotalPrice()
	{
		return $this->total_price;
	}

	/**
	 * @param int $total_price
	 */
	public function setTotalPrice($total_price)
	{
		$this->total_price = $total_price;
	}

	/**
	 * @return string
	 */
	public function getSimulatedClass()
	{
		return $this->simulatedClass;
	}

	/**
	 * @param string $simulatedClass
	 */
	public function setSimulatedClass($simulatedClass)
	{
		$this->simulatedClass = $simulatedClass;
	}

	/**
	 * @return string
	 */
	public function getOneyType()
	{
		return $this->oney_type;
	}

	/**
	 * @param string $oney_type
	 */
	public function setOneyType($oney_type)
	{
		$this->oney_type = $oney_type;
	}


}
