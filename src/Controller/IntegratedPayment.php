<?php

namespace Payplug\PayplugWoocommerce\Controller;

class IntegratedPayment
{

	static public function template_form(){

		$logo = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/logo-payplug.png';
		$lock = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/lock.svg';
		$privacy_policy_url = __("payplug_integrated_payment_privacy_policy_url", "payplug");
		$f = function ($fn) {
			return $fn;
		};

		if (isset(get_option( 'woocommerce_payplug_settings', [] )['oneclick']) && get_option( 'woocommerce_payplug_settings', [] )['oneclick'] === "yes") {
			$save_card = true;
		} else {
			$save_card = false;
		}

		if ($save_card) {
			$save_card_container = <<<HTML
				<div class="payplug IntegratedPayment_container -saveCard" data-e2e-name="saveCard">
					<label><input type="checkbox" name="savecard"><span></span>{$f(__('payplug_integrated_payment_oneClick', 'payplug'))}</label>
				</div>
			HTML;
		} else {
			$save_card_container = "";
		}

		return <<<HTML
			<form class="payplug IntegratedPayment -loaded">
				<div class="payplug IntegratedPayment_container -cardHolder cardholder-input-container" data-e2e-name="cardHolder"></div>
				<div class="payplug IntegratedPayment_error -cardHolder -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">[EXAMPLE] INVALID VALUE [EXAMPLE]</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">[EXAMPLE] EMPTY FIELD [EXAMPLE]</span>
				</div>
				<div class="payplug IntegratedPayment_container -scheme">
					<div>{$f(__('payplug_integrated_payment_your_card', 'payplug'))}</div>
					<div class="payplug IntegratedPayment_schemes">
						<label class="payplug IntegratedPayment_scheme -visa"><input type="radio" name="schemeOptions" value="visa"/><span></span></label>
						<label class="payplug IntegratedPayment_scheme -mastercard"><input type="radio" name="schemeOptions" value="mastercard" /><span></span></label>
						<label class="payplug IntegratedPayment_scheme -cb"><input type="radio" name="schemeOptions" value="cb" /><span></span></label>
					</div>
				</div>
				<div class="payplug IntegratedPayment_container -pan pan-input-container" data-e2e-name="pan"></div>
				<div class="payplug IntegratedPayment_error -pan -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">[EXAMPLE] INVALID VALUE [EXAMPLE]</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">[EXAMPLE] EMPTY FIELD [EXAMPLE]</span>
				</div>
				<div class="payplug IntegratedPayment_container -exp exp-input-container" data-e2e-name="expiration"></div>
				<div class="payplug IntegratedPayment_container -cvv cvv-input-container" data-e2e-name="cvv"></div>
				<div class="payplug IntegratedPayment_error -exp -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">[EXAMPLE] INVALID VALUE [EXAMPLE]</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">[EXAMPLE] EMPTY FIELD [EXAMPLE]</span>
				</div>
				<div class="payplug IntegratedPayment_error -cvv -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">[EXAMPLE] INVALID VALUE [EXAMPLE]</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">[EXAMPLE] EMPTY FIELD [EXAMPLE]</span>
				</div>
				$save_card_container
				<div class="payplug IntegratedPayment_container -transaction">
					<img class="lock-icon" src="$lock" /><label class="transaction-label">{$f(__('payplug_integrated_payment_transaction_secure', 'payplug'))}</label><img class="payplug-logo" src="$logo" />
				</div>
				<div class="payplug IntegratedPayment_container -privacy-policy">
					<a href="$privacy_policy_url" target="_blank">{$f(__('payplug_integrated_payment_privacy_policy', 'payplug'))}</a>
				</div>

				<!-- TODO:: Remove this once form is validated! -->
				<button onClick="javascript:PayplugIntegrated.showErrors();return false;">Show/Hide Errors</button>
			</form>
HTML;
	}

}
