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
			e.preventDefault()
			e.stopImmediatePropagation()
			apple_pay.CreateSession()
			apple_pay.CancelOrder()
			apple_pay.PaymentCompleted()
			$('form.woocommerce-checkout').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } })
			$.post(
				apple_pay_params.ajax_url_payplug_create_order,
				$('form.woocommerce-checkout').serialize()
			).done(apple_pay.OrdernPaymentCreated)
		},
		OrdernPaymentCreated: function (response) {
			$('form.woocommerce-checkout').unblock()
			if ('success' !== response.result) {
				var error_messages = response.messages || ''
				apple_pay.SubmitError(error_messages)
				return;
			}
			$('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove()
			apple_pay.BeginSession(response)
		},
		SubmitError: function (error_message) {
			var parsedHtml = $.parseHTML(error_message, document, false);
			$('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove()
			$('<div></div>')
				.addClass('woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout')
				.html(parsedHtml)
				.prependTo($('form.woocommerce-checkout'))
			$('form.woocommerce-checkout').removeClass('processing').unblock()
			$('form.woocommerce-checkout').find('.input-text, select, input:checkbox').trigger('validate').blur()
			apple_pay.ScrollToNotices()
			$(document.body).trigger('checkout_error')
		},
		ScrollToNotices: function () {
			var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout')
			if (!scrollElement.length) {
				scrollElement = $('.form.checkout')
			}
			$('html, body').animate({
				scrollTop: (scrollElement.offset().top - 100)
			}, 500)
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
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': apple_pay_params.apple_pay_domain
				}))
			}
			session = new ApplePaySession(3, request)
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
		},
		CancelOrder: function () {
			session.oncancel = event => {
				$('apple-pay-button').addClass("isDisabled")
				window.location = session.cancel_url
			}
		},
		PaymentCompleted: function () {
			session.onpaymentauthorized = event => {
				$('apple-pay-button').addClass("isDisabled")
				jQuery.ajax({
					url: apple_pay_params.ajax_url_applepay_update_payment,
					type: 'post',
					data: {'action': 'applepay_update_payment', 'post_type': 'POST',
						'payment_id': session.payment_id, 'payment_token': event.payment.token, 'order_id': session.order_id},
					dataType: 'json',
					success:function(res) {
						var apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;
						if (res.data.result !== true) {
							apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
						}
						session.completePayment({"status": apple_pay_Session_status})
						window.location = session.return_url
					},
					error: function(err){
						$('apple-pay-button').removeClass("isDisabled")
					},
				})
			}
		},

		getOrderTotals(){
			if(!apple_pay.load_order_total){
				apple_pay.load_order_total = true;
				return false;
			}
			 $('apple-pay-button').addClass("isDisabled")

			jQuery.post(
				apple_pay_params.ajax_url_applepay_get_order_totals
			).done(function(results){
				if(results.success){
					apple_pay_params.total = results.data;
				}

				$('apple-pay-button').removeClass("isDisabled")
			})
		}
	}

	var applePaycontroller = function(){
		if(jQuery("[name=payment_method]:checked").val() === "apple_pay"){
			jQuery("[name=woocommerce_checkout_place_order]").prop("disabled", true);

			//enable buttons
			apple_pay.init();
		}else{
			jQuery("[name=woocommerce_checkout_place_order]").prop("disabled", false);
		}
	}

	jQuery(document).on("change click", "[name=payment_method]", applePaycontroller);

	$( document ).ajaxComplete(function() {
		applePaycontroller();
	});

	jQuery("[name=payment_method]").prop("checked", false);

	//GET ORDER TOTALS ON SHIPPING METHOD SELECTION
	jQuery( 'body' ).on( 'updated_checkout', function() {
		apple_pay.getOrderTotals();
	})
})(jQuery)




