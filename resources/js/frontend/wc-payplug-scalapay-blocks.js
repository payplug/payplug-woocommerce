import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { isEligible } from './helper/wc-payplug-ppro-eligibility';
import { ScalapayWidget } from './helper/wc-payplug-scalapay-widget';
import { ScalapayLabel } from './helper/wc-payplug-scalapay-label';

const settings = getSetting( 'scalapay_data', {} );
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities( settings?.title ) || defaultLabel;

/**
 * Content component
 */
const Content = ( { billing } ) => {
	return (
		<>
			{ window.wp.htmlEntities.decodeEntities( settings?.description || '' ) }
			<ScalapayWidget widget={ settings?.widget } amountKey={ billing?.cartTotal?.value } />
		</>
	);
};
/**
 * Payplug payment method config object.
 */
const Scalapay = {
	name: "scalapay",
	label: <ScalapayLabel label={ label } icon={ settings?.icon } />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: ( data ) => isEligible( settings.ppro_restrictions, data ),
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

// Register the defined payment method object
registerPaymentMethod( Scalapay );
