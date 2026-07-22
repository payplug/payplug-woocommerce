import { getSetting } from '@woocommerce/settings';
import $ from 'jquery';
const settings = getSetting('payplug_data', {});

// jQuery's .fail() callback receives a jqXHR object, not an Error - normalize it so
// callers doing `catch (error) { error.message }` get a usable string instead of undefined.
function toRequestError(jqXHR, textStatus, errorThrown) {
	const data = jqXHR?.responseJSON?.data;
	const message = (typeof data === 'string' && data) || data?.message || errorThrown || textStatus || 'Request failed';
	return new Error(message);
}

export const getPayment = (props, _settings, order_id) => {
	const data = getPaymentData(props);
	return new Promise((resolve, reject) => {
		return $.ajax({
			type: 'POST',
			data: data,
			url: settings.payplug_create_intent_payment,

		}).done(function (response) {
			resolve(response);

		}).fail(function (jqXHR, textStatus, errorThrown) {
			reject(toRequestError(jqXHR, textStatus, errorThrown));

		});
	});

	function getPaymentData(props) {
		return {
			"order_id": order_id,
			"woocommerce-process-checkout-nonce": settings.wp_nonce,
			"gateway": _settings?.payment_method,
			"order_pay_key": _settings?.order_pay_key || ''
		}
	}
};

export const check_payment = (data) => {
	return new Promise((resolve, reject) => {
		$.ajax({
			type: 'POST',
			data: data,
			url: settings.payplug_integrated_payment_check_payment_url
		}).done(function (response) {
			resolve(response);

		}).fail(function (jqXHR, textStatus, errorThrown) {
			reject(toRequestError(jqXHR, textStatus, errorThrown));

		});
	});
};
