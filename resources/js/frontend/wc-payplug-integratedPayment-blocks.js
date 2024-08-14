import { getSetting } from '@woocommerce/settings';
import { useEffect } from 'react';
import {getPayment, check_payment} from "./helper/wc-payplug-requests";
const settings = getSetting( 'payplug_data', {} );

export const IntegratedPayment = (
	{settings: settings, props: props }
) => {

	const { eventRegistration } = props;
	const { onCheckoutValidation, onPaymentSetup } = eventRegistration;

	useEffect(() => {

		// Create an instance of Integrated Payments
		ObjIntegratedPayment.api = new Payplug.IntegratedPayment(false);
		ObjIntegratedPayment.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX)

		// Add each payments fields
		ObjIntegratedPayment.form.cardHolder = ObjIntegratedPayment.api.cardHolder(document.querySelector('.cardHolder-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cardholder } );
		ObjIntegratedPayment.form.pan = ObjIntegratedPayment.api.cardNumber(document.querySelector('.pan-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_card_number } );
		ObjIntegratedPayment.form.cvv = ObjIntegratedPayment.api.cvv(document.querySelector('.cvv-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cvv } );

		// With one field for expiration date
		ObjIntegratedPayment.form.exp = ObjIntegratedPayment.api.expiration(document.querySelector('.exp-input-container'), {default: ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_expiration_date } );
		ObjIntegratedPayment.scheme = ObjIntegratedPayment.api.getSupportedSchemes();

		ObjIntegratedPayment.api.onValidateForm( async ({isFormValid}) => {
			if(isFormValid){
				const payment = await getPayment(props);
				ObjIntegratedPayment.paymentId = payment.payment_id;
				ObjIntegratedPayment.return_url = payment.redirect;
				await ObjIntegratedPayment.api.pay(ObjIntegratedPayment.paymentId, Payplug.Scheme.AUTO, {save_card: false});
			}
			return false;
		});

		ObjIntegratedPayment.api.onCompleted( function (event) {
			const data = {'payment_id' : event.token};
			console.log(data);

			check_payment(data).then( () => {

				console.log("paid");
				console.log(ObjIntegratedPayment.return_url);

				window.location.href = ObjIntegratedPayment.return_url;
			})
			.catch((error) => {
				//TODO:: handling errors
			});
		});

		fieldValidation();

	}, []);


	useEffect(() => {
		const onValidation = () => {
			ObjIntegratedPayment.api.validateForm();
		}
		const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
		return () => { unsubscribeAfterProcessing(); };

	}, [onCheckoutValidation]);

	useEffect(() => {
		const onPayment = () => {
			return { type: 'error' };
		};

		const unsubscribeAfterProcessing = onPaymentSetup(onPayment);
		return () => {
			unsubscribeAfterProcessing();
		};
	}, [
		onPaymentSetup
	]);

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
