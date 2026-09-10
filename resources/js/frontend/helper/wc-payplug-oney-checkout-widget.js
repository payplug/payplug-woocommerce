import {useEffect, useState} from '@wordpress/element';
import {useSelect} from '@wordpress/data';

/**
 * Renders the placeholder the official Oney widget mounts its inline payment-schedule
 * section into (oneyMerchantApp.loadCheckoutSection), for one specific Oney gateway variant.
 */
const OneyCheckoutWidget = ({settings}) => {
	const placeholderId = 'oney-checkout-' + settings?.name;
	const widget = settings?.oney_widget;
	const [isLoading, setIsLoading] = useState(true);

	// settings comes from getSetting(), a one-time snapshot taken at page load, so
	// widget.payment_amount is frozen. Read the live total from the Store API instead, so
	// shipping / coupon / address changes are reflected in the displayed schedule.
	const liveAmount = useSelect((select) => {
		const totals = select('wc/store/cart')?.getCartTotals();

		if (!totals || totals.total_price === undefined || totals.total_price === null) {
			return null;
		}

		const total = parseInt(totals.total_price, 10);

		if (isNaN(total)) {
			return null;
		}

		// Store API totals are expressed in minor units.
		const minorUnit = typeof totals.currency_minor_unit === 'number' ? totals.currency_minor_unit : 2;

		return total / Math.pow(10, minorUnit);
	}, []);

	const paymentAmount = liveAmount !== null ? liveAmount : widget?.payment_amount;

	useEffect(() => {
		if (typeof window.loadOneyWidget !== 'function' || !widget?.business_transaction_code) {
			// Nothing to load (loader script not ready/failed, or this country has no matching
			// business_transaction_code) - don't leave the spinner running forever.
			setIsLoading(false);

			return;
		}

		setIsLoading(true);

		const placeholder = document.getElementById(placeholderId);
		if (placeholder) {
			// The widget doesn't clear its own mount point on repeat calls, so without this a
			// live total change (address/coupon/shipping edit) would stack duplicate sections.
			placeholder.innerHTML = '';
		}

		const options = {
			...widget,
			payment_amount: paymentAmount,
			filter_by: 'business_transaction_code',
			checkout_placeholder: '#' + placeholderId,
			successCallback: () => setIsLoading(false),
			errorCallback: (status, response) => {
				setIsLoading(false);
				console.warn('Oney checkout widget unavailable', status, response);
			},
		};

		window.loadOneyWidget(() => {
			if (typeof window.oneyMerchantApp === 'undefined') {
				setIsLoading(false);

				return;
			}
			window.oneyMerchantApp.loadCheckoutSection({options});
		});
	}, [widget?.business_transaction_code, paymentAmount]);

	return (
		<div>
			{isLoading && (
				<div className="payplug-lds-roller">
					<div></div><div></div><div></div><div></div>
					<div></div><div></div><div></div><div></div>
				</div>
			)}
			<div id={placeholderId}></div>
		</div>
	);
};

export default OneyCheckoutWidget;
