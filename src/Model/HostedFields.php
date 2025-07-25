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

	const OPERATION_TYPE_PAYMENT = 'payment';

	private $account_key;

	private $api_key_id;

	private $identifier;

	private $api_key;

	public function __construct($account_key, $api_key_id, $identifier, $api_key) {
		$this->set_api_key_id($api_key_id);
		$this->set_api_key($api_key);
		$this->set_identifier($identifier);
		$this->set_account_key($account_key);
	}

	/**
	 * @param $payment_data
	 * @param $order
	 * @param $order_id
	 * @param $hf_token
	 * @param $amount
	 *
	 * @return mixed
	 * @throws \Exception
	 *
	 * Create a payment data array for the Hosted Fields integration.
	 */
	public function populateCreatePayment(&$payment_data, $order, $order_id, $hf_token, $amount) {

		if (empty($hf_token)) {
			throw new \Exception(__('Hosted fields token is empty.', 'payplug'));
		}

		$payment_data['method'] = self::OPERATION_TYPE_PAYMENT;
		$payment_data['params'] = [
			"IDENTIFIER" => $this->get_identifier(),
			"OPERATIONTYPE" => self::OPERATION_TYPE_PAYMENT,
			"AMOUNT" => "$amount",
			"VERSION" => self::API_Version,
			"CLIENTIDENT" => $order->get_billing_first_name() . $order->get_billing_last_name(),
			"CLIENTEMAIL" => $order->get_billing_email(),
			"CLIENTREFERRER" => $this->limit_length(esc_url_raw(home_url()), 500),
			"CLIENTUSERAGENT" => $order->get_customer_user_agent(),
			"CLIENTIP" => $order->get_customer_ip_address(),
			"ORDERID" => $order_id,
			"DESCRIPTION" => !empty($order->get_customer_order_notes()) ? $order->get_customer_order_notes() : "N.a.",
			"CREATEALIAS" => "yes",
			"APIKEYID" => $this->get_api_key_id(),
			"HFTOKEN" => $hf_token,
		];

		$this->handle_hostedfield_address($payment_data['params'], $order, 'billing');
		$this->handle_hostedfield_address($payment_data['params'], $order, 'shipto');

		//FIXME HF::updating the amount for: $amount response 5002 error
		$payment_data['params']["AMOUNT"]="1000";

		$payment_data["params"]["HASH"] = $this->buildHashContent( $payment_data['params']);
		return $payment_data;
	}


	/**
	 * @description Prepares the data for a refund transaction.
	 * @param $payment_id
	 * @param $amount
	 * @param $order_id
	 * @param $order
	 * @return array
	 */
	public function populateRefundTransaction($payment_id, $amount, $order_id, $order) {
		$data = Array(
			"method" => 'refund',
			"params" => array(
				'IDENTIFIER' => $this->get_identifier(),
				'OPERATIONTYPE' => 'refund',
				'AMOUNT' => (string) $amount,
				'ORDERID' =>$order_id,
				'DESCRIPTION' =>!empty($order->get_customer_order_notes()) ? $order->get_customer_order_notes() : "N.a.",
				'TRANSACTIONID' => $payment_id,
				'VERSION' => self::API_Version,
			)
		);
		$data["params"]["HASH"] = $this->buildHashContent( $data['params'], true );
		return $data;
	}

	/**
	 * @param $data
	 * @param $order
	 * @param $type
	 *
	 * @return void
	 *
	 * Handles the address data for hosted fields.
	 */
	public function handle_hostedfield_address(&$data, $order, $type)
	{
		$prefix = strtoupper($type);
        $type = $type == 'shipto' ? 'shipping' : $type;
		$data["{$prefix}ADDRESS"] = $order->{"get_{$type}_address_1"}() . ' ' . $order->{"get_{$type}_address_2"}();
		$data["{$prefix}COUNTRY"] = $order->{"get_{$type}_country"}();
		$data["{$prefix}POSTALCODE"] = $order->{"get_{$type}_postcode"}();
		return $data;
	}


	/**
	 * @param $payment_id
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 *  * Prepares the data for retrieving a transaction by its ID.
	 */
	public function populateGetTransaction($payment_id){

		if(empty($payment_id)){
			throw new \InvalidArgumentException('Parameters array cannot be empty');
		}

		$data = Array(
			"method" => 'getTransactions',
			"params" => array(
				'IDENTIFIER' => $this->get_identifier(),
				'OPERATIONTYPE' => 'getTransaction',
				'TRANSACTIONID' => $payment_id,
				'VERSION' => self::API_Version,
			)
		);
		$data["params"]["HASH"] = $this->buildHashContent( $data['params'], true );

		return $data;

	}

	/**
	 * @description Builds a hash content string
	 * by concatenating sorted parameters with a secret key.
	 *
	 * @param array $params
	 * @param bool $getTransaction
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function buildHashContent($params, $getTransaction = false) {

		// Validate inputs
		if (empty($params) || !is_array($params)) {
			throw new \InvalidArgumentException('Parameters array cannot be empty');
		}
		if (empty($this->get_api_key())) {
			throw new \InvalidArgumentException('Secret key cannot be empty');
		}
		if($getTransaction && empty($this->get_account_key()) ) {
			throw new \InvalidArgumentException('API Secret cannot be empty');
		}
		$key = $getTransaction ? $this->get_account_key() : $this->get_api_key();

		ksort($params);
		$string = '';
		foreach($params as $k => $v){
			$string .= $k  . "=" . $v . $key;
		}

		return hash('sha256', $key . $string);

	}

	/**
	 * @return mixed
	 */
	public function get_api_key_id() {
		return $this->api_key_id;
	}

	/**
	 * @return mixed
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * @return self
	 */
	public function get_account_key() {
		return $this->account_key;
	}

	/**
	 * @return mixed
	 */
	public function get_identifier() {
		return $this->identifier;
	}

	/**
	 * @param mixed $api_key_id
	 */
	private function set_api_key_id( $api_key_id ) {
		$this->api_key_id = $api_key_id;
	}

	/**
	 * @param mixed $api_key
	 */
	private function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * @param mixed $account_key
	 */
	private function set_account_key( $account_key ) {
		$this->account_key = $account_key;
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
