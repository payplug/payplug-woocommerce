<?php

namespace Payplug\PayplugWoocommerce\Controller;

class HostedFields {

	protected $options;

	public function __construct($options)
	{
		$this->options = $options;
	}

	static public function template_form(){

		$logo = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/logo-payplug.png';
		$lock = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/lock.svg';
		$privacy_policy_url = __("payplug_integrated_payment_privacy_policy_url", "payplug");
		$f = function ($fn) {
			return $fn;
		};

		return <<<HTML
			<form id="payment-form" class="payplug IntegratedPayment -loaded">
				<div class="payplug IntegratedPayment_container -scheme">
					<div>{$f(__('payplug_integrated_payment_your_card', 'payplug'))}</div>
					<div class="payplug IntegratedPayment_schemes">
						<label class="payplug IntegratedPayment_scheme -cb"><input type="radio" name="schemeOptions" value="cb" /><span></span></label>
						<label class="payplug IntegratedPayment_scheme -visa"><input type="radio" name="schemeOptions" value="visa"/><span></span></label>
						<label class="payplug IntegratedPayment_scheme -mastercard"><input type="radio" name="schemeOptions" value="mastercard" /><span></span></label>
					</div>
				</div>
				<div class="payplug IntegratedPayment_container -pan pan-input-container" data-e2e-name="pan">
					<p>
			            <span class="input-container" id="card-container"></span>
			        </p>
				</div>
				<div class="payplug IntegratedPayment_error -pan -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_pan_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>

				<div class="payplug IntegratedPayment_container -exp exp-input-container" data-e2e-name="expiration">
					<p>
			            <span class="input-container" id="expiry-container"></span>
			        </p>
				</div>
				<div class="payplug IntegratedPayment_container -cvv cvv-input-container" data-e2e-name="cvv">
					<p>
			            <span class="input-container" id="cvv-container"></span>
			        </p>
				</div>
				<div class="payplug IntegratedPayment_error -exp -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_exp_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>
				<div class="payplug IntegratedPayment_error -cvv -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_cvv_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>
				 <div class="payplug IntegratedPayment_error -payment"><span>{$f(__('payplug_integrated_payment_error', 'payplug'))}</span></div>
				<div class="payplug IntegratedPayment_container -transaction">
					<img class="lock-icon" src="$lock" /><label class="transaction-label">{$f(__('payplug_integrated_payment_transaction_secure', 'payplug'))}</label><img class="payplug-logo" src="$logo" />
				</div>
				<div class="payplug IntegratedPayment_container -privacy-policy">
					<a href="$privacy_policy_url" target="_blank">{$f(__('payplug_integrated_payment_privacy_policy', 'payplug'))}</a>
				</div>
			</form>

HTML;
	}

}
