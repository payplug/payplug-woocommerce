import React, { useEffect, useRef } from 'react';
import { getPayment} from "./helper/wc-payplug-requests";

const Popup = ({props: props, settings:_settings}) => {
	const { eventRegistration, emitResponse, shouldSavePayment } = props;
	const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;
	// A plain variable would be reset on every re-render, but it's read from the
	// onCheckoutSuccess callback below, which can fire well after a re-render has happened.
	const getPaymentDataRef = useRef(null);

	useEffect(() => {
		const handlePaymentProcessing = async () => {
			// Order-pay always has a real, existing order from the start (the one being
			// repaid), so the payment intent can be created right away.
			if (_settings.is_order_pay) {
				try {
					const response = await getPayment(props, _settings, _settings.order_pay_id);
					getPaymentDataRef.current = response;

					return {
						type: 'success'
					};
				} catch (error) {
					return {
						type: 'error',
						message: error.message,
						messageContext: emitResponse.noticeContexts.PAYMENTS
					};
				}
			}

			// Regular checkout: WooCommerce doesn't create the real order until the place-order
			// submission that follows this step (order creation is deferred until then since
			// WooCommerce 10.8). The payment intent can only be created once that real order
			// exists, so that happens server-side in process_payment() and comes back here via
			// payment_details on the onCheckoutSuccess event below - nothing more to do yet.
			return {
				type: 'success'
			};
		}
		const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		shouldSavePayment,
		onPaymentSetup,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS
	]);

	useEffect(() => {
		const handlePaymentProcessing = async ({ processingResponse }) => {
			// Order-pay's payment intent was already created in onPaymentSetup above (same
			// shape as the AJAX response: { data: { redirect, cancel } }), since a real order
			// already existed there. Regular checkout only gets its payment_id/redirect/cancel
			// here, via payment_details, once process_payment() has created it server-side.
			const paymentDetails = _settings.is_order_pay
				? (getPaymentDataRef.current?.data || {})
				: (processingResponse?.paymentDetails || {});

			return await showPopupPayment(paymentDetails).then( () => {
				return {
					type: "success"
				}
			}).catch( () => {
				return {
					type: "error",
					message: "Timeout",
					messageContext: emitResponse.noticeContexts.PAYMENTS
				}
			})

			function showPopupPayment(paymentDetails) {
				return new Promise(async (resolve, reject) => {
					try {
						window.redirection_url = paymentDetails.cancel || false;
						await Payplug.showPayment(paymentDetails.redirect);
						// Deliberately no resolve() here: Payplug's widget navigates window.top
						// to hosted_payment.return_url itself once the payment finishes inside
						// the lightbox. Resolving as soon as showPayment() opens the lightbox
						// would mark checkout "complete" and let WooCommerce Blocks navigate
						// away on its own (to payment_result.redirect_url) before the widget
						// gets the chance to - tearing the lightbox down before the customer
						// can pay. Only the error path settles this promise.
					} catch (e) {
						reject(e);
					}

				})
			}
		}
		const unsubscribeAfterProcessing = onCheckoutSuccess(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onCheckoutSuccess
	]);

	return (
		<>
		</>
	)

}

export default Popup;
