import {__} from '@wordpress/i18n';
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';


console.log(JSON.stringify(wcPaymentGatewaysData));

const createPaymentMethod = (id, gatewayData, Label) => ({
	name: id,
	label: decodeEntities(gatewayData.title),
	content: <PaymentMethodContent gateway={id} />,
	edit: <PaymentMethodEdit gateway={id} />,
	canMakePayment: (cartData) => {
		// Add logic to determine if this method can be used
		return true;
	},
	ariaLabel: Label,
	supports: {
		features: gatewayData.supports,
	},
});

Object.entries(wcPaymentGatewaysData).forEach(([id, gatewayData]) => {
	console.log(JSON.stringify(gatewayData));
	/**
	 * Label component
	 *
	 */
	let Label = () => {
		return (
			<span style={{ width: '100%' }}>
            {gatewayData.title}
        </span>
		)
	}
	registerPaymentMethod(createPaymentMethod(id, gatewayData, Label));
});


// Generic Payment Method Content component
function PaymentMethodContent({ gateway }) {
	// Implement gateway-specific payment form
	return <div>{gateway} Payment Form</div>;
}

// Generic Payment Method Edit component (for the admin area)
function PaymentMethodEdit({ gateway }) {
	// Implement gateway-specific settings form
	return <div>{gateway} Settings</div>;
}
