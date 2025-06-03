/* global window, apple_pay_params */
(function($){

	var $apple_pay_button = $('apple-pay-button')
	var session = null;
	var is_cart = apple_pay_params.is_cart;
	var apple_pay = {
		load_order_total: false,
		init: function () {

			if(!is_cart){
				return;
			}

			apple_pay.updateOrderTotal();
			$('apple-pay-button').on('click', apple_pay.ProcessCheckout)
		},
		updateOrderTotal: function(){
			jQuery.post(
				apple_pay_params.ajax_url_applepay_get_order_totals

			).done(function(results){
				if(results.success){
					apple_pay_params.total = results.data;

				} else {
					$apple_pay_button.remove();
				}

			}).done(function(){
				apple_pay.getShippings();

			}).fail( function() {
				$apple_pay_button.remove();

			});
		},
		getShippings: function(){
			jQuery.post(
				apple_pay_params.ajax_url_applepay_get_shippings

			).done(function(results){

				if(results.data.length === 0){
					//$apple_pay_button.remove();
					apple_pay_params.carriers = [];
					return;
				}

				selected_shipping = jQuery('[name^="shipping_method"]:checked').val();

				//if there's only 1 shipping method available there is no radio
				if(typeof selected_shipping === "undefined" ){
					if( jQuery("ul#shipping_method li").length === 1 ){
						selected_shipping = jQuery('[name^="shipping_method"]').val();
					}
				}

				if(typeof selected_shipping === "undefined" ){
					apple_pay_params.carriers = [];
					return;
				}

				selected_shipping = selected_shipping.split(":");

				results.data.map(function(v){
					if(v.identifier === selected_shipping[0]){
						v.selected = true;
					}
				});
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
				if(results.success === false){
					apple_pay.showError(results.data.msg, "error");
					apple_pay.handle_process_error(results.data.order_id);
					return;
				}

				apple_pay_params.total = results.total
				apple_pay.OrderPaymentCreated(results);
			});

		},
		OrderPaymentCreated: function (response) {
			if ('success' !== response.payment_data.result) {
				var error_messages = response.messages || ''
				apple_pay.CancelOrder(error_messages);
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
					"cartesBancaires",
					"visa",
					"masterCard"
				],
            	"supportedTypes": [
					"debit",
					"credit"
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

			session = new ApplePaySession(4, request);

		},
		BeginSession: function (response) {
			session.payment_id = response.payment_data.payment_id
			session.order_id = response.order_id
			session.cancel_url = response.payment_data.cancel_url
			session.return_url = response.payment_data.return_url

			apple_pay.MerchantValidated(session, response.payment_data.merchant_session);

			session.amount = parseFloat(apple_pay_params.total/100) * 100;
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
							amount: currentShippingCost
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
					apple_pay.CancelOrder();
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
								apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.')
							}
							session.completePayment({"status": apple_pay_Session_status})
							window.location = session.return_url
						},
						error: function(err){
							apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.')
						},
					});

				}).fail(function (response) {
					apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.');
				})
				$('apple-pay-button').addClass("isDisabled")

			}
		},
		CancelOrder: function (message) {
			session.oncancel = event => {
				$('apple-pay-button').removeClass("isDisabled")
				apple_pay.cancel_order_request(session.order_id, session.payment_id);

			}
		},
		handle_process_error: function (order_id = null) {
			$('apple-pay-button').removeClass("isDisabled")
			apple_pay.cancel_order_request(order_id, null);
		},
		cancel_order_request: function(order_id, payment_id = null){
			jQuery.post({
				url : apple_pay_params.ajax_url_applepay_cancel_order,
				data : {
					'order_id' : order_id,
					'payment_id' : payment_id
				}
			}).done(function (response) {
				apple_pay.showError(response.data.message, "info");
				apple_pay.updateOrderTotal();
			})
		},
		showError: function (message="", type = "info") {
			jQuery('.woocommerce').unblock()
			let notices = jQuery('.woocommerce-notices-wrapper')
			jQuery('<div id="apple-pay-cart-notice"></div>')
				.addClass('woocommerce-'+type)
				.html(message)
				.prependTo(notices)
			jQuery('html , body').animate({
				scrollTop: (notices.offset().top - 100)
			}, 500)
			jQuery('.woocommerce-notices-wrapper').fadeIn();
			setTimeout(function() {
				notices.fadeOut('slow', function() {
					jQuery('.woocommerce-notices-wrapper #apple-pay-cart-notice').remove();
				});
			}, 5000);
		}
	}

	$apple_pay_button.on("click", apple_pay.init());

	jQuery( 'body' ).on( 'updated_cart_totals', function() {
		apple_pay.updateOrderTotal()
		$apple_pay_button.on("click", apple_pay.init());
	})

})(jQuery)




