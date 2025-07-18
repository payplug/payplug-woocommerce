import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';
const settings = getSetting('payplug_data', {});

export const getPayment = (props, _settings, order_id) => {
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
			"gateway": _settings?.payment_method
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
