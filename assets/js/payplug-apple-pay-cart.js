/* global window, apple_pay_params */
(function($){

	var $apple_pay_button = $('apple-pay-button')
	var session = null;
	var apple_pay = {
		load_order_total: false,
		init: function () {

			jQuery.post(
				apple_pay_params.ajax_url_applepay_get_order_totals

			).done(function(results){
				if(results.success){
					apple_pay_params.total = results.data;

				} else {
					$apple_pay_button.remove();
				}

				console.log("get_order_totals");
				console.log(results);

			}).done(function(){
				apple_pay.getShippings();

			}).fail( function() {
				$apple_pay_button.remove();

			});

			$apple_pay_button.on('click', apple_pay.ProcessCheckout)
		},
		getShippings: function(){
			jQuery.post(
				apple_pay_params.ajax_url_applepay_get_shippings

			).done(function(results){
				if(results.data.length === 0){
					$apple_pay_button.remove();
				}

				console.log("get_order_totals");
				console.log(results);

				apple_pay_params.carriers = results.data;
			}).fail( function() {
				$apple_pay_button.remove();

			})
		},
		ProcessCheckout: function (e) {
			e.preventDefault();
			e.stopImmediatePropagation();

			apple_pay.CreateSession();
			apple_pay.CancelOrder()
			apple_pay.PaymentCompleted();

			//loading layer
			jQuery('.woocommerce').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } })

			jQuery.post(
				apple_pay_params.ajax_url_place_order_with_dummy_data

			).done(function(results){
				if(results.payment_data.result === 'success'){
					apple_pay_params.total = results.total

					//TODO: error handling here
					apple_pay.OrderPaymentCreated(results);

				} else {
					return false;

				}

			}).fail( function() {
				return false;

			})

		},
		OrderPaymentCreated: function (response) {
			if ('success' !== response.payment_data.result) {
				var error_messages = response.messages || ''

				return error_messages;
			}

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
				'shippingMethods': apple_pay_params.carriers,
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': apple_pay_params.apple_pay_domain
				})),
				'requiredBillingContactFields' : [
					'postalAddress',
					'name',
				],
				'requiredShippingContactFields' : [
					"postalAddress",
					"name",
					"phone",
					"email"
				],
			}

			session = new ApplePaySession(3, request);

		},
		BeginSession: function (response) {
			session.payment_id = response.payment_data.payment_id
			session.order_id = response.order_id
			session.cancel_url = response.payment_data.cancel_url
			session.return_url = response.payment_data.return_url

			apple_pay.MerchantValidated(session, response.payment_data.merchant_session);

				session.onshippingmethodselected = event => {

					const shippingMethod = event.shippingMethod;
					session.shippingMethod = shippingMethod.identifier;

					const baseTotal = apple_pay_params.total/100 ;
					let currentShippingCost = shippingMethod.amount;

					const newTotalAmount = parseFloat(baseTotal) + parseFloat(currentShippingCost);
					session.amount = newTotalAmount * 100;

					const update = {
						newTotal: {
							label: 'Total',
							amount: newTotalAmount
						},
						newLineItems: [
							{
								label: shippingMethod.label,
								type: 'final',
								amount: session.amount
							}
						]
					};

					session.completeShippingMethodSelection(update);
				};
			session.begin();
		},
		MerchantValidated: function(session, merchant_session) {

			session.onvalidatemerchant = event => {
				try {
					session.completeMerchantValidation(merchant_session);
				} catch (err) {
					alert(err)
				}
			}
		},
		PaymentCompleted: function () {
			session.onpaymentauthorized = event => {
				let data = event.payment;

				jQuery.post({
					'url' : apple_pay_params.ajax_url_update_applepay_order,
					'data' : {
						'order_id' : session.order_id,
						'shipping' : data.shippingContact,
						'billing' : data.billingContact,
						'shipping_method' : session.shippingMethod
					}
				}).done(function (response) {

					jQuery.ajax({
						url: apple_pay_params.ajax_url_update_applepay_payment,
						type: 'post',
						data: {
							'action': 'applepay_update_payment',
							'post_type': 'POST',
							'payment_id': session.payment_id,
							'payment_token': event.payment.token,
							'order_id': session.order_id,
							'amount': session.amount
						},
						dataType: 'json',
						success:function(res) {
							jQuery('woocommerce').unblock();
							var apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;

							if (res.success !== true) {
								apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
							}
							session.completePayment({"status": apple_pay_Session_status})
							window.location = session.return_url
						},
						error: function(err){
							jQuery('woocommerce').unblock();
							$('apple-pay-button').removeClass("isDisabled")
						},
					})
				})
				$('apple-pay-button').addClass("isDisabled")

			}
		},
		CancelOrder: function () {
			session.oncancel = event => {
				$('apple-pay-button').addClass("isDisabled")
				window.location = session.cancel_url
			}
		}
	}

	$apple_pay_button.on("click", apple_pay.init());

})(jQuery)




