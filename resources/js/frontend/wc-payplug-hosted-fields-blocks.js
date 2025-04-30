import { getSetting } from '@woocommerce/settings';
import React, { useEffect } from 'react';
const settings = getSetting( 'payplug_data', {} );

var style = {
	"input": {
		"font-size": "1em",
		"background-color": "transparent",
	},
	"::placeholder": {
		"font-size": "1em",
		"color": "#777",
		"font-style": "italic"
	},
	":invalid": {
		"color": "#FF0000",
		"font-size": "1em"
	}
};

var HostedFields = {
	hfields: dalenys.hostedFields({
		// API Keys
		key: {
			id: "970c4f7c-c62e-40d2-8084-b61781326c81",
			value: "lr3*{F/4?nLnTq.t"
		},
		// Manages each hosted-field container
		fields: {
			'card': {
				id: 'card-container',
				placeholder: settings?.payplug_integrated_payment_card_number,
				enableAutospacing: true,
				style: style,
				onInput: function (event) {
					if (typeof event['cardType'] !== "undefined") {
						jQuery("input[type='radio'][name='schemeOptions'][value='" + event['cardType'] + "']").attr("checked", true);
					}

					HostedFields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-pan .invalidField"))
				}
			},
			'expiry': {
				id: 'expiry-container',
				placeholder: settings?.payplug_integrated_payment_expiration_date,
				style: style,
				onInput: function (event) {
					HostedFields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-exp .invalidField"))
				}
			},
			'cryptogram': {
				id: 'cvv-container',
				placeholder: settings?.payplug_integrated_payment_cvv,
				style: style,
				onInput: function (event) {
					HostedFields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-cvv .invalidField"))
				}
			}
		}
	}),
	handleInvalidFieldErrors: function (event, $element) {
		if (event["type"] === "invalid") {
			$element.removeClass("-hide");
			$element.parent().removeClass("-hide");
		}

		if (event["type"] === "valid" || event["type"] === "empty") {
			$element.addClass("-hide");
			$element.parent().addClass("-hide");
		}
	},
	showInputErrorBorder: function (input, error_targer) {
		jQuery(input).addClass("hosted-fields-invalid-state");
		jQuery(error_targer).removeClass("-hide");
		jQuery(error_targer).parent().removeClass("-hide");
	},
	hideInputErrorBorder: function (input, error_targer) {
		jQuery(input).removeClass("hosted-fields-invalid-state");
		jQuery(error_targer).addClass("-hide");
		jQuery(error_targer).parent().addClass("-hide");
	},
	validateInput: function (input, error_targer) {
		// Check if the input value has more than 4 letters
		if (jQuery(input).val().length < 5 && jQuery(input).val().length > 0) {
			HostedFields.showInputErrorBorder(input, error_targer);
			return false;

		} else {
			HostedFields.hideInputErrorBorder(input, error_targer);
			return true;
		}
	},
	submitValidation: function (input, error_targer) {

		// Check if the input value has more than 4 letters
		if (jQuery(input).val().length < 1) {
			HostedFields.showInputErrorBorder(input, error_targer);
			return false;

		} else {
			HostedFields.hideInputErrorBorder(input, error_targer);
			return true;
		}
	}
};

