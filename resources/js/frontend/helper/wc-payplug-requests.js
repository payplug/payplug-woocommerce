import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';
const settings = getSetting('payplug_data', {});
import { useEffect } from 'react';

export const getPayment = (props) => {
	 const data = getPaymentData(props);

	return new Promise((resolve, reject) => {
		return $.ajax({
			type: 'POST',
			data: data,
			url: settings.payplug_integrated_payment_get_payment_url,

		}).success(function (response) {
			resolve(response);

		}).error(function (error) {
			reject(error);

		});
	});

	function getPaymentData(props) {
		return {
			"billing_first_name" : props.billing.billingData.first_name,
			"billing_last_name" : props.billing.billingData.last_name,
			"billing_company" : props.billing.billingData.company,
			"billing_country" : props.billing.billingData.country,
			"billing_address_1" : props.billing.billingData.address_1,
			"billing_address_2" : props.billing.billingData.address_2,
			"billing_postcode" : props.billing.billingData.postcode,
			"billing_city" : props.billing.billingData.city,
			"billing_state" : props.billing.billingData.state,
			"billing_phone" : props.billing.billingData.phone,
			"billing_email" : props.billing.billingData.email,
			"shipping_first_name" : props.shippingData.shippingAddress.first_name,
			"shipping_last_name" : props.shippingData.shippingAddress.last_name,
			"shipping_company" : props.shippingData.shippingAddress.company,
			"shipping_country" : props.shippingData.shippingAddress.country,
			"shipping_address_1" : props.shippingData.shippingAddress.address_1,
			"shipping_address_2" : props.shippingData.shippingAddress.address_2,
			"shipping_postcode" : props.shippingData.shippingAddress.postcode,
			"shipping_city" : props.shippingData.shippingAddress.city,
			"shipping_state" : props.shippingData.shippingAddress.state,
			"shipping_method": props.shippingData.selectedRates[0],
			"payment_method": props.activePaymentMethod,
			"woocommerce-process-checkout-nonce": settings.wp_nonce
		}
	}

};

export const check_payment = (data) => {
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.payplug_integrated_payment_check_payment_url

		}).success(function (response) {
			resolve(response);

		}).error(function (error) {
			reject(error); // NOT WORKING!!

		});
	});
};

/**
 * Handles the Block Checkout onCheckoutFail event.
 * Displays the error message returned from server in the paymentDetails object in the PAYMENTS notice context container.
 *
 * @param {*} onCheckoutFail The onCheckoutFail event.
 * @param {*} emitResponse   Various helpers for usage with observer.
 */
export const usePaymentFailHandler = (
	onCheckoutFail,
	emitResponse
) => {
	useEffect(
		() =>
			onCheckoutFail( ( { processingResponse: { paymentDetails } } ) => {
				return {
					type: 'failure',
					message: paymentDetails.errorMessage,
					messageContext: emitResponse.noticeContexts.PAYMENTS,
				};
			} ),
		[
			onCheckoutFail,
			emitResponse.noticeContexts.PAYMENTS,
		]
	);
};


export const usePaymentCompleteHandler = (
	onCheckoutSuccess,
	emitResponse,
) => {
	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() =>
			onCheckoutSuccess(
				( { processingResponse: { paymentDetails } } ) =>{
					if(paymentDetails.result === "success"){
						return {
							type: 'success',
							redirectUrl: paymentDetails.redirect,
						}
					}
				}
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[]
	);
};
