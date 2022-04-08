<?php

namespace Payplug\PayplugWoocommerce\Front;

use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;

class PayplugPayWithOney
{

	/**
	 * Check if Oney can add JS & CSS in Shop
	 *
	 * @return void
	 */
	public function check_oney_frontend() {

		if ( ( is_cart() || is_checkout()) && PayplugWoocommerceHelper::is_oney_available()) {

			add_action('woocommerce_cart_totals_after_order_total', [$this, 'oney_simulate_payment_detail']);
		//	add_action('woocommerce_review_order_before_payment', [$this, 'oney_simulate_payment_detail']);

			add_action( 'wp_enqueue_scripts', [$this, 'add_oney_css'] );

			add_action( 'wp_enqueue_scripts', [$this, 'add_oney_js'] );

			add_action( 'wp_enqueue_scripts', [$this, 'add_oney_script'] );

		}

	}

	/**
	 * Button to show oney popup
	 *
	 * @return void
	 */
	public function oney_simulate_payment_detail()
	{
		global $product;
		$total_price = (is_cart()) ? floatval(WC()->cart->total) : (float) ($product->get_price());
		$this->setTotalPrice($total_price);

		$this->handleTotalProducts();

		if ($this->getTotalPrice() < $this->get_min_amount() || $this->getTotalPrice() > $this->get_max_amount() || $this->getTotalProducts() >= PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM) {
			$this->setDisable(true);
		}

		echo self::payWithOney($this);
		echo self::disabledOneyPopup($this);

	}

	/**
	 * @global WP_Scripts $wp_scripts The WP_Scripts object for printing scripts.
	 *
	 */
	public function add_oney_script() {
		wp_localize_script('payplug-oney', 'payplug_config', array(
			'ajax_url'      => admin_url('admin-ajax.php'),
			'ajax_action'   => 'simulate_oney_payment'
		));

	}

	/**
	 * Add CSS
	 *
	 */
	public function add_oney_css() {
		wp_enqueue_style('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-oney.css', [], PAYPLUG_GATEWAY_VERSION);
	}

	/**
	 * Add JS
	 *
	 */
	public function add_oney_js() {
		wp_enqueue_script('payplug-oney-mobile', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-detect-mobile.js', [], PAYPLUG_GATEWAY_VERSION, true);
		wp_enqueue_script('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-oney.js', [
			'jquery',
			'jquery-ui-position'
		], PAYPLUG_GATEWAY_VERSION, true);
	}

	/**
	 * disabled oney popup
	 * @param PayplugOney $oney
	 * @return string
	 */
	static function disabledOneyPopup($oney)
	{
		return '<div class="payplug-oney ' . $oney->isDisable() . '" id="oney-popup">
			<div class="payplug-lds-roller"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
			<div id="oney-popup-error">
				<div class="oney-error range"> ' . sprintf( __('The total amount of your order should be between %s€ and %s€ to pay with Oney.', 'payplug'), $oney->get_min_amount(), $oney->get_max_amount()) . '</div>
				<div class="oney-error qty">' . sprintf(__('The payment with Oney is unavailable because you have more than %s items in your cart.', 'payplug'), PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM) . '</div>
			</div>
		</div>';
	}

	/**
	 * header for cart oney
	 * @return string
	 */
	static function payWithOney($oney)
	{
		return '
			<div class="payplug-oney ' . $oney->isDisable(). '"
				 data-is-cart="' . (is_cart() ? 1 : 0) . '"
				 data-total-products="' . $oney->getTotalProducts() . '"
				 data-price="' .  $oney->getTotalPrice() .'"
				 data-max-oney-qty="' .  PayplugGatewayOney3x::ONEY_PRODUCT_QUANTITY_MAXIMUM .'"
				 data-min-oney="' .  $oney->get_min_amount() . '"
				 data-max-oney="' .  $oney->get_max_amount() . '">
				' . __('OR PAY IN', 'payplug') . '
				<div class="payplug-oney-popup">
					<div class="oney-img ' . $oney->getIcon() . '"></div>
					<div id="oney-show-popup" class="bold oney-color">?</div>
				</div>
			</div>';
	}

}
