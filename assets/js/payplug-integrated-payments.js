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
		return_url: null
	},
	form: jQuery('form.woocommerce-checkout'),
	init: function(){
		this.manageSaveCard(IntegratedPayment.props);

		if( !IntegratedPayment.checkLoaded() ){
			// Create an instance of Integrated Payments
			IntegratedPayment.props.api = new Payplug.IntegratedPayment(false);
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
		if (jQuery('form.woocommerce-checkout input[name="payment_method"]:checked').val() === "payplug") {
			e.stopImmediatePropagation();
			e.preventDefault();
			//validate the form before create payment/submit payment
			IntegratedPayment.props.api.validateForm();
			return;
		}

	},
	getPayment: function(){
		$data = getFormData(jQuery('form.woocommerce-checkout'));
		$data.ajax = 1;
		$data.createIP = 1;
		$data._wpnonce = payplug_integrated_payment_params.nonce;

		jQuery.ajax({
			type: 'POST',
			url: payplug_integrated_payment_params.ajax_url, //NEED TO HAVE AN ENDPOINT FOR THIS,
			dataType: 'json',
			data: $data,
			error: function (jqXHR, textStatus, errorThrown) {
				//integrated.form.clearIntPayment();
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			},
			success: function (response) {
				if (response.result === "failure") {
					IntegratedPayment.form.unblock();
					var error_messages = response.messages || '';
					IntegratedPayment.submit_error(error_messages);
					return;
				}

				IntegratedPayment.props.paymentId = response.payment_id;
				IntegratedPayment.props.return_url = response.redirect;
			},
			complete: function(){
				IntegratedPayment.SubmitPayment();
			}
		});

		function getFormData($form){
			var unindexed_array = $form.serializeArray();
			var indexed_array = {};

			jQuery.map(unindexed_array, function(n, i){
				indexed_array[n['name']] = n['value'];
			});

			return indexed_array;
		}
	},
	submit_error: function (error_message) {
		var parsedHtml = jQuery.parseHTML(error_message, document, false);
		jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
		jQuery('<div></div>')
			.addClass('woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-error')
			.html(parsedHtml)
			.prependTo(IntegratedPayment.form);
		IntegratedPayment.form.removeClass('processing').unblock();
		IntegratedPayment.form.find('.input-text, select, input:checkbox').trigger('validate').blur();
		IntegratedPayment.scroll_to_notices();
		jQuery(document.body).trigger('checkout_error');
	},
	scroll_to_notices: function () {
		var scrollElement = jQuery('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
		if (!scrollElement.length) {
			scrollElement = jQuery('.form.checkout');
		}
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
			console.log(error);
		}
	},
	resetIntegratedForm: function(){
		IntegratedPayment.props.form.cardHolder.clear();
		IntegratedPayment.props.form.pan.clear();
		IntegratedPayment.props.form.cvv.clear();
		IntegratedPayment.props.form.exp.clear();
	}
};

jQuery( 'body' ).on( 'updated_checkout', function() {
	IntegratedPayment.init();

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
					IntegratedPayment.form.unblock();
					var error_messages = response.data.message || '';
					IntegratedPayment.submit_error(error_messages);
					jQuery(".payplug.IntegratedPayment_error.-payment").show();
					return;
				} else {
					jQuery(".payplug.IntegratedPayment_error.-payment").hide();
					window.location.href = IntegratedPayment.props.return_url;
				}

			}
		});

	});

	//CALLING THE EVENT
	IntegratedPayment.props.api.onValidateForm(({isFormValid}) => {

		if(IntegratedPayment.oneClickSelected()){
			IntegratedPayment.resetIntegratedForm();
			IntegratedPayment.getPayment();

		}else if (isFormValid) {
			IntegratedPayment.getPayment();

		} else {
			jQuery('form.woocommerce-checkout').unblock();
		}
	});
});

(function ($) {
	$("body").attr("payplug-domain", payplug_integrated_payment_params.secureDomain);
	//on submit event
	$('form.woocommerce-checkout').on('submit', function(event){
		console.log("-> pim");
		IntegratedPayment.form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
		console.log("-> pam");
		IntegratedPayment.onSubmit(event);
	});

	//$(document).ajaxStart(jQuery.blockUI({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } })).ajaxStop($.unblockUI);

})(jQuery);
