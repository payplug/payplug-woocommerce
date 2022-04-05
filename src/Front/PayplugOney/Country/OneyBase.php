<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Country;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use function is_cart;
use function WC;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

Abstract Class OneyBase implements InterfaceOney
{
	/**
	 * @var string
	 */
	private $icon = 'oney-3x4x';

	/**
	 * @var bool
	 */
	private $disable = false;

	/**
	 * @var int
	 */
	private $min_amount = 0;

	/**
	 * @var int
	 */
    private $max_amount = 0;

	/**
	 * @var int
	 */
	private $total_products = 0;

	/**
	 * @var int
	 */
	private $total_price = 0;

	/**
	 * @var string
	 */
	private $simulatedClass = '';

	/**
	 * @var string
	 */
	private $oney_type="with_fees";

	/**
	 * @var array
	 */
	private $payplugOptions = [];

	/**
	 * @var int
	 */
	private $max_default_amount = 3000;

	/**
	 * @var int
	 */
	private $min_default_amount = 100;

	public function __construct()
	{
		$this->payplugOptions = PayplugWoocommerceHelper::getOneySettings();
		$max = ( !empty($this->payplugOptions['oney_thresholds_max']) && (int) $this->payplugOptions['oney_thresholds_max'] <= $this->max_default_amount ) ? $this->payplugOptions['oney_thresholds_max'] : $this->max_default_amount;
		$min = ( !empty($this->payplugOptions['oney_thresholds_min']) && (int) $this->payplugOptions['oney_thresholds_min'] >= $this->min_default_amount) ? $this->payplugOptions['oney_thresholds_min'] : $this->min_default_amount;

		$this->set_max_amount( $max );
		$this->set_min_amount( $min );
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

	/**
	 * @return array
	 */
	public function getPayplugOptions()
	{
		return $this->payplugOptions;
	}

	/**
	 * @param array $payplugOptions
	 */
	public function setPayplugOptions($payplugOptions)
	{
		$this->payplugOptions = $payplugOptions;
	}


}
