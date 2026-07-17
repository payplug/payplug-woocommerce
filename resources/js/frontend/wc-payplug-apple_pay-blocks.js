import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { registerPaymentMethod, registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { useEffect, useRef } from 'react';
import { apple_pay_update_payment, getPayment } from './helper/wc-payplug-apple_pay-requests';
import ApplePayCart from './wc-payplug-apple_pay_cart-blocks';

const settings = getSetting( 'apple_pay_data', {} );
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities( settings?.title ) || defaultLabel;

const Content = (props) => {

	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup, onCheckoutSuccess} = eventRegistration;
	// A plain local variable would be reset on every re-render, but it's read from
	// async callbacks/DOM handlers that can fire well after a re-render has happened.
	const sessionRef = useRef(null);

	useEffect(() => {
		const element = jQuery("form .wp-block-woocommerce-checkout-actions-block .wc-block-components-button");
		// Safari requires ApplePaySession to be constructed synchronously from a user gesture,
		// so it's created here on the raw click rather than inside the async onPaymentSetup
		// callback below. We don't preventDefault(): checkout's own field validation and order
		// sync must run normally first, exactly like every other payment method in the list.
		const onPlaceOrderClick = () => {
			apple_pay.CreateSession();
			apple_pay.CancelOrder();
		};
		element.on("click", onPlaceOrderClick);
		return () => {
			element.off("click", onPlaceOrderClick);
		};
	// CreateSession() reads props.billing.cartTotal, so the handler must be re-bound whenever
	// it changes to avoid using a stale value from the first render.
	},[props.billing.cartTotal.value]);



	useEffect(() => {
		const handlePaymentProcessing = async () => {

			// Order-pay always has a real, existing order from the start (the one being
			// repaid), so the payment intent can be created right away.
			if (settings.is_order_pay) {
				try {
					const response = await getPayment(props, settings.order_pay_id);

					if (!response || response.success === false) {
						const errorMessage = (response && response.data && response.data.message)
							|| (response && typeof response.data === 'string' ? response.data : null)
							|| __('Payment processing failed. Please retry.', 'payplug');

						return {
							type: emitResponse.responseTypes.ERROR,
							message: errorMessage,
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						};
					}

					apple_pay.BeginSession(response);

					return {
						type: emitResponse.responseTypes.SUCCESS
					}
				} catch (error) {
					const serverData = error && error.responseJSON && error.responseJSON.data;
					const serverMessage = (serverData && serverData.message)
						|| (typeof serverData === 'string' ? serverData : null);

					return {
						type: emitResponse.responseTypes.ERROR,
						message: serverMessage || __('Payment processing failed. Please retry.', 'payplug'),
						messageContext: emitResponse.noticeContexts.PAYMENTS,
					};
				}
			}

			// Regular checkout: WooCommerce doesn't create the real order until the place-order
			// submission that follows this step (order creation is deferred until then since
			// WooCommerce 10.8). The Apple Pay session was already constructed on click; the
			// merchant session/payment intent for it can only be created once that real order
			// exists, so that happens server-side in process_payment() and comes back here via
			// payment_details on the onCheckoutSuccess event below - nothing more to do yet.
			return {
				type: emitResponse.responseTypes.SUCCESS
			};
		}

		const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onPaymentSetup,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS
	]);

	useEffect(() => {
		const handleCheckoutSuccess = ({ orderId, processingResponse }) => {

			// Order-pay's session.begin()/onvalidatemerchant were already wired up in
			// BeginSession() above, during onPaymentSetup - just wait for authorization.
			if (!settings.is_order_pay) {
				sessionRef.current.order_id = orderId;
				apple_pay.BeginSessionFromPaymentDetails(processingResponse?.paymentDetails || {}, processingResponse?.redirectUrl);
			}

			return new Promise((resolve) => {
				sessionRef.current.onpaymentauthorized = async event => {
					const data = {
						'action': 'applepay_update_payment',
						'post_type': 'POST',
						'payment_id': sessionRef.current.payment_id,
						'payment_token': event.payment.token,
						'order_id': sessionRef.current.order_id
					};

					try {
						const res = await apple_pay_update_payment(data);

						if (res.success !== true) {
							sessionRef.current.completePayment({"status": ApplePaySession.STATUS_FAILURE});
							resolve({
								type: emitResponse.responseTypes.ERROR,
								message: __('Payment processing failed. Please retry.', 'payplug'),
								messageContext: emitResponse.noticeContexts.PAYMENTS,
							});
							return;
						}

						sessionRef.current.completePayment({"status": ApplePaySession.STATUS_SUCCESS});
						resolve({
							type: emitResponse.responseTypes.SUCCESS,
							redirectUrl: sessionRef.current.return_url,
						});
					} catch (error) {
						sessionRef.current.completePayment({"status": ApplePaySession.STATUS_FAILURE});
						resolve({
							type: emitResponse.responseTypes.ERROR,
							message: __('Payment processing failed. Please retry.', 'payplug'),
							messageContext: emitResponse.noticeContexts.PAYMENTS,
						});
					}
				};
			});
		}
		const unsubscribeAfterProcessing = onCheckoutSuccess(handleCheckoutSuccess);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onCheckoutSuccess
	]);

	let apple_pay = {
		CreateSession: function () {
			const request = {
				"countryCode": settings.payplug_countryCode,
				"currencyCode": settings.payplug_currencyCode,
				"merchantCapabilities": [
					"supports3DS"
				],
				"supportedNetworks": [
					"visa",
					"masterCard"
				],
            	"supportedTypes": [
					"debit",
					"credit"
				],
				"total": {
					"label": "Apple Pay",
					"type": "final",
					"amount": props.billing.cartTotal.value/100
				},
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': settings.payplug_apple_pay_domain
				}))
			}

			sessionRef.current = new ApplePaySession(3, request)
		},
		CancelOrder: function () {
			sessionRef.current.oncancel = event => {
				window.location = sessionRef.current.cancel_url
			}
		},
		BeginSession: function (response) {
			const session = sessionRef.current;
			session.payment_id = response.data.payment_id;
			session.order_id = settings.order_pay_id;
			session.cancel_url = response.data.cancel;
			session.return_url = response.data.redirect;
			apple_pay.MerchantValidated(session, response.data.merchant_session)
			session.begin()
		},
		// Counterpart to BeginSession() for regular checkout, where the merchant session/payment
		// intent only becomes available once the real order exists (see the onCheckoutSuccess
		// comment above) - payment_details values are always strings, so merchant_session is
		// JSON-encoded server-side and needs to be parsed back into an object here.
		BeginSessionFromPaymentDetails: function (paymentDetails, fallbackRedirectUrl) {
			const session = sessionRef.current;
			session.payment_id = paymentDetails.payment_id;
			session.cancel_url = paymentDetails.cancel_url;
			session.return_url = paymentDetails.return_url || fallbackRedirectUrl;

			let merchantSession = null;
			try {
				merchantSession = JSON.parse(paymentDetails.merchant_session);
			} catch (error) {
				merchantSession = null;
			}

			apple_pay.MerchantValidated(session, merchantSession)
			session.begin()
		},
		MerchantValidated: function(session, merchant_session) {
			session.onvalidatemerchant = async event => {
				try {
					session.completeMerchantValidation(merchant_session)
				} catch (err) {
					apple_pay.CancelOrder()
				}
			}
		}
	}

	return (
		<></>
	)

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
const ApplePay = {
	name: "apple_pay",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports
	},
};

