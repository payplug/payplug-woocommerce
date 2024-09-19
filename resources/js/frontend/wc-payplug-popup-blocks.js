import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useRef } from 'react';
import { useSelect } from '@wordpress/data';
import { getPayment} from "./helper/wc-payplug-requests";
const settings = getSetting( 'payplug_data', {} );

const Popup = ({props: props, settings:_settings}) => {
	const { eventRegistration, emitResponse, shouldSavePayment } = props;
	const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;
	const { CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;
	const order_id = useSelect( ( select ) => select( CHECKOUT_STORE_KEY ).getOrderId() );
	let getPaymentData;

	useEffect(() => {
		const handlePaymentProcessing = async () => {
			let result = {};

			await getPayment(props, _settings, order_id).then( async (response) => {
				getPaymentData = response;
			})

			return {
				type: 'success'
			}
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
		const handlePaymentProcessing = async ({processingResponse: {paymentDetails}}) => {
			await showPopupPayment(getPaymentData).then( () => {
				return {
					type: "error",
					message: "Timeout",
					messageContext: emitResponse.noticeContexts.PAYMENTS
				}
			})

			function showPopupPayment(getPaymentData) {
				return new Promise(async (resolve, reject) => {
					try {
						window.redirection_url = getPaymentData.data.cancel || false;
						await Payplug.showPayment(getPaymentData.data.redirect);
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
