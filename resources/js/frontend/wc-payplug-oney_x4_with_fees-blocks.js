import {__} from '@wordpress/i18n';
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';



const settings = getSetting('oney_x4_with_fees_data', {});
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities(settings?.title) || defaultLabel;

let Content;

const translations = settings?.translations;

const allowed_country_codes = settings?.requirements.allowed_country_codes;

const oney_response = settings?.oney_response;

if (typeof oney_response.x4_with_fees !== 'undefined') {
	var down_payment_amount = parseFloat(settings?.oney_response['x4_with_fees']['down_payment_amount']) ;
	var total_price_oney = down_payment_amount;
	settings?.oney_response['x4_with_fees']['installments'].forEach((amount) => {

		total_price_oney += parseFloat(amount['amount']);

	});


	Content = (e) => {

		let country = e.shippingData.shippingAddress.country;
		if (allowed_country_codes.indexOf(country) === -1) {
			settings.icon.class = 'disable-checkout-icons';
			return (
				<div className={settings?.oney_disabled.validations.country.class}>
					{settings?.oney_disabled.validations.country.text}
				</div>

			);
		} else if (e.cartData.cartItems.length > settings?.requirements.max_quantity) {
			settings.icon.class = 'disable-checkout-icons';
			return (
				<div className={settings?.oney_disabled.validations.items_count.class}>
					{settings?.oney_disabled.validations.items_count.text}
				</div>

			);
		} else if ((e.billing.cartTotal.value > settings?.requirements.max_threshold)
			|| (e.billing.cartTotal.value < settings?.requirements.min_threshold)) {
			settings.icon.class = 'disable-checkout-icons';
			return (
				<div className={settings?.oney_disabled.validations.amount.class}>
					{settings?.oney_disabled.validations.amount.text}
				</div>

			);
		} else {
			settings.icon.class = 'payplug-payment-icon';
			return (
				<div>
					<div className="payplug-oney-flex">
						<div>{translations['bring']} :</div>
						<div>{down_payment_amount} {e.billing.currency.symbol}</div>
					</div>
					<div className="payplug-oney-flex">
						<small>( {translations['oney_financing_cost']}
							<b>{settings?.oney_response['x4_with_fees']['total_cost']} {e.billing.currency.symbol}</b> TAEG
							: <b>{settings?.oney_response['x4_with_fees']['effective_annual_percentage_rate']} %</b> )</small>
					</div>
					<div className="payplug-oney-flex">
						<div>{translations['1st monthly payment']}:</div>
						<div>{settings?.oney_response['x4_with_fees']['installments'][0]['amount']} {e.billing.currency.symbol}</div>
					</div>
					<div className="payplug-oney-flex">
						<div>{translations['2nd monthly payment']}:</div>
						<div>{settings?.oney_response['x4_with_fees']['installments'][1]['amount']} {e.billing.currency.symbol}</div>
					</div>
					<div className="payplug-oney-flex">
						<div>{translations['3rd monthly payment']}:</div>
						<div>{settings?.oney_response['x4_with_fees']['installments'][2]['amount']} {e.billing.currency.symbol}</div>
					</div>
					<div className="payplug-oney-flex">
						<div><b>{translations['oney_total']}</b></div>
						<div><b>{total_price_oney.toFixed(2)} {e.billing.currency.symbol}</b></div>
					</div>
				</div>
			);
		}
	};
} else {
	// No oney response
	Content = (e) => {
		let country = e.shippingData.shippingAddress.country;
		if (allowed_country_codes.indexOf(country) === -1) {
			return (
				<div className={settings?.oney_disabled.validations.country.class}>
					{settings?.oney_disabled.validations.country.text}
				</div>

			);
		} else if (e.cartData.cartItems.length > settings?.requirements.max_quantity) {
			return (
				<div className={settings?.oney_disabled.validations.items_count.class}>
					{settings?.oney_disabled.validations.items_count.text}
				</div>

			);
		} else if ((e.billing.cartTotal.value > settings?.requirements.max_threshold)
			|| (e.billing.cartTotal.value < settings?.requirements.min_threshold)) {
			return (
				<div className={settings?.oney_disabled.validations.amount.class}>
					{settings?.oney_disabled.validations.amount.text}
				</div>

			);
		}

	};
}

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
let oney_x4_with_fees = {
	name: "oney_x4_with_fees",
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

		return allowed_country_codes.indexOf(props.shippingAddress.country) !== -1;
	},
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod(oney_x4_with_fees);

