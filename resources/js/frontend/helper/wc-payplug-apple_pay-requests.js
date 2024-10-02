import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';
const settings = getSetting('apple_pay_data', {});
import { useEffect } from 'react';

export const getPayment = (props, order_id) => {
	const data = getPaymentData(props);
	return new Promise((resolve, reject) => {
		return $.ajax({
			type: 'POST',
			data: data,
			url: settings.payplug_create_intent_payment,
		}).success(function (response) {
			resolve(response);

		}).error(function (error) {
			reject(error);

		});
	});

	function getPaymentData(props) {
		return {
			"order_id": order_id,
			"woocommerce-process-checkout-nonce": settings.wp_nonce,
			"gateway": "apple_pay"
		}
	}
};

export const apple_pay_update_payment = (data) => {
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_update_payment
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!

		});
	});
}
