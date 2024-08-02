import { getSetting } from '@woocommerce/settings';
const settings = getSetting( 'payplug_data', {} );

export class IntegratedPayment extends React.Component
{

	ObjIntegratedPayment = {
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
	};

	render(){
		return (
			<>
				<div className="payplug IntegratedPayment -loaded">
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

	componentDidMount = () => {
		//init form after rendered
		this.initIntegratedPayment()

	};

	initIntegratedPayment = () => {

		// Create an instance of Integrated Payments
		this.ObjIntegratedPayment.api = new Payplug.IntegratedPayment(false);
		this.ObjIntegratedPayment.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX)

		// Add each payments fields
		this.ObjIntegratedPayment.form.cardHolder = this.ObjIntegratedPayment.api.cardHolder(document.querySelector('.cardHolder-input-container'), {default: this.ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cardholder } );
		this.ObjIntegratedPayment.form.pan = this.ObjIntegratedPayment.api.cardNumber(document.querySelector('.pan-input-container'), {default: this.ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_card_number } );
		this.ObjIntegratedPayment.form.cvv = this.ObjIntegratedPayment.api.cvv(document.querySelector('.cvv-input-container'), {default: this.ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_expiration_date } );
		// With one field for expiration date
		this.ObjIntegratedPayment.form.exp = this.ObjIntegratedPayment.api.expiration(document.querySelector('.exp-input-container'), {default: this.ObjIntegratedPayment.inputStyle.default, placeholder: settings?.payplug_integrated_payment_cvv } );

		this.ObjIntegratedPayment.scheme = this.ObjIntegratedPayment.api.getSupportedSchemes();

	}

}
