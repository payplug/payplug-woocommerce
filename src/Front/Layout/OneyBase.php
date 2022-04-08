<?php

namespace Payplug\PayplugWoocommerce\Front\Layout;

Abstract Class OneyBase implements InterfaceOneyResources
{

	public function __construct()
	{
		add_action( 'wp_enqueue_scripts', self::addOneyCSS() );
		add_action( 'wp_enqueue_scripts', self::addOneyJs() );
		add_action( 'wp_enqueue_scripts', self::addOneyScript());
	}

	/**
	 * @global WP_Scripts $wp_scripts The WP_Scripts object for printing scripts.
	 *
	 */
	static function addOneyScript() {
		wp_localize_script('payplug-oney', 'payplug_config', array(
			'ajax_url'      => admin_url('admin-ajax.php'),
			'ajax_action'   => 'simulate_oney_payment'
		));

	}

	/**
	 * Add CSS
	 *
	 */
	static function addOneyCSS() {
		wp_enqueue_style('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/css/payplug-oney.css', [], PAYPLUG_GATEWAY_VERSION);
	}

	/**
	 * Add JS
	 *
	 */
	static function addOneyJs() {
		wp_enqueue_script('payplug-oney-mobile', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-detect-mobile.js', [], PAYPLUG_GATEWAY_VERSION, true);
		wp_enqueue_script('payplug-oney', PAYPLUG_GATEWAY_PLUGIN_URL . 'assets/js/payplug-oney.js', [
			'jquery',
			'jquery-ui-position'
		], PAYPLUG_GATEWAY_VERSION, true);
	}

	/**
	 * Popup Content for Oney Without fees
	 * @param $oney
	 * @return mixed
	 */
	abstract static function simulationPopupContentWithoutFees($oney);

	/**
	 * Popup Content for Oney With fees
	 * @param $oney
	 * @return mixed
	 */
	abstract static function simulationPopupContent($oney);

	/**
	 * Popup footer for Oney without fees
	 * @param $min
	 * @param $max
	 * @return mixed
	 */
	abstract static function footerOneyWithoutFees($min, $max);

	/**
	 * Popup footer for Oney with fees
	 * @param $min
	 * @param $max
	 * @return mixed
	 */
	abstract static function footerOneyWithFees($min, $max);

	/**
	 * disable Oney image on cart
	 * @param $oney
	 * @return mixed
	 */
	abstract static function disabledOneyPopup($oney);

	/**
	 * Oney image on cart
	 * @param $oney
	 * @return mixed
	 */
	abstract static function payWithOney($oney);

}
