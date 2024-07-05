
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'payplug_data', {} );

const defaultLabel = __(
	'Dummy Payments',
	'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	let x = settings.description;
	return (
		<p>
			<div className="payplug-oney-flex">
				<div>{x}</div>
				<div>11111</div>
			</div>
			<div className="payplug-oney-flex">
				<small>( TEST TEXT <b>TEST TEXT</b> TAEG : <b>TEST TEXT</b></small>
			</div>
			<div className="payplug-oney-flex">
				<div>TEST TEXT:</div>
				<div>TEST TEXT</div>
			</div>
			<div className="payplug-oney-flex">
				<div>TEST TEXT:</div>
				<div>TEST TEXT</div>
			</div>
			<div className="payplug-oney-flex">
				<div><b>TEST TEXT</b></div>
				<div><b>TEST TEXT</b></div>
			</div>
		</p>
	);
};
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

const Oney = () => {
	return <span style={{width: '100%'}}>
					OLA MUNDO
				<Icon/>
			</span>
}

/**
 * Dummy payment method config object.
 */
const Payplug = {
	name: "payplug",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( Payplug );