/**
 *
 * @param props
 * @returns {JSX.Element}
 * @Content for express payment method
 */
const ExpressContent = (props) => {
	return (
		<>
			<div id="apple-pay-button-wrapper">
				<apple-pay-button
					id="apple-pay-button"
					buttonstyle="black"
					type="pay"
					locale={settings?.payplug_locale}
				></apple-pay-button>
			</div>
			<ApplePayCart {...props} />
		</>
	);
};

const ExpressApplePay = {
	name: "apple_pay",
	content: <ExpressContent/>,
	edit: <ExpressContent/>,
	canMakePayment: (data) => {

		if (!settings?.is_cart) {
			return false;
		}

		settings.payplug_apple_pay_shipping_required = data.cartNeedsShipping;
		settings.total_amount = data.cartTotals.total_price;

		if (!data.cartNeedsShipping) {
			return true;
		}

		// On a fresh checkout page load, no address has been entered yet, so shipping
		// rates/selection may not exist at all: Apple Pay's own sheet collects the address
		// and lets the merchant offer shipping methods dynamically from there
		// (see ApplePayCart's onshippingmethodselected), so don't block the button on it.
		if (!data.selectedShippingMethods || !data.selectedShippingMethods[0]) {
			return true;
		}

		let selectedShippingMethod = data.selectedShippingMethods[0];

		settings.payplug_carriers.forEach(function(item){

			if (item.identifier === selectedShippingMethod){
				item.selected = true;
			}

		})

		let payplug_authorized_carriers = settings?.payplug_authorized_carriers;
		let selected_shipping = data.selectedShippingMethods[0].split(":");
		let authorized = false;

		payplug_authorized_carriers.forEach(function(item){
			if (selected_shipping[0] === item){
				authorized = true
			}
		});

		return authorized;
	},
	paymentMethodId: "apple_pay"

};

registerExpressPaymentMethod( ExpressApplePay );

registerPaymentMethod( ApplePay );
