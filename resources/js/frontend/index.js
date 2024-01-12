import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'dummy_data', {} );

const defaultLabel = __(
	'Payplug Paymentssss',
	'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	return decodeEntities( "<p>a bola</p><h2>Hello Mundo</h2>" );
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

/**
 * Dummy payment method config object.
 */
const payplug = {
	name: "payplug",
	icons: [
		{
		id: "payplug",
		src: "http://local.woocommerce.com/wp-content/plugins/payplug//assets/images/checkout/logos_scheme_CB.svg",
		alt: "FDDDDXXXX"
		}
	],
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( payplug );
