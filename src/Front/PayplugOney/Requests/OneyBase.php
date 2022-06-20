<?php

namespace Payplug\PayplugWoocommerce\Front\PayplugOney\Requests;

use Payplug\PayplugWoocommerce\Front\PayplugOney\OneySimulation;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use function is_cart;
use function is_checkout;
use function is_product;

Abstract class OneyBase
{

	/**
	 * @var string
	 */
	private $country;

	/**
	 * @var OneySimulation
	 */
	private $simulation = [];

	/**
	 * Dependency injection
	 * @var \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\OneyBase
	 */
	private $oney;


	public function __construct()
	{
		add_action( 'wp_ajax_simulate_oney_payment', [ $this, 'simulateOneyPayment' ]);
		add_action( 'wp_ajax_nopriv_simulate_oney_payment', [ $this, 'simulateOneyPayment' ]);
		add_action( 'template_redirect', [ $this, 'showOneyAnimation' ] );

		$product_page_animation = get_option('woocommerce_payplug_settings', [])['oney_product_animation'];

		if ($product_page_animation == 'yes')
			add_action( 'woocommerce_before_add_to_cart_form', [ $this, 'showOneyAnimationProduct' ] );
	}

	/**
	 * request simulation
	 * print results
	 */
	public function simulateOneyPayment(){
		$simulation = new OneySimulation($this->oney);
		$this->simulation = $simulation->OneySimulation();
		$html = $this->drawAnimation();

		wp_send_json_success(
			array(
				'popup' => $html
			)
		);

		wp_die();
	}

	/**
	 * draw html popup
	 * @return string
	 */
	public function drawAnimation(){
		$class = "Payplug\\PayplugWoocommerce\\Front\\Layout\\Oney" . $this->getCountry();

		switch($this->oney->getOneyType()){
			case "without_fees":
				$footer = $class::footerOneyWithoutFees($this->oney->get_min_amount(), $this->oney->get_max_amount());
				$content = $class::simulationPopupContentWithoutFees($this);
				break;
			default:
				$footer = $class::footerOneyWithFees($this->oney->get_min_amount(), $this->oney->get_max_amount());
				$content = $class::simulationPopupContent($this);
				break;
		}

		$html = <<<HTML
 			$content
			$footer
HTML;
		return $html;

	}

	/**
	 * Button to show oney popup
	 *
	 * @return void
	 */
	public function showOneyAnimation()
	{

		if ( ( is_cart() || is_checkout()) && PayplugWoocommerceHelper::is_oney_available()) {
			global $product;

			$total_price = (is_numeric( floatval(WC()->cart->total))) ? floatval(WC()->cart->total) : (float)($product->get_price());
			$this->oney->setTotalPrice($total_price);
			$this->oney->handleTotalProducts();

			if ($this->oney->getTotalPrice() < $this->oney->get_min_amount() || $this->oney->getTotalPrice() > $this->oney->get_max_amount() || $this->oney->getTotalProducts() >= PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM) {
				$this->oney->setDisable(true);
			}

			add_action('woocommerce_cart_totals_after_order_total', [$this, 'oneyGeneratePopup']);
		}

	}

	/**
	 * Button to show oney popup product page
	 *
	 * @return void
	 */
	public function showOneyAnimationProduct()
	{
		if ( (is_product()) && PayplugWoocommerceHelper::is_oney_available()) {
			global $product;

			$total_price = $product->get_price();
			$this->oney->setTotalPrice($total_price);
			$this->oney->handleTotalProducts();

			if ($product->get_price() < $this->oney->get_min_amount() || $product->get_price() > $this->oney->get_max_amount() || $this->oney->getTotalProducts() >= PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM) {
				$this->oney->setDisable(true);
			}

			$this->oneyGeneratePopup();
		}

	}

	/**
	 * get Html for Oney
	 */
	public function oneyGeneratePopup(){
		$class = "Payplug\\PayplugWoocommerce\\Front\\Layout\\Oney" . $this->getCountry();
		$class = new $class();
		echo $class::payWithOney($this->oney);
		echo $class::disabledOneyPopup($this->oney);

	}

	/**
	 * @param $options
	 * @return void
	 */
	public function setCountry($options){
		$this->country = $options["payplug_merchant_country"] ? $options["payplug_merchant_country"] : ["payplug_merchant_country" => "FR"];
	}

	/**
	 * @return string
	 */
	public function getCountry(){
		return $this->country;
	}

	/**
	 * @return array|OneySimulation
	 */
	public function getSimulation(){
		return $this->simulation;
	}

	/**
	 * @param $oney
	 */
	public function setOney($oney)
	{
		$this->oney = $oney;
	}

}
