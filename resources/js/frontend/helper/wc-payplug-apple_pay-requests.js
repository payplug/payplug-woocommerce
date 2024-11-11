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

export const apple_pay_updateOrderTotal = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_get_order_totals
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!

		});
	});
}


export const apple_pay_getShippings = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_get_shippings
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!

		});
	});
}

export const apple_pay_UpdateOrder = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_update_applepay_order
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!
		});
	});
}

export const apple_pay_Payment = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_update_applepay_payment
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!
		});
	});
}

export const apple_pay_PlaceOrderWithDummyData = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_place_order_with_dummy_data
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!
		});
	});
}

export const apple_pay_CancelOrder = (data) =>{
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.ajax_url_applepay_cancel_order
		}).success(function (response) {
			resolve(response);

		}).error(function (xhr, status, error) {
			reject(error); // NOT WORKING!!
		});
	});
}
