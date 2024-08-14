import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';
import {promise} from "../../../../../../../wp-includes/js/dist/redux-routine";
const settings = getSetting('payplug_data', {});

export const getPayment = async (props) => {
	 const data = getPaymentData(props);

	 return $.ajax({
		type: 'POST',
		data: data,
		url: settings.payplug_integrated_payment_get_payment_url,
	}).done(function (response) {
		 return response;

	 }).fail(function (error) {
		 return error;

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
			console.log("it success");
			resolve(response);

		}).error(function (error) {

			console.log("it fails");
			reject(error); // NOT WORKING!!

		});
	});
};

