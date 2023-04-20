/* global window, payplug_integrated_payment_params */
const PAYPLUG_DOMAIN = "https://secure-qa.payplug.com";

var IntegratedPayment = {
	props: {
		cartId: null,
		paymentId: null,
		paymentOptionId: null,
		form: {},
		checkoutForm: null,
		api: null,
		integratedPayment: null,
		token: null,
		notValid: true,
		fieldsValid: {
			cardHolder: false,
			pan: false,
			cvv: false,
			exp: false,
		},
		fieldsEmpty: {
			cardHolder: true,
			pan: true,
			cvv: true,
			exp: true,
		},
		inputStyle:{
			default: {
				color: '#2B343D',
				fontFamily: 'Poppins, sans-serif',
				fontSize: '14px',
				textAlign: 'left',
				'::placeholder': {
					color: '#969a9f',
				},
				':focus': {
					color: '#2B343D',
				}
			},
			invalid: {
				color: '#E91932'
			}
		},
		save_card: false,
		scheme: null,
		query: null,
		submit: null,
		order_review: false,
		return_url: null
	},
	form: function(){
		if(jQuery('form.woocommerce-checkout').length){
			return jQuery('form.woocommerce-checkout');
		}

		if(jQuery('form#order_review').length){
			IntegratedPayment.props.order_review = true;
			return jQuery('form#order_review');
		}

	},
	init: function(){
		this.manageSaveCard(IntegratedPayment.props);

		if( !IntegratedPayment.checkLoaded() ){
			// Create an instance of Integrated Payments
			IntegratedPayment.props.api = new Payplug.IntegratedPayment(payplug_integrated_payment_params.mode == 1 ? false : true);
			IntegratedPayment.props.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX)

			// Add each payments fields
			IntegratedPayment.props.form.cardHolder = IntegratedPayment.props.api.cardHolder(
				document.querySelector('.cardHolder-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.cardholder } );
			IntegratedPayment.props.form.pan = IntegratedPayment.props.api.cardNumber(
				document.querySelector('.pan-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.card_number } );
			IntegratedPayment.props.form.cvv = IntegratedPayment.props.api.cvv(
				document.querySelector('.cvv-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.cvv } );
			// With one field for expiration date
			IntegratedPayment.props.form.exp = IntegratedPayment.props.api.expiration(
				document.querySelector('.exp-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.expiration_date } );

			IntegratedPayment.props.scheme = IntegratedPayment.props.api.getSupportedSchemes();

			//EVENTS TO VALIDATE
			IntegratedPayment.props.api.onValidateForm(({isFormValid}) => {

				if(IntegratedPayment.oneClickSelected()){
					IntegratedPayment.resetIntegratedForm();
					IntegratedPayment.getPayment();

				}else if (isFormValid) {
					IntegratedPayment.getPayment();

				} else {
					IntegratedPayment.form().unblock();
				}

			});

			//event to hide show form if token is already done
			jQuery("[name=wc-payplug-payment-token]").on('change', function(event){
				if( jQuery(this).val() === "new" ){
					jQuery(".payplug.IntegratedPayment").show()
				}else{
					jQuery(".payplug.IntegratedPayment").hide()
				}
			});


			IntegratedPayment.props.api.onCompleted(function (event) {
				jQuery.post({
					async: false,
					url: payplug_integrated_payment_params.check_payment_url, //NEED TO HAVE AN ENDPOINT FOR THIS,
					data: {'payment_id' : event.token},
					error: function (jqXHR, textStatus, errorThrown) {
						console.log(jqXHR);
						console.log(textStatus);
						console.log(errorThrown);
					},
					success: function (response) {
						if (response.success === false) {
							IntegratedPayment.form().unblock();
							var error_messages = response.data.message || '';
							IntegratedPayment.submit_error(error_messages);
							jQuery(".payplug.IntegratedPayment_error.-payment").show();
							IntegratedPayment.resetIntegratedForm();
							return;
						} else {
							jQuery(".payplug.IntegratedPayment_error.-payment").hide();
							window.location.href = IntegratedPayment.props.return_url;
						}

					}
				});

			});

		}
	},
	checkLoaded: function(){
		return jQuery("iframe#cardholder").length ? true : false;
	},
	manageSaveCard: function(Integrated){
		$saveCard = jQuery('.payplug.IntegratedPayment .-saveCard');
		$saveCard.find('input').on('change', function () {
			if (jQuery(this).prop('checked')) {
				Integrated.save_card = true;
				$saveCard.addClass('-checked');
			} else {
				Integrated.save_card = false;
				$saveCard.removeClass('-checked');
			}
		});
	},
	onSubmit: function(e){
		if ( jQuery('#payment_method_payplug').is(':checked') ) {
			e.stopImmediatePropagation();
			e.preventDefault();
			//validate the form before create payment/submit payment
			IntegratedPayment.props.api.validateForm();
			return;
		}

	},
	getPayment: function(){

		request_url = payplug_integrated_payment_params.ajax_url;
		if(IntegratedPayment.props.order_review) {
			request_url = payplug_integrated_payment_params.order_review_url;
		}

		jQuery.ajax({
			type: 'POST',
			url: request_url, //NEED TO HAVE AN ENDPOINT FOR THIS,
			dataType: 'json',
			data: IntegratedPayment.form().serialize(),
			error: function (jqXHR, textStatus, errorThrown) {
				//integrated.form.clearIntPayment();
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			},
			success: function (response) {
				if (response.result === "failure") {
					IntegratedPayment.form().unblock();
					var error_messages = response.messages || '';
					IntegratedPayment.submit_error(error_messages);
					return;
				}

				if(response.is_paid || IntegratedPayment.oneClickSelected()) {
					document.location.href = response.redirect;
					return;
				}

				IntegratedPayment.props.paymentId = response.payment_id;
				IntegratedPayment.props.return_url = response.redirect;
			},
			complete: function(){
				if(IntegratedPayment.oneClickSelected()){
					return;

				}else{
					IntegratedPayment.SubmitPayment();

				}
			}
		});
	},
	submit_error: function (error_message) {
		var parsedHtml = jQuery.parseHTML(error_message, document, false);
		jQuery('#woocommerce-NoticeGroup').remove();
		jQuery('<div></div>')
			.addClass('woocommerce-error')
			.attr('id', 'woocommerce-NoticeGroup')
			.html(parsedHtml)
			.prependTo(IntegratedPayment.form());
		IntegratedPayment.form().unblock();
		IntegratedPayment.scroll_to_notices();
	},
	scroll_to_notices: function () {
		var scrollElement = jQuery('#woocommerce-NoticeGroup');
		jQuery('html, body').animate({
			scrollTop: (scrollElement.offset().top - 100)
		}, 500);
	},
	oneClickSelected: function () {
		var token = jQuery('input[name=wc-payplug-payment-token]:checked');
		return token.length > 0 && 'new' !== token.val();
	},
	SubmitPayment: function(){
		try {
			IntegratedPayment.props.api.pay(IntegratedPayment.props.paymentId, Payplug.Scheme.AUTO, {save_card: IntegratedPayment.props.save_card});
		} catch(error) {
			IntegratedPayment.submit_error(error.message);
			jQuery(".payplug.IntegratedPayment_error.-payment").show();
		}
	},
	resetIntegratedForm: function(){
		IntegratedPayment.props.form.cardHolder.clear();
		IntegratedPayment.props.form.pan.clear();
		IntegratedPayment.props.form.cvv.clear();
		IntegratedPayment.props.form.exp.clear();

		if(IntegratedPayment.props.save_card){
			jQuery('.payplug.IntegratedPayment .-saveCard').find('input').click();
		}

	},
	showValidationErrorMessages: function(){
		jQuery.each(IntegratedPayment.props.form, function (key, field) {
			field.onChange(function(err) {
				if (err.error) {
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).classList.remove("-hide");
					document.querySelector('.'+key+'-input-container').classList.add("-invalid");

					if (err.error.name === "FIELD_EMPTY") {
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".emptyField").classList.remove("-hide");
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".invalidField").classList.add("-hide");
					} else {
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".invalidField").classList.remove("-hide");
						document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".emptyField").classList.add("-hide");
					}
				} else {
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).classList.add("-hide");
					document.querySelector('.'+key+'-input-container').classList.remove("-invalid");
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".invalidField").classList.add("-hide");
					document.querySelector(".payplug.IntegratedPayment_error.-"+key).querySelector(".emptyField").classList.add("-hide");
					IntegratedPayment.props.fieldsValid[key] = true;
					IntegratedPayment.props.fieldsEmpty[key] = false;
				}
			});
		});
	}
};

jQuery( 'body' ).on( 'updated_checkout', function() {
	IntegratedPayment.init();
	IntegratedPayment.showValidationErrorMessages();
});

(function ($) {
	$("body").attr("payplug-domain", payplug_integrated_payment_params.secureDomain);

	if(!jQuery('.cardHolder-input-container').length){
		return;
	}

	IntegratedPayment.init();
	//on submit event
	$('form.woocommerce-checkout, form#order_review').on('submit', function(event){
		IntegratedPayment.form().block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
		IntegratedPayment.onSubmit(event);
	});

	IntegratedPayment.showValidationErrorMessages();

})(jQuery);
