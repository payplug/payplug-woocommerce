import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useRef } from 'react';
import {
	getPayment,
	check_payment,
	usePaymentFailHandler,
	usePaymentCompleteHandler
} from "./helper/wc-payplug-requests";
const settings = getSetting( 'payplug_data', {} );

export const IntegratedPayment = ({props: props,}) => {

	const shouldSavePayment = false;
	const { eventRegistration, emitResponse } = props;
	const { onCheckoutValidation, onPaymentSetup, onCheckoutFail, onCheckoutSuccess } = eventRegistration;

	useEffect(() => {
		ObjIntegratedPayment.api = new Payplug.IntegratedPayment(false);
		ObjIntegratedPayment.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX)
		ObjIntegratedPayment.form.cardHolder = ObjIntegratedPayment.api.cardHolder(document.querySelector('.cardHolder-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cardholder } );
		ObjIntegratedPayment.form.pan = ObjIntegratedPayment.api.cardNumber(document.querySelector('.pan-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_card_number } );
		ObjIntegratedPayment.form.cvv = ObjIntegratedPayment.api.cvv(document.querySelector('.cvv-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cvv } );
		ObjIntegratedPayment.form.exp = ObjIntegratedPayment.api.expiration(document.querySelector('.exp-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_expiration_date } );
		ObjIntegratedPayment.scheme = ObjIntegratedPayment.api.getSupportedSchemes();
		fieldValidation();

	}, []);

	useEffect(() => {
		const onValidation = async () => {
			ObjIntegratedPayment.api.validateForm();

			let isValid = false;
			await validateForm().then( (response) => {
				isValid = response;
			});

			if(!isValid){
				return {
					errorMessage: settings?.payplug_invalid_form
				}
			}else{
				return isValid;
			}

			function validateForm(){
				return new Promise(async (resolve, reject) => {
					await ObjIntegratedPayment.api.onValidateForm(({isFormValid}) => {
						resolve(isFormValid);
					});
				})
			}
		}
		const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
		return () => { unsubscribeAfterProcessing(); };
	}, [onCheckoutValidation]);


	useEffect(() => {
		const handlePaymentProcessing = async () => {
			let data = {};
			await getPayment(props).then( async (response) => {
				ObjIntegratedPayment.paymentId = response.payment_id;
				data = {'payment_id': response.payment_id};
				ObjIntegratedPayment.return_url = response.redirect;
				try {
					await ObjIntegratedPayment.api.pay(ObjIntegratedPayment.paymentId, Payplug.Scheme.AUTO, {save_card: false});

				}catch(error) {
					return {
						type: 'error',
						message: error.message
					}
				}

			});

			let result = {};
			await CompleteProcessingPayment().then( (res) => {
				if(res.success){
					result = {
						type: 'success',
						meta: {}
					}
				}else{
					result = {
						type: 'error',
						message: res.data.message,
					};
				}
			});

			return result;

			function CompleteProcessingPayment(){
				return new Promise(async (resolve, reject) => {
					await ObjIntegratedPayment.api.onCompleted( function (event) {
						const data = {'payment_id' : event.token};
						check_payment(data).then( (res) => {
							resolve(res);
						});
					});
				})
			}

		}
		const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onPaymentSetup,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS
	]);

	usePaymentFailHandler(onCheckoutFail, emitResponse);

	usePaymentCompleteHandler(onCheckoutSuccess, emitResponse, shouldSavePayment)

	const fieldValidation = () => {
		jQuery.each(ObjIntegratedPayment.form, function (key, field) {
			field.onChange(function(err) {
				if (err.error) {
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).classList.remove("-hide");
					document.querySelector('.'+key+'-input-container').classList.add("-invalid");

					if (err.error.name === "FIELD_EMPTY") {
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".emptyField").classList.remove("-hide");
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".invalidField").classList.add("-hide");
					} else {
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".invalidField").classList.remove("-hide");
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".emptyField").classList.add("-hide");
					}
				} else {
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).classList.add("-hide");
					document.querySelector('.'+key+'-input-container').classList.remove("-invalid");
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".invalidField").classList.add("-hide");
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".emptyField").classList.add("-hide");
					ObjIntegratedPayment.fieldsValid[key] = true;
					ObjIntegratedPayment.fieldsEmpty[key] = false;
				}
			});
		});
	}

	var ObjIntegratedPayment = {
		cartId: null,
		paymentId: null,
		paymentOptionId: null,
		form: {},
		checkoutForm: null,
		api: null,
		integratedPayment: null,
		token: null,
		notValid: true,
		fieldsValid: {
			cardHolder: false,
			pan: false,
			cvv: false,
			exp: false,
		},
		fieldsEmpty: {
			cardHolder: true,
			pan: true,
			cvv: true,
			exp: true,
		},
		inputStyle: {
			default: {
				color: '#2B343D',
				fontFamily: 'Poppins, sans-serif',
				fontSize: '14px',
				textAlign: 'left',
				'::placeholder': {
					color: '#969a9f',
				},
				':focus': {
					color: '#2B343D',
				}
			},
			invalid: {
				color: '#E91932'
			}
		},
		save_card: false,
		scheme: null,
		query: null,
		submit: null,
		order_review: false,
		return_url: null
	}

	return (
		<>
			<div id="payplug-integrated-payment" className="payplug IntegratedPayment -loaded">
				<div className="payplug IntegratedPayment_container -cardHolder cardHolder-input-container" data-e2e-name="cardHolder"></div>
				<div className="payplug IntegratedPayment_error -cardHolder -hide">
					<span className="-hide invalidField" data-e2e-error="invalidField">{ settings?.payplug_integrated_payment_cardHolder_error }</span>
					<span className="-hide emptyField" data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
				</div>
				<div className="payplug IntegratedPayment_container -scheme">
					<div>{settings?.payplug_integrated_payment_your_card}</div>
					<div className="payplug IntegratedPayment_schemes">
						<label className="payplug IntegratedPayment_scheme -visa">
							<input type="radio" name="schemeOptions" value="visa" /><span></span></label>
						<label className="payplug IntegratedPayment_scheme -mastercard">
							<input type="radio" name="schemeOptions" value="mastercard"/><span></span></label>
						<label className="payplug IntegratedPayment_scheme -cb">
							<input type="radio" name="schemeOptions" value="cb"/><span></span></label>
					</div>
				</div>
				<div className="payplug IntegratedPayment_container -pan pan-input-container" data-e2e-name="pan"></div>
				<div className="payplug IntegratedPayment_error -pan -hide">
					<span className="-hide invalidField" data-e2e-error="invalidField">{settings?.payplug_integrated_payment_pan_error}</span>
					<span className="-hide emptyField" data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
				</div>
				<div className="payplug IntegratedPayment_container -exp exp-input-container" data-e2e-name="expiration"></div>
				<div className="payplug IntegratedPayment_container -cvv cvv-input-container" data-e2e-name="cvv"></div>
				<div className="payplug IntegratedPayment_error -exp -hide">
					<span className="-hide invalidField" data-e2e-error="invalidField">{settings?.payplug_integrated_payment_exp_error}</span>
					<span className="-hide emptyField" data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
				</div>
				<div className="payplug IntegratedPayment_error -cvv -hide">
					<span className="-hide invalidField" data-e2e-error="invalidField">{settings?.payplug_integrated_payment_cvv_error}</span>
					<span className="-hide emptyField" data-e2e-error="paymentError">{settings?.payplug_integrated_payment_empty}</span>
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
					<a href={settings?.payplug_integrated_payment_privacy_policy_url} target="_blank">{settings?.payplug_integrated_payment_privacy_policy}</a>
				</div>
			</div>
		</>
	)
}
