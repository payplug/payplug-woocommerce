import {__} from '@wordpress/i18n';
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
import {decodeEntities} from '@wordpress/html-entities';
import {getSetting} from '@woocommerce/settings';
import Oney_Simulation from './helper/wc-payplug-oney-simulation';

const settings = getSetting('oney_x3_with_fees_data', {});
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities(settings?.title) || defaultLabel;

const Content = (props) => {
	return (
		<Oney_Simulation settings={settings} name={"x3_with_fees"} props={props} />
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
let oney_x3_with_fees = {
	name: "oney_x3_with_fees",
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

registerPaymentMethod(oney_x3_with_fees);

/**
 * Cart page Oney logo
 */
(() => {
	if (
		!!window?.wp?.data?.select('wc/store/cart') &&
		document.body.classList.contains('woocommerce-cart')
	)
	{
		const { createElement } = window.wp.element;
		const { select } = window.wp.data;
		const { createRoot } = require('react-dom/client');
		let root = null;
		const createCustomElement = () => {

			// Get cart total
			const cartStore = select('wc/store/cart');
			if(is_subscription()){
				return false;
			}

			let cartTotal = cartStore?.getCartTotals()?.total_price || 0;
			let qty = 0;

			cartStore?.getCartData().items.forEach((item) => {
				qty += parseFloat(item.quantity);
			});

			let oney_available = false;
			if ((cartTotal >= settings?.requirements['min_threshold']) && (cartTotal <= settings?.requirements['max_threshold']) && (qty <= settings?.requirements['max_quantity'])) {
				oney_available = true;
			} else {
				oney_available = false;
			}

			function is_subscription(){
				let bool = false;
				cartStore.getCartData().items.forEach(function(item){
					if( ["subscription", "downloadable_subscription", "virtual_subscription", "variable-subscription", "subscription_variation"].includes(item.type) ){
						bool = true;
					}
				})
				return bool;
			}

			return createElement('div',
				{
					className: 'wc-block-components-totals-item payplug-oney',
					style: {
						display: 'flex',
						justifyContent: 'space-between',
						alignItems: 'center'
					}
				},
				[
					createElement('div',
						{
							className: 'oney-message',
							style: {
								display: 'flex',
								alignItems: 'center',
								gap: '8px'
							}
						},
						settings.oney_cart_label
					),
					createElement('img', {
						src: settings?.oney_cart_logo,
						alt: 'Oney Payplug',
						className: `oney-3x4x ${oney_available ? '' : 'disable-checkout-icons'}`,
						style: {
							maxWidth: '50%',
							height: 'auto',
							display: 'block',
							margin: '10px 0'
						}
					})
				]
			);
		};

		const renderCustomContent = () => {
			try {
				const cartTotalsBlock = document.querySelector('.wc-block-components-totals-wrapper');

				if (cartTotalsBlock) {
					// Look for existing container
					let container = document.querySelector('.payplug-totals-container');
					const totalsWrapper = document.querySelector('.wc-block-components-totals-wrapper');

					// Create container if it doesn't exist
					if (!container) {
						container = document.createElement('div');
						container.className = 'payplug-totals-container';
						totalsWrapper.insertBefore(container, totalsWrapper.firstChild);
						root = createRoot(container);

					}

					// Use regular render
					if (root) {
						root.render(createCustomElement());
					}
				}
			} catch (error) {
				console.error('Render error:', error);
			}
		};

		// Initial setup
		const observer = new MutationObserver((mutations, obs) => {
			if (document.querySelector('.wc-block-components-totals-wrapper') && window.wc?.blocksCheckout) {
				obs.disconnect();
				renderCustomContent();

				// Subscribe to store changes
				wp.data.subscribe(() => {
					const cartStore = select('wc/store/cart');
					const cartTotals = cartStore?.getCartTotals();
					if (cartTotals) {
						renderCustomContent();
					}
				});
			}
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});


	}

})();
