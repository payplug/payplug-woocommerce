
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import Popup from "./wc-payplug-popup-blocks";

const settings = getSetting( 'american_express_data', {} );
const defaultLabel = __('Gateway method title', 'payplug');

const label = decodeEntities( settings?.title ) || defaultLabel;
/**
 * Content component
 */
const Content = (props) => {

	if(settings?.popup === true){
		console.log("popup");

		return (
			<Popup settings={settings} props={props} />
		)
	}

	return window.wp.htmlEntities.decodeEntities( settings?.description || '' );
};
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
		<img src={settings?.icon.src} alt={settings?.icon.icon_alt} className="payplug-payment-icon" style={{float: 'right'}}/>
	)
}

/**
 * Payplug payment method config object.
 */
const Amex = {
	name: "american_express",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( Amex );
