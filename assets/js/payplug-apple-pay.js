/* global window, apple_pay_params */
(function ($) {
	var session = null;
	var apple_pay = {
		init: function () {
			$('body').on( 'init_checkout update_checkout updated_checkout payment_method_selected', function () {
				var payment_method = $('input[name="payment_method"]:checked').attr("value")
				apple_pay.ShowHideSubmitButton(payment_method)
			})
			this.$apple_pay_button = $('apple-pay-button')
			this.$apple_pay_button.on(
				'click',
				apple_pay.ProcessCheckout
			)
		},
		ShowHideSubmitButton: function (payment_method) {
			if (payment_method == 'apple_pay')
				$('*[name=woocommerce_checkout_place_order]').hide()
			else
				$('*[name=woocommerce_checkout_place_order]').show();
		},
		ProcessCheckout: function (e) {
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
			e.preventDefault();
			e.stopImmediatePropagation();
			$('form.woocommerce-checkout').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
			$.post(
				apple_pay_params.ajax_url_payplug_create_order,
				$('form.woocommerce-checkout').serialize()
			).done(apple_pay.OrdernPaymentCreated)
		},
		OrdernPaymentCreated: function (response) {
			console.clear()
			console.log(response)
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
		BeginSession: function (response) {
			session.payment_id = response.payment_id
			session.order_id = response.order_id
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
	$(document).ready(function () {
		apple_pay.init()
	})
})(jQuery);
