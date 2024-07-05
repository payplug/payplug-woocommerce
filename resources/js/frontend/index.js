import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';


const settings = getSetting( 'apple_pay_data', {} )

const defaultLabel = __(
	'Apple Pay',
	'woo-gutenberg-products-block'
);


const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	let local = settings.local;
	return (
		<div id="apple-pay-button-wrapper"><apple-pay-button buttonstyle="black" type="pay" locale={local}></apple-pay-button></div>
	);
};

const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};



const ApplePay = {
	name: "apple_pay",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( ApplePay );
