/* global payplug_checkout_params */

(function ($) {

	/**
	 * For now we need to redefine the `_closeIframe` from the Payplug object
	 * to trigger an event when it run. This allow us to redirect the user to
	 * the cancelURL manually.
	 */
	if (typeof Payplug != 'undefined') {
		Payplug._closeIframe = function (callback) {
			var node = document.getElementById("payplug-spinner");
			if (node) {
				node.style.display = "none";
				node.parentNode.removeChild(node);
			}
			node = document.getElementById("wrapper-payplug-iframe");
			if (node) {
				this._fadeOut(node, function () {
					if (callback) {
						callback();
					}
				});
			}
			// Hard Remove iframe
			node.parentNode.removeChild(node);
			node = document.getElementById("iframe-payplug-close");
			if (node && node.parentNode) {
				node.parentNode.removeChild(node);
			}

			$(document).trigger('payplugIframeClosed');
		}
	}

	var payplug_checkout = {
		init: function () {
			if ($('form.woocommerce-checkout').length) {
				this.$form = $('form.woocommerce-checkout');
				this.$form.on(
					'submit',
					this.onSubmit
				)
			}

			if ($('form#order_review').length) {
				this.$form = $('form#order_review');
				this.$form.on(
					'submit',
					this.onSubmit
				)
			}

			$(document).on('payplugIframeClosed', this.handleClosedIframe);
		},
		onSubmit: function (e) {
			if (!payplug_checkout.isPayplugChosen()) {
				return;
			}

			// Use standard checkout process if a payment token has been
			// choose by a user.
			if (payplug_checkout.isPaymentTokenSelected()) {
				return;
			}

			//Prevent submit and stop all other listeners from being triggered.
			e.preventDefault();
			e.stopImmediatePropagation();

			payplug_checkout.$form.block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});

			$.post(
				payplug_checkout_params.ajax_url,
				payplug_checkout.$form.serialize()
			).done(payplug_checkout.openModal);
		},
		openModal: function (response) {
			payplug_checkout.$form.unblock();

			if ('success' !== response.result) {
				var error_messages = response.messages || '';
				payplug_checkout.submit_error(error_messages);
				return;
			}

			payplug_checkout.cancelUrl = response.cancel || false;
			Payplug.showPayment(response.redirect);
		},
		handleClosedIframe: function () {
			if (payplug_checkout.cancelUrl) {
				window.location.href = payplug_checkout.cancelUrl;
			}
		},
		isPayplugChosen: function () {
			return $('#payment_method_payplug').is(':checked');
		},
		isPaymentTokenSelected: function () {
			var token = $('input[name=wc-payplug-payment-token]:checked');
			return token.length > 0 && 'new' !== token.val();
		},
		submit_error: function (error_message) {
			$('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
			payplug_checkout.$form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
			payplug_checkout.$form.removeClass('processing').unblock();
			payplug_checkout.$form.find('.input-text, select, input:checkbox').trigger('validate').blur();
			payplug_checkout.scroll_to_notices();
			$(document.body).trigger('checkout_error');
		},
		scroll_to_notices: function () {
			var scrollElement = $('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');

			if (!scrollElement.length) {
				scrollElement = $('.form.checkout');
			}

			$('html, body').animate({
				scrollTop: (scrollElement.offset().top - 100)
			}, 500);
		}
	};

	payplug_checkout.init();
})(jQuery);