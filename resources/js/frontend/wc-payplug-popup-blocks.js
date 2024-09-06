import { getSetting } from '@woocommerce/settings';
import React, { useEffect, useRef } from 'react';
import { useSelect } from '@wordpress/data';
import {check_payment, createOrder, getPayment} from "./helper/wc-payplug-requests";
const settings = getSetting( 'payplug_data', {} );

const Popup = ({props: props,}) => {
	const { eventRegistration, emitResponse, shouldSavePayment } = props;
	const { onCheckoutValidation, onPaymentSetup, onCheckoutSuccess } = eventRegistration;
	const { PAYMENT_STORE_KEY, CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;
	const order_id = useSelect( ( select ) => select( CHECKOUT_STORE_KEY ).getOrderId() );

	useEffect((event) => {
		const handlePaymentProcessing = async () => {

			console.log(order_id);

			let result = {};
			await getPayment(props, order_id).then( async (response) => {
				await showPopupPayment(response)
			})

			return {
				type: 'error'
			}

			function showPopupPayment(response) {
				return new Promise(async (resolve, reject) => {
					try {
						await Payplug.showPayment(response.data.redirect);
						resolve();

					} catch (e) {
						reject(e);
					}
				})
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

	return (
		<>
		</>
	)

}

export default Popup;
