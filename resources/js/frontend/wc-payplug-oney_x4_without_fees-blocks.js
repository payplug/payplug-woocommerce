import {__} from '@wordpress/i18n';
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';
import Oney_Simulation from "./helper/wc-payplug-oney-simulation";

const settings = getSetting('oney_x4_without_fees_data', {});
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities(settings?.title) || defaultLabel;

const Content = (props) => {
	return (
		<Oney_Simulation settings={settings} name={"x4_without_fees"} props={props} />
	);
};

/**
 * Label component
 *
 */
const Label = () => {
	return (
		<span style={{width: '100%'}}>
            {label}
			<Icon/>
        </span>
	)
}

const Icon = () => {
	return (
		<img src={settings?.icon.src} alt={settings?.icon.alt} className={settings.icon.class}
			 style={{float: 'right'}}/>
	)
}

/**
 * Payplug payment method config object.
 */
let oney_x4_without_fees = {
	name: "oney_x4_without_fees",
	label: <Label/>,
	content: <Content/>,
	edit: <Content/>,
	canMakePayment: (props) => {

		if (props.cart.cartItemsCount > settings?.requirements.max_quantity) {
			return false
		}

		if ((props.cartTotals.total_price > settings?.requirements.max_threshold) ||
			(props.cartTotals.total_price < settings?.requirements.min_threshold)) {
			return false;
		}

		return settings?.requirements.allowed_country_codes.indexOf(props.shippingAddress.country) !== -1;
	},
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod(oney_x4_without_fees);

