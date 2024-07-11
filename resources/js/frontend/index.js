import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';


const settings = getSetting( 'payplug_data', {} )

const defaultLabel = __(
	'Apple Pay',
	'woo-gutenberg-products-block'
);


const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {

	return (
		decodeEntities( settings.title ) || ''
	);
};


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
		<img src="https://woocommerce.local/wp-content/plugins/payplug//assets/images/checkout/logos_scheme_CB.svg" alt="Visa & Mastercard" className="payplug-payment-icon" style={{float: 'right'}}/>
	)
}



const ApplePay = {
	name: settings.name,
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
