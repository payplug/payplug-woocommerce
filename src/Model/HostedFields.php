<?php

namespace Payplug\PayplugWoocommerce\Model;

/**
 * Class HostedFields
 *
 * This class represents the configuration for Payplug's Hosted Fields integration.
 * It encapsulates the API credentials and provides methods to access them.
 *
 * @package Payplug\PayplugWoocommerce\Model
 */

class HostedFields {

	const API_Version = 3.0;

	private $api_secret;

	private $api_key;

	private $identifier;

	private $api_key_secret;

	public function __construct($api_secret, $api_key, $identifier, $api_key_secret) {
		$this->set_api_key($api_key);
		$this->set_api_key_secret($api_key_secret);
		$this->set_api_secret($api_secret);
		$this->set_identifier($identifier);
	}


	public function populateCreatePayment(&$payment_data, $order, $order_id, $hf_token, $amount) {

		if (empty($hf_token)) {
			throw new \Exception(__('Hosted fields token is empty.', 'payplug'));
		}

		$payment_data['method'] = "payment";
		$payment_data['params'] = [
			"IDENTIFIER" => $this->get_identifier(),
			"OPERATIONTYPE" => "payment",
			"AMOUNT" => (string) $amount,
			"VERSION" => $this->get_api_version(),
			"CLIENTIDENT" => $order->get_billing_first_name() . $order->get_billing_last_name(),
			"CLIENTEMAIL" => $order->get_billing_email(),
			"CLIENTREFERRER" => $this->limit_length(esc_url_raw(home_url()), 500),
			"CLIENTUSERAGENT" => $order->get_customer_user_agent(),
			"CLIENTIP" => $order->get_customer_ip_address(),
			"ORDERID" => $order_id,
			"DESCRIPTION" => !empty($order->get_customer_order_notes()) ? $order->get_customer_order_notes() : "N.a.",
			"CREATEALIAS" => "yes",
			"APIKEYID" => $this->get_api_key(),
			"HFTOKEN" => $hf_token,
		];


		//FIXME HF::updating the amount for: $amount response 5002 error
		$payment_data['params']["AMOUNT"]="1000";
		$payment_data["params"]["HASH"] = $this->buildHashContent( $payment_data['params'] );

		return $payment_data;
	}

	/**
	 * @description Builds a hash content string
	 * by concatenating sorted parameters with a secret key.
	 *
	 * @param $params
	 * @param $secret
	 * @param $prefix
	 * @return string
	 */
	public function buildHashContent($params){

		// Validate inputs
		if (empty($params) || !is_array($params)) {
			throw new \InvalidArgumentException('Parameters array cannot be empty');
		}
		if (empty($this->get_api_key_secret())) {
			throw new \InvalidArgumentException('Secret key cannot be empty');
		}

		ksort($params);
		$string = '';
		foreach($params as $k => $v){
			$string .= $k  . "=" . $v . $this->get_api_key_secret();
		}

		return hash('sha256', $this->get_api_key_secret() . $string);

	}

	public function get_api_version() {
		return self::API_Version;
	}

	/**
	 * @return mixed
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * @return mixed
	 */
	public function get_api_key_secret() {
		return $this->api_key_secret;
	}

	/**
	 * @return self
	 */
	public function get_api_secret() {
		return $this->api_secret;
	}

	/**
	 * @return mixed
	 */
	public function get_identifier() {
		return $this->identifier;
	}

	/**
	 * @param mixed $api_key
	 */
	private function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * @param mixed $api_key_secret
	 */
	private function set_api_key_secret( $api_key_secret ) {
		$this->api_key_secret = $api_key_secret;
	}

	/**
	 * @param mixed $api_secret
	 */
	private function set_api_secret( $api_secret ) {
		$this->api_secret = $api_secret;
	}

	/**
	 * @param mixed $identifier
	 */
	private function set_identifier( $identifier ) {
		$this->identifier = $identifier;
	}

	static public function template_form(){

		$logo = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/logo-payplug.png';
		$lock = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/lock.svg';
		$privacy_policy_url = __("payplug_integrated_payment_privacy_policy_url", "payplug");
		$f = function ($fn) {
			return $fn;
		};

		return <<<HTML
			<form id="payment-form" class="payplug IntegratedPayment -loaded" onsubmit="event.preventDefault(); HostedFields.tokenizeHandler();">
				<div class="payplug IntegratedPayment_container -cardHolder cardHolder-input-container" data-e2e-name="cardHolder">
					<p>
			            <span class="input-container" id="cardHolder-container">
			            	<input type="text" name="hosted-fields-cardHolder" value="" id="hosted-fields-cardHolder" class="hosted-fields hosted-fields-input-state" placeholder="{$f(__('payplug_integrated_payment_cardholder', 'payplug'))}">
						</span>
			        </p>
				</div>
				<div class="payplug IntegratedPayment_error -cardHolder -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_cardHolder_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>
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

	/**
	 * Limit string length.
	 *
	 * @param string $value
	 * @param int $maxlength
	 *
	 * @return string
	 */
	public function limit_length($value, $maxlength = 100)
	{
		return (strlen($value) > $maxlength) ? substr($value, 0, $maxlength) : $value;
	}

}
