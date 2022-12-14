/* global window, payplug_checkout_params */
(function ($) {
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
        },
        onSubmit: function (e) {
            if (!payplug_checkout.isPayplugChosen()) {
                return;
            }
            // Use standard checkout process if a payment token has been
            // choose by a user.
            if (payplug_checkout.isPaymentTokenSelected()) {
                payplug_checkout.$form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
                //Prevent submit and stop all other listeners from being triggered.
                e.preventDefault();
                e.stopImmediatePropagation();
                $.post(
                    payplug_checkout_params.ajax_url,
                    payplug_checkout.$form.serialize()
                ).done(function (response) {
                    if (response.result === "failure") {
                        payplug_checkout.$form.unblock();
                        var error_messages = response.messages || '';
                        payplug_checkout.submit_error(error_messages);
                        return;
                    } 
                    
                    if(response.is_paid) {
                        document.location.href = response.redirect;
                        return;
                    }
                    Payplug.showPayment(response.redirect, true);
                });
                return;
            }
            if (!payplug_checkout_params.is_embedded) {
                return
            }
            //Prevent submit and stop all other listeners from being triggered.
            e.preventDefault();
            e.stopImmediatePropagation();
            payplug_checkout.$form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
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
            // Set the cancel URL use by PayPlug js when the user close the embedded payment form.
            window.redirection_url = response.cancel || false;
            Payplug.showPayment(response.redirect);
        },
        isPayplugChosen: function () {
            return $('#payment_method_payplug').is(':checked');
        },
        isPaymentTokenSelected: function () {
            var token = $('input[name=wc-payplug-payment-token]:checked');
            return token.length > 0 && 'new' !== token.val();
        },
        submit_error: function (error_message) {
            var parsedHtml = $.parseHTML(error_message, document, false);
            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
            $('<div></div>')
                .addClass('woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout')
                .html(parsedHtml)
                .prependTo(payplug_checkout.$form);
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
