import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useRef } from 'react';
import { useSelect } from '@wordpress/data';
import {check_payment, createOrder, getPayment} from "./helper/wc-payplug-requests";
import {apple_pay_update_payment} from "./helper/wc-payplug-apple_pay-requests";
const settings = getSetting( 'payplug_data', {} );

const Popup = ({props: props,}) => {
	const { eventRegistration, emitResponse, shouldSavePayment } = props;
	const { onPaymentSetup, onCheckoutSuccess } = eventRegistration;
	const { PAYMENT_STORE_KEY, CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;
	const order_id = useSelect( ( select ) => select( CHECKOUT_STORE_KEY ).getOrderId() );
	let getPaymentData;

	useEffect((event) => {
		const handlePaymentProcessing = async () => {
			let result = {};

			await getPayment(props, order_id).then( async (response) => {
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
				setTimeotu(function(){
					return {
						type: "error",
						message: "Timeout",
						messageContext: emitResponse.noticeContexts.PAYMENTS
					}
				},60000)
			})

			function showPopupPayment(getPaymentData) {
				return new Promise(async (resolve, reject) => {
					try {
						await Payplug.showPayment(getPaymentData.data.redirect);
						resolve();

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