const IntegratedPayment = ({props: props,}) => {

	const { eventRegistration } = props;
	const { onCheckoutValidation, onPaymentSetup } = eventRegistration;

	//on init
	useEffect(() => {
		HostedFields.hfields.load();
		jQuery("[name=hosted-fields-cardHolder]").on("input", function (event) {
			HostedFields.validateInput(event.target, jQuery(".IntegratedPayment_error.-cardHolder .invalidField"));
		});

	}, []);

	useEffect(() => {
		const onValidation = async () => {

			try {
				const response = await tokenizeHandler();

				if (response.execCode === "0000") {
					return true; // Validation successful
				} else {
					return {
						errorMessage: settings?.payplug_invalid_form
					};
				}
			} catch (error) {
				return {
					errorMessage: settings?.payplug_invalid_form
				};
			}
		};

		async function tokenizeHandler() {
			return new Promise((resolve, reject) => {
				HostedFields.hfields.createToken(function (result) {
					if (HostedFields.submitValidation(jQuery("[name=hosted-fields-cardHolder]"), jQuery(".IntegratedPayment_error.-cardHolder .invalidField")) && result.execCode == "0000") {
						document.getElementById("hf-token").value = result.hfToken;
						resolve(result);

					} else {
						reject(new Error("Tokenization failed"));
					}
				});
			});
		}


		const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
		return () => { unsubscribeAfterProcessing(); };
	}, [onCheckoutValidation]);
	return (
		<>
			<div className="payplug IntegratedPayment_container -cardHolder cardHolder-input-container"
				 data-e2e-name="cardHolder">
				<p className="cardHolder-container">
			            <span className="input-container" id="cardHolder-container">
			            	<input type="text" name="hosted-fields-cardHolder" id="hosted-fields-cardHolder"
								   className="hosted-fields hosted-fields-input-state"
								   placeholder={settings?.payplug_integrated_payment_cardholder}/>
						</span>
				</p>
			</div>
			<div className="payplug IntegratedPayment_error -cardHolder -hide">
				<span className="-hide invalidField"
					  data-e2e-error="invalidField">{settings?.payplug_integrated_payment_cardHolder_error}</span>
				<span className="-hide emptyField"
					  data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
			</div>
			<div className="payplug IntegratedPayment_container -scheme">
				<div>{settings?.payplug_integrated_payment_your_card}</div>
				<div className="payplug IntegratedPayment_schemes">
					<label className="payplug IntegratedPayment_scheme -cb">
						<input type="radio" name="schemeOptions" value="cb"/><span></span></label>
					<label className="payplug IntegratedPayment_scheme -visa">
						<input type="radio" name="schemeOptions" value="visa"/><span></span></label>
					<label className="payplug IntegratedPayment_scheme -mastercard">
						<input type="radio" name="schemeOptions" value="mastercard"/><span></span></label>
				</div>
			</div>

			<div className="payplug IntegratedPayment_container -pan pan-input-container" data-e2e-name="pan">
				<span className="input-container" id="card-container"></span>
			</div>
			<div className="payplug IntegratedPayment_error -pan -hide">
				<span className="-hide invalidField"
					  data-e2e-error="invalidField">{settings?.payplug_integrated_payment_pan_error}</span>
				<span className="-hide emptyField"
					  data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
			</div>
			<div className="payplug IntegratedPayment_container -exp exp-input-container" data-e2e-name="expiration">
				<span className="input-container" id="expiry-container"></span>
			</div>
			<div className="payplug IntegratedPayment_container -cvv cvv-input-container" data-e2e-name="cvv">
				<span className="input-container" id="cvv-container"></span>
			</div>
			<div className="payplug IntegratedPayment_error -exp -hide">
				<span className="-hide invalidField"
					  data-e2e-error="invalidField">{settings?.payplug_integrated_payment_exp_error}</span>
				<span className="-hide emptyField"
					  data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
			</div>
			<div className="payplug IntegratedPayment_error -cvv -hide">
				<span className="-hide invalidField"
					  data-e2e-error="invalidField">{settings?.payplug_integrated_payment_cvv_error}</span>
				<span className="-hide emptyField"
					  data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
			</div>
			<div className="payplug IntegratedPayment_error -payment">
				<span>{settings?.payplug_integrated_payment_error}</span>
			</div>

			<div className="payplug IntegratedPayment_container -transaction">
				<img className="lock-icon" src={settings?.lock}/>
				<label
					className="transaction-label">{settings?.payplug_integrated_payment_transaction_secure}</label>
				<img className="payplug-logo" src={settings?.logo}/>
			</div>
			<div className="payplug IntegratedPayment_container -privacy-policy">
				<a href={settings?.payplug_integrated_payment_privacy_policy_url}
				   target="_blank">{settings?.payplug_integrated_payment_privacy_policy}</a>
			</div>
			<input type="hidden" name="hf-token" id="hf-token"/>
		</>
	)
}

export default IntegratedPayment;
