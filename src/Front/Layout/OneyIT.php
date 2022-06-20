<?php

namespace Payplug\PayplugWoocommerce\Front\Layout;

use Payplug\PayplugWoocommerce\Front\PayplugOney\Requests\OneyWithFees;
use Payplug\PayplugWoocommerce\Front\PayplugOney\Requests\OneyWithoutFees;
use Payplug\PayplugWoocommerce\Gateway\PayplugGatewayOney3x;
use function is_cart;

class OneyIT extends OneyBase implements InterfaceOneyLayout
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @param OneyWithoutFees $oney
	 * @return string
	 */
	static function simulationPopupContentWithoutFees( $oney ){

		$simulationResponse = $oney->getSimulation();

		$x4 = $simulationResponse['x4_without_fees'];
		$x3 = $simulationResponse['x3_without_fees'];

		$financing_cost_3x = intval($x3['total_cost']) / 100;
		$financing_cost_4x = intval($x4['total_cost']) / 100;

		$f = function($fn) { return $fn; };

		return <<<HTML
                <div id='oney-popup-close'>
                    <div class='oney-popup-close-mdiv'>
                        <div class='oney-popup-close-md'></div>
                    </div>
                </div>
                <div class='oney-img oney-logo no-margin'></div>
                <div class='oney-title'>
                    <p class='no-margin oney-color'>{$f(__('payplug_oneyIT_popup-withoutfees-title', 'payplug'))} </p>
                    <p class='no-margin bold oney-color'>{$f(__('BY CREDIT CARD', 'payplug'))}</p>
                </div>
                <div class='oney-content oney-3x-content'>
                    <div class='oney-img oney-3x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))}: {$x3['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>{$f(sprintf(__('IT monthly payment of', 'payplug'),2))}: {$x3['installments'][0]['amount']} € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))}: {$financing_cost_3x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))}: {$x3['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
                <div class='oney-separator'></div>
                <div class='oney-content oney-4x-content'>
                    <div class='oney-img oney-4x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))}: {$x4['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>{$f(sprintf(__('IT monthly payment of', 'payplug'), 3))}: {$x4['installments'][0]['amount']}  € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))}: {$financing_cost_4x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))}: {$x4['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
HTML;

	}

	/**
	 * @param OneyWithFees $oney
	 * @return string
	 */
	static function simulationPopupContent($oney)
	{
		$simulationResponse = $oney->getSimulation();

		$x4 = $simulationResponse['x4_with_fees'];
		$x3 = $simulationResponse['x3_with_fees'];

		$financing_cost_3x = intval($x3['total_cost']) / 100;
		$financing_cost_4x = intval($x4['total_cost']) / 100;

		$f = function($fn) { return $fn; };

		return <<<HTML
                <div id='oney-popup-close'>
                    <div class='oney-popup-close-mdiv'>
                        <div class='oney-popup-close-md'></div>
                    </div>
                </div>
                <div class='oney-img oney-logo no-margin'></div>
                <div class='oney-title'>
                    <p class='no-margin oney-color'>{$f(__('payplug_oneyIT_popup-title', 'payplug'))} </p>
                    <p class='no-margin bold oney-color'>{$f(__('BY CREDIT CARD', 'payplug'))}</p>
                </div>
                <div class='oney-content oney-3x-content'>
                    <div class='oney-img oney-3x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))}: {$x3['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>{$f(sprintf(__('IT monthly payment of', 'payplug'),2))}: {$x3['installments'][0]['amount']} € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))}: {$financing_cost_3x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))}: {$x3['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
                <div class='oney-separator'></div>
                <div class='oney-content oney-4x-content'>
                    <div class='oney-img oney-4x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))}: {$x4['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>{$f(sprintf(__('IT monthly payment of', 'payplug'),3))}: {$x4['installments'][0]['amount']}  € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))}: {$financing_cost_4x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))}: {$x4['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
HTML;
	}

	/**
	 * @param $min
	 * @param $max
	 * @return string
	 */
	static function footerOneyWithoutFees($min, $max)
	{
		$f = function($fn) { return $fn; };

		$footer = <<<HTML
 			<div class='oney-cgv-content oney-cgv-footer'>
				{$f(sprintf(esc_html__('payplug_oneyIT_popup-footer', 'payplug'), $min, $max,
			__("payplug_oneyIT_popup-url", "payplug"),
			__("payplug_oneyIT-without-fees_popup-footer_pdf", "payplug")))}
			</div>
HTML;

		return $footer;

	}

	/**
	 * @param $min
	 * @param $max
	 * @return string
	 */
	static function footerOneyWithFees($min, $max)
	{
		$f = function($fn) { return $fn; };

		$footer =  <<<HTML
 			<div class='oney-cgv-content oney-cgv-footer'>
				{$f(sprintf(esc_html__('payplug_oneyIT_popup-footer', 'payplug'), $min, $max,
			__("payplug_oneyIT_popup-url", "payplug"),
			__("payplug_oneyIT_popup-footer_pdf", "payplug")))}
			</div>
HTML;
		return $footer;

	}

	/**
	 * disabled oney popup
	 * @param \Payplug\PayplugWoocommerce\Front\PayplugOney\Country\Oney $oney
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
				' . __('payplug_oneyIT_paywith', 'payplug') . '
				<div class="payplug-oney-popup">
					<div class="oney-img ' . $oney->getIcon() . '"></div>
					<div id="oney-show-popup" class="bold oney-color">?</div>
				</div>
			</div>';
	}

}
