<?php

namespace Payplug\PayplugWoocommerce\Controller;

use Payplug\Payplug;
use Payplug\Authentication;
use Payplug\PayplugWoocommerce\Gateway\PayplugGateway;
use Payplug\PayplugWoocommerce\PayplugWoocommerceHelper;
use Payplug\Exception\ForbiddenException;

class IntegratedPayment
{
	protected $options;

	public function __construct($options)
	{
		$this->options = $options;
	}

	static public function template_form($oneClick){

		$logo = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/logo-payplug.png';
		$lock = PAYPLUG_GATEWAY_PLUGIN_URL . '/assets/images/integrated/lock.svg';
		$privacy_policy_url = __("payplug_integrated_payment_privacy_policy_url", "payplug");
		$f = function ($fn) {
			return $fn;
		};

		if($oneClick) {
			$saved = <<<HTML
					<div class="payplug IntegratedPayment_container -saveCard" data-e2e-name="saveCard">
						<label><input type="checkbox" name="savecard"><span></span>{$f(__('payplug_integrated_payment_oneClick', 'payplug'))}</label>
					</div>
HTML;
		} else {
			$saved = "";
		}


		return <<<HTML
			<form class="payplug IntegratedPayment -loaded">
				<div class="payplug IntegratedPayment_container -cardHolder cardHolder-input-container" data-e2e-name="cardHolder"></div>
				<div class="payplug IntegratedPayment_error -cardHolder -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_cardHolder_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
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
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_pan_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>
				<div class="payplug IntegratedPayment_container -exp exp-input-container" data-e2e-name="expiration"></div>
				<div class="payplug IntegratedPayment_container -cvv cvv-input-container" data-e2e-name="cvv"></div>
				<div class="payplug IntegratedPayment_error -exp -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_exp_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>
				<div class="payplug IntegratedPayment_error -cvv -hide">
					<span class="-hide invalidField" data-e2e-error="invalidField">{$f(__('payplug_integrated_payment_cvv_error', 'payplug'))}</span>
					<span class="-hide emptyField" data-e2e-error="paymentError">{$f(__('payplug_integrated_payment_empty', 'payplug'))}</span>
				</div>

				{$saved}

				 <div class="payplug IntegratedPayment_error -payment">
					<span>{$f(__('payplug_integrated_payment_error', 'payplug'))}</span>
    			</div>

				<div class="payplug IntegratedPayment_container -transaction">
					<img class="lock-icon" src="$lock" /><label class="transaction-label">{$f(__('payplug_integrated_payment_transaction_secure', 'payplug'))}</label><img class="payplug-logo" src="$logo" />
				</div>
				<div class="payplug IntegratedPayment_container -privacy-policy">
					<a href="$privacy_policy_url" target="_blank">{$f(__('payplug_integrated_payment_privacy_policy', 'payplug'))}</a>
				</div>
			</form>
HTML;
	}

	public function enable_ip(){

		//save into transaction the IP permissions
		$this->options['payment_method'] = "integrated";
		$this->options['update_gateway'] = true;
		$this->options['can_use_integrated_payments'] = true;
		update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $this->options) );
	}

	//refered to https://payplug-prod.atlassian.net/browse/WOOC-772
	public function disable_ip(){
		if ($this->options['payment_method'] == "integrated") {
			$this->options["payment_method"] = "redirect";
		}
		$this->options['update_gateway'] = false;
		$this->options['can_use_integrated_payments'] = false;

		//save into transaction the IP permissions
		update_option( 'woocommerce_payplug_settings', apply_filters('woocommerce_settings_api_sanitized_fields_payplug', $this->options) );
		return false;
	}

	public function ip_permissions(){

		$options          = get_option('woocommerce_payplug_settings', []);

		try {
			if (!empty(PayplugWoocommerceHelper::get_live_key())) {
				$permissions = Authentication::getAccount(new Payplug(PayplugWoocommerceHelper::get_live_key()));
				PayplugWoocommerceHelper::set_transient_data($permissions, $options);
			} else {
				return false;
			}

		} catch (\Payplug\Exception\UnauthorizedException $e) {
		} catch (\Payplug\Exception\ConfigurationNotSetException $e) {
		} catch( \Payplug\Exception\ForbiddenException $e){
		} catch (\Payplug\Exception\ForbiddenException $e){return array();}


		if( empty($permissions["httpResponse"]["permissions"]['can_use_integrated_payments']) || !$permissions["httpResponse"]["permissions"]['can_use_integrated_payments'] ){
			return false;
		}

		if( !empty($permissions["httpResponse"]["permissions"]['can_use_integrated_payments']) && $permissions["httpResponse"]["permissions"]['can_use_integrated_payments'] ){
			return true;
		}

		return false;
	}

	public function already_updated(){

		if( empty($this->options['update_gateway']) || (!empty($this->options['update_gateway']) && !$this->options['update_gateway'] ) ){
			return false;
		}

		return true;
	}

}
