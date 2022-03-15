<?php

namespace Payplug\PayplugWoocommerce\Front;

class PayplugOneyWithFees extends PayplugPayWithOney
{

	public function __construct()
	{
		parent::__construct();

		$this->setIcon('oney-3x4x');
		$this->setSimulatedClass('Payplug\\PayplugWoocommerce\\Gateway\\PayplugGatewayOney3x');
		$this->setOneyType("with_fees");

		add_action( 'wp_ajax_simulate_oney_payment', [ $this, 'simulate_oney_payment' ]);
		add_action( 'wp_ajax_nopriv_simulate_oney_payment', [ $this, 'simulate_oney_payment' ]);
		add_action( 'template_redirect', [ $this, 'check_oney_frontend' ] );
	}

	/**
	 * Simulate Oney Payment
	 *
	 * @return void
	 */
	public function simulate_oney_payment() {
		$simulation = $this->handleOneySimulation();
		wp_send_json_success(
			array(
				'popup' => $this->get_simulate_oney_payment_popup($simulation)
			)
		);

		wp_die();
	}

	/**
	 * Show oney simulation details
	 *
	 * @param $oney_response
	 * @return string
	 */
	public function get_simulate_oney_payment_popup($oney_response) {
		$cgv = $this->popupfooter();

		if($oney_response) {
			$popup_content = is_array($oney_response) ? PayplugOney::simulationPopupContent($this, $oney_response['x3_with_fees'], $oney_response['x4_with_fees']) : $oney_response;

			$popup = <<<HTML
                $popup_content
				$cgv
HTML;

			return $popup;
		}
		return "<div class='oney-content oney-cgv-content'></div>";
	}

	/**
	 * @return string
	 */
	private function popupfooter()
	{
		$f = function($fn) { return $fn; };

		$footer = <<<HTML
 			<div class='oney-cgv-content oney-cgv-footer'>
 				{$f( sprintf(
					esc_html__("Offre de financement avec apport obligatoire, réservée aux particuliers et valable pour tout achat de %s€ à %s€. Sous réserve d'acceptation par Oney Bank. Vous disposez d'un délai de 14 jours pour renoncer à votre crédit. Oney Bank - SA au capital de 51 286 585€ - 34 Avenue de Flandre 59170 Croix - 546 380 197 RCS Lille Métropole - n° Orias 07 023 261 %s Correspondance : CS 60 006 - 59895 Lille Cedex - %s"),
					 $this->get_min_amount(),
					 $this->get_max_amount(),
					__("<a href='https://www.orias.fr' target='_empty' > www.orias.fr </a>"),
					__("<a href=\"https://www.oney.fr\" target=\"_empty\" > www.oney.fr </a>")
					))}
			</div>
HTML;

		return $footer;

	}



}
