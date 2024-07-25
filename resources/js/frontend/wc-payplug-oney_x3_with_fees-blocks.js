
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';


const settings = getSetting( 'oney_x3_with_fees_data', {} );
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities( settings?.title ) || defaultLabel;

let Content;

const translations = settings?.translations;

console.log(JSON.stringify(settings?.oney_response));

if (settings?.oney_response) {
	const down_payment_amount = decodeEntities( settings?.oney_response['x3_with_fees']['down_payment_amount'] )  || defaultLabel;
	let total_price_oney = down_payment_amount;

	settings?.oney_response['x3_with_fees']['installments'].forEach((amount) => {

		total_price_oney += amount['amount'];

	})

	const currency = decodeEntities( settings?.currency) ;
	Content = () => {
		return (
			<p>
				<div className="payplug-oney-flex">
					<div>{translations['bring']} :</div>
					<div>{down_payment_amount} {currency}</div>
				</div>
				<div className="payplug-oney-flex">
					<small>( {translations['oney_financing_cost']} <b>{settings?.oney_response['x3_with_fees']['total_cost']} {currency}</b> TAEG : <b>{settings?.oney_response['x3_with_fees']['effective_annual_percentage_rate']} %</b> )</small>
				</div>
				<div className="payplug-oney-flex">
					<div>{translations['1st monthly payment']}:</div>
					<div>{settings?.oney_response['x3_with_fees']['installments'][0]['amount']} {currency}</div>
				</div>
				<div className="payplug-oney-flex">
					<div>{translations['2nd monthly payment']}:</div>
					<div>{settings?.oney_response['x3_with_fees']['installments'][1]['amount']} {currency}</div>
				</div>
				<div className="payplug-oney-flex">
					<div><b>{translations['oney_total']}</b></div>
					<div><b>{total_price_oney} {currency}</b></div>
				</div>
			</p>
		);
	};
} else {
	Content = () => {
		return (
			<div className={settings?.description.class}>
				{settings?.description.text}
			</div>

		);
	};
}

/**
 * Label component
 *
 */
const Label = () => {
	return (
		<span style={{ width: '100%' }}>
            {label}
			<Icon />
        </span>
	)
}

const Icon = () => {
	return (
		<img src={settings?.icon.src} alt={settings?.icon.alt} className={settings?.icon.class} style={{float: 'right'}}/>
	)
}

/**
 * Payplug payment method config object.
 */
const oney_x3_with_fees = {
	name: "oney_x3_with_fees",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( oney_x3_with_fees );

