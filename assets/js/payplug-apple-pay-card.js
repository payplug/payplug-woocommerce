/* global window, apple_pay_params */
(function($){

	var $apple_pay_button = $('apple-pay-button')
	var session = null;
	var apple_pay = {
		load_order_total: false,
		init: function () {
			$apple_pay_button = $('apple-pay-button')
			$apple_pay_button.on(
				'click',
				apple_pay.ProcessCheckout
			)
		},
		ProcessCheckout: function (e) {

			console.log('clicked');
			e.preventDefault()
			e.stopImmediatePropagation()
			apple_pay.CreateSession()

			$('.woocommerce').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } })
			$.post(
				apple_pay_params.ajax_url_payplug_create_order,
				$('form.woocommerce-checkout').serialize()
			).done(apple_pay.OrdernPaymentCreated)
		},
		OrdernPaymentCreated: function (response) {
			$('woocommerce').unblock()
			if ('success' !== response.result) {
				var error_messages = response.messages || ''

				return error_messages;
			}
			$('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove()
			apple_pay.BeginSession(response)
		},

		CreateSession: function () {
			const request = {
				"countryCode": apple_pay_params.countryCode,
				"currencyCode": apple_pay_params.currencyCode,
				"merchantCapabilities": [
					"supports3DS"
				],
				"supportedNetworks": [
					"visa",
					"masterCard"
				],
				"total": {
					"label": "Apple Pay",
					"type": "final",
					"amount": apple_pay_params.total
				},
				'shippingMethods': [
					{
						'identifier': 'standard',
						'label': 'Standard Shipping',
						'amount': '5.00',
						'detail': 'Next-day delivery'
					},
					{
						'identifier': 'express',
						'label': 'Express Shipping',
						'amount': '10.00',
						'detail': 'Next-day delivery'
					}
				],
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': apple_pay_params.apple_pay_domain
				}))
			}
			session = new ApplePaySession(3, request);
			session.begin();
		},
		BeginSession: function (response) {
			session.payment_id = response.payment_id
			session.order_id = response.order_id
			session.cancel_url = response.cancel_url
			session.return_url = response.return_url
			apple_pay.MerchantValidated(session, response.merchant_session)
			session.begin()
		},
		MerchantValidated: function(session, merchant_session) {
			session.onvalidatemerchant = async event => {
				try {
					session.completeMerchantValidation(merchant_session)
				} catch (err) {
					alert(err)
				}
			}
		}
	}

	var applePaycontroller = function(){

			//enable buttons
			apple_pay.init();


	}

	$apple_pay_button.on("click", applePaycontroller);

	$( document ).ajaxComplete(function() {
		applePaycontroller();
	});

	$("[name=payment_method]").prop("checked", false);

	//GET ORDER TOTALS ON SHIPPING METHOD SELECTION
	$(document.body).on('updated_shipping_method', function() {

		console.log('Shipping method updated');
	});

})(jQuery)




