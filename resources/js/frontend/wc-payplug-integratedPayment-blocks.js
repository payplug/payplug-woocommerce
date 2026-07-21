import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useRef } from 'react';
const settings = getSetting( 'payplug_data', {} );
import {getPayment, check_payment } from "./helper/wc-payplug-requests";
let saved_card = false;

const IntegratedPayment = ({props: props,}) => {
	const { eventRegistration, emitResponse } = props;
	saved_card = props.shouldSavePayment;

	const { onCheckoutValidation, onPaymentSetup, onCheckoutSuccess } = eventRegistration;

	// A plain object would be reset on every re-render, but it's read from async
	// callbacks/DOM handlers registered in effects below that can fire well after a
	// re-render has happened (same reason the Apple Pay blocks component uses useRef).
	const ObjIntegratedPayment = useRef({
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
	});

	useEffect(() => {
		document.body.setAttribute('payplug-domain', settings?.secureDomain);
		ObjIntegratedPayment.current.api = new Payplug.IntegratedPayment( settings?.mode == 1 ? false : true );
		ObjIntegratedPayment.current.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX)
		ObjIntegratedPayment.current.form.cardHolder = ObjIntegratedPayment.current.api.cardHolder(document.querySelector('.cardHolder-input-container'), {default: ObjIntegratedPayment.current.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cardholder } );
		ObjIntegratedPayment.current.form.pan = ObjIntegratedPayment.current.api.cardNumber(document.querySelector('.pan-input-container'), {default: ObjIntegratedPayment.current.inputStyle.default, placeholder: settings?.payplug_integrated_payment_card_number } );
		ObjIntegratedPayment.current.form.cvv = ObjIntegratedPayment.current.api.cvv(document.querySelector('.cvv-input-container'), {default: ObjIntegratedPayment.current.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cvv } );
		ObjIntegratedPayment.current.form.exp = ObjIntegratedPayment.current.api.expiration(document.querySelector('.exp-input-container'), {default: ObjIntegratedPayment.current.inputStyle.default, placeholder: settings?.payplug_integrated_payment_expiration_date } );
		ObjIntegratedPayment.current.scheme = ObjIntegratedPayment.current.api.getSupportedSchemes();
		fieldValidation();

	}, []);

	useEffect(() => {
		const onValidation = async () => {
			ObjIntegratedPayment.current.api.validateForm();

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
					await ObjIntegratedPayment.current.api.onValidateForm(({isFormValid}) => {
						resolve(isFormValid);
					});
				})
			}
		}
		const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
		return () => { unsubscribeAfterProcessing(); };
	}, [onCheckoutValidation]);

	// Waits for the SDK to report the card capture as completed, then verifies it server-side
	// and resolves the final response WooCommerce Blocks expects (onPaymentSetup/onCheckoutSuccess
	// share this shape). Used once the payment_id/return_url to pay against are already known,
	// regardless of whether that happened on order-pay (in onPaymentSetup) or regular checkout
	// (in onCheckoutSuccess, see below).
	function onCompleteEvent(){
		return new Promise((resolve, reject) => {
			ObjIntegratedPayment.current.api.onCompleted(function (event) {
				check_payment({'payment_id' : event.token}).then((res) => {
					if (res && res.success === false) {
						resolve({
							type: 'error',
							message: settings?.payplug_integrated_payment_error
						});
						return;
					}
					resolve({
						type: 'success',
					});
				}).catch(() => {
					resolve({
						type: 'error',
						message: settings?.payplug_integrated_payment_error
					});
				});
			});
		});
	}

	useEffect(() => {
		const handlePaymentProcessing = async () => {
			// Order-pay always has a real, existing order from the start (the one being
			// repaid), so the payment intent can be created and paid right away.
			if (settings.is_order_pay) {
				try {
					const response = await getPayment(props, settings, settings.order_pay_id);
					ObjIntegratedPayment.current.paymentId = response.data.payment_id;
					ObjIntegratedPayment.current.return_url = response.data.redirect;

					const onCompleted = onCompleteEvent();
					await ObjIntegratedPayment.current.api.pay(ObjIntegratedPayment.current.paymentId, Payplug.Scheme.AUTO, {save_card: saved_card} );

					return await onCompleted;
				} catch (error) {
					return {
						type: 'error',
						message: error.message
					}
				}
			}

			// Regular checkout: WooCommerce doesn't create the real order until the place-order
			// submission that follows this step (order creation is deferred until then since
			// WooCommerce 10.8). The payment intent can only be created once that real order
			// exists, so that happens server-side in process_payment() and comes back here via
			// payment_details on the onCheckoutSuccess event below - nothing more to do yet.
			return {
				type: 'success'
			};
		}
		const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onPaymentSetup
	]);

	useEffect(() => {
		const handleCheckoutSuccess = async ({ processingResponse }) => {
			// Order-pay's payment was already created and paid synchronously in onPaymentSetup
			// above, since a real order already existed there - nothing left to do here.
			if (settings.is_order_pay) {
				return {
					type: "success",
					redirectUrl: ObjIntegratedPayment.current.return_url
				}
			}

			const paymentDetails = processingResponse?.paymentDetails || {};
			ObjIntegratedPayment.current.paymentId = paymentDetails.payment_id;
			ObjIntegratedPayment.current.return_url = paymentDetails.redirect;

			try {
				const onCompleted = onCompleteEvent();
				await ObjIntegratedPayment.current.api.pay(ObjIntegratedPayment.current.paymentId, Payplug.Scheme.AUTO, {save_card: saved_card} );

				const result = await onCompleted;
				return result.type === 'success'
					? { ...result, redirectUrl: ObjIntegratedPayment.current.return_url }
					: result;
			} catch (error) {
				return {
					type: 'error',
					message: error.message
				}
			}
		}
		const unsubscribeAfterProcessing = onCheckoutSuccess(handleCheckoutSuccess);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onCheckoutSuccess
	]);


	const fieldValidation = () => {
		jQuery.each(ObjIntegratedPayment.current.form, function (key, field) {
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
					ObjIntegratedPayment.current.fieldsValid[key] = true;
					ObjIntegratedPayment.current.fieldsEmpty[key] = false;
				}
			});
		});
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
						<label className="payplug IntegratedPayment_scheme -cb">
							<input type="radio" name="schemeOptions" value="cb"/><span></span></label>
						<label className="payplug IntegratedPayment_scheme -visa">
							<input type="radio" name="schemeOptions" value="visa" /><span></span></label>
						<label className="payplug IntegratedPayment_scheme -mastercard">
							<input type="radio" name="schemeOptions" value="mastercard"/><span></span></label>
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

export default IntegratedPayment;
