import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';
const settings = getSetting('apple_pay_data', {});

export const getPayment = (props, order_id) => {
	const data = getPaymentData(props);
	return new Promise((resolve, reject) => {
		return $.ajax({
			type: 'POST',
			data: data,
			url: settings.payplug_create_intent_payment,
		}).done(function (response) {
			resolve(response);

		}).fail(function (error) {
			reject(error);

		});
	});

	function getPaymentData(props) {
		const data = {
			"order_id": order_id,
			"woocommerce-process-checkout-nonce": settings.wp_nonce,
			"gateway": "apple_pay"
		};

		if (settings.is_order_pay) {
			data.order_pay_key = settings.order_pay_key;
		}

		return data;
	}
};

export const apple_pay_get_shippings = (data) => {
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_get_shippings
		}).done(function (response) {
			resolve(response);

		}).fail(function (xhr, status, error) {
			reject(error);

		});
	});
}

export const apple_pay_update_payment = (data) => {
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_update_payment
		}).done(function (response) {
			resolve(response);

		}).fail(function (xhr, status, error) {
			reject(error);

		});
	});
}

export const apple_pay_UpdateOrder = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_update_applepay_order
		}).done(function (response) {
			resolve(response);

		}).fail(function (xhr, status, error) {
			reject(error);
		});
	});
}

export const apple_pay_Payment = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_update_applepay_payment
		}).done(function (response) {
			resolve(response);

		}).fail(function (xhr, status, error) {
			reject(error);
		});
	});
}

export const apple_pay_PlaceOrderWithDummyData = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_place_order_with_dummy_data
		}).done(function (response) {
			resolve(response);

		}).fail(function (xhr, status, error) {
			reject(error);
		});
	});
}

export const apple_pay_CancelOrder = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_cancel_order
		}).done(function (response) {
			resolve(response);

		}).fail(function (xhr, status, error) {
			reject(error);
		});
	});
}
