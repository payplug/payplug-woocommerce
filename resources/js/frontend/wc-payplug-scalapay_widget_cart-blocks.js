import { registerPlugin } from '@wordpress/plugins';
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';
import { isEligible } from './helper/wc-payplug-ppro-eligibility';
import { ScalapayWidget } from './helper/wc-payplug-scalapay-widget';

const settings = getSetting( 'payplug_scalapay_widget_cart_data', {} );

const CartWidget = ( { cart, context } ) => {
	if ( 'woocommerce/cart' !== context || ! cart ) {
		return null;
	}

	const billingAddress = { country: cart.billingAddress?.country || settings.country };
	if ( ! isEligible( settings.restrictions, { cartTotals: cart.cartTotals, billingAddress } ) ) {
		return null;
	}

	return <ScalapayWidget widget={ settings.widget } amountKey={ cart.cartTotals?.total_price } />;
};

registerPlugin( 'payplug-scalapay-widget-cart', {
	render: () => (
		<ExperimentalOrderMeta>
			<CartWidget />
		</ExperimentalOrderMeta>
	),
	scope: 'woocommerce-checkout',
} );
