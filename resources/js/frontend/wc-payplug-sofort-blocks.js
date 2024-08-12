
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'sofort_data', {} );
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities( settings?.title ) || defaultLabel;
const allowed_country_codes = settings?.allowed_country_codes;

/**
 * Content component
 */
const Content = () => {
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
const Sofort = {
	name: "sofort",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: (data) => {

		if (allowed_country_codes.includes(data.billingData.country)) {
			return true;
		} else {
			return false;
		}

	},
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( Sofort );

