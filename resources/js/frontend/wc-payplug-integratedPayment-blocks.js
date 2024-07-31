import { getSetting } from '@woocommerce/settings';
const settings = getSetting( 'payplug_data', {} );

export class IntegratedPayment extends React.Component
{
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

}
