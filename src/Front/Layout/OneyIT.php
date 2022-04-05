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
                    <p class='no-margin oney-color'>{$f(__('PAYMENT', 'payplug'))} <span class='underline'>{$f(__('WITHOUT FEES', 'payplug'))}</span> </p>
                    <p class='no-margin bold oney-color'>{$f(__('BY CREDIT CARD', 'payplug'))}</p>
                </div>
                <div class='oney-content oney-3x-content'>
                    <div class='oney-img oney-3x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$x3['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>+2  {$f(__('monthly payment of', 'payplug'))} : {$x3['installments'][0]['amount']} € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$financing_cost_3x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$x3['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
                <div class='oney-separator'></div>
                <div class='oney-content oney-4x-content'>
                    <div class='oney-img oney-4x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$x4['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>+3  {$f(__('monthly payment of', 'payplug'))} : {$x4['installments'][0]['amount']}  € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$financing_cost_4x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$x4['effective_annual_percentage_rate']}  % </p>
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
                    <p class='no-margin oney-color'>{$f(__('PAYMENT', 'payplug'))} </p>
                    <p class='no-margin bold oney-color'>{$f(__('BY CREDIT CARD', 'payplug'))}</p>
                </div>
                <div class='oney-content oney-3x-content'>
                    <div class='oney-img oney-3x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$x3['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>+2  {$f(__('monthly payment of', 'payplug'))} : {$x3['installments'][0]['amount']} € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$financing_cost_3x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$x3['effective_annual_percentage_rate']}  % </p>
                    </div>
                </div>
                <div class='oney-separator'></div>
                <div class='oney-content oney-4x-content'>
                    <div class='oney-img oney-4x no-margin'></div>
                    <div class='oney-details'>
                        <p class='bold no-margin'> {$f(__('Bring', 'payplug'))} : {$x4['down_payment_amount']}  €</p>
                        <p class='bold no-margin'>+3  {$f(__('monthly payment of', 'payplug'))} : {$x4['installments'][0]['amount']}  € </p>
                        <p class='no-margin'> {$f(__('Of which financing cost', 'payplug'))} : {$financing_cost_4x} € </p>
                        <p class='no-margin'> {$f(__('TAEG', 'payplug'))} : {$x4['effective_annual_percentage_rate']}  % </p>
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
 				{$f(sprintf(
			esc_html__("Offre de financement sans assurance avec apport obligatoire, réservée aux particuliers et valable pour tout achat de %s€ à %s€. Sous réserve d’acceptation par Oney Bank. Vous disposez d’un délai de 14 jours pour renoncer à votre crédit. Oney Bank - SA au capital de 51286585€ - 34 Avenue de Flandre 59 170 Croix - 546 380 197 RCS Lille Métropole - n° Orias 07023 261 %s .  Correspondance : CS 60 006 - 59895 Lille Cedex - %s", "payplug"),
			$min,
			$max,
			__("<a href='https://www.orias.fr' target='_empty' > www.orias.fr </a>"),
			__("<a href=\"https://www.oney.fr\" target=\"_empty\" > www.oney.fr </a>")
		))}
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

		return <<<HTML
 			<div class='oney-cgv-content oney-cgv-footer'>
 				{$f( sprintf(
			esc_html__("Offre de financement avec apport obligatoire, réservée aux particuliers et valable pour tout achat de %s€ à %s€. Sous réserve d'acceptation par Oney Bank. Vous disposez d'un délai de 14 jours pour renoncer à votre crédit. Oney Bank - SA au capital de 51 286 585€ - 34 Avenue de Flandre 59170 Croix - 546 380 197 RCS Lille Métropole - n° Orias 07 023 261 %s Correspondance : CS 60 006 - 59895 Lille Cedex - %s"),
			$min,
			$max,
			__("<a href='https://www.orias.fr' target='_empty' > www.orias.fr </a>"),
			__("<a href=\"https://www.oney.fr\" target=\"_empty\" > www.oney.fr </a>")
		))}
			</div>
HTML;

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
				' . __('OR PAY IN', 'payplug') . '
				<div class="payplug-oney-popup">
					<div class="oney-img ' . $oney->getIcon() . '"></div>
					<div id="oney-show-popup" class="bold oney-color">?</div>
				</div>
			</div>';
	}

}
