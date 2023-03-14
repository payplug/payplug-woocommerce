/* global window, payplug_integrated_payment_params */

const PAYPLUG_DOMAIN = "https://secure.payplug.com";

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
	init: function(){
		this.manageSaveCard(IntegratedPayment.props);

		if( !IntegratedPayment.checkLoaded() ){
			// Create an instance of Integrated Payments
			IntegratedPayment.props.api = new Payplug.IntegratedPayment(false);

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
		jQuery('form.woocommerce-checkout').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
		e.stopImmediatePropagation();
		e.preventDefault();
		//validate the form before create payment/submit payment

		IntegratedPayment.props.api.onValidateForm(({isFormValid}) => {
			if (isFormValid) {
				IntegratedPayment.getPayment();
			} else {
				jQuery('form.woocommerce-checkout').unblock();
			}

		});
		IntegratedPayment.props.api.validateForm();

		return;

	},
	getPayment: function(){
		$data = getFormData(jQuery('form.woocommerce-checkout'));
		$data.ajax = 1;
		$data.createIP = 1;
		$data._wpnonce = payplug_integrated_payment_params.nonce;

		jQuery.ajax({
			type: 'POST',
			async: false,
			url: payplug_integrated_payment_params.ajax_url, //NEED TO HAVE AN ENDPOINT FOR THIS,
			dataType: 'json',
			data: $data,
			error: function (jqXHR, textStatus, errorThrown) {
				//integrated.form.clearIntPayment();
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			},
			success: function (result) {
				if (result.success && result.data.payment_id) {
					IntegratedPayment.props.paymentId = result.data.payment_id;
					IntegratedPayment.props.return_url = result.data.return_url;
					IntegratedPayment.SubmitPayment();
				} else {
					alert("NOT CREATED")
				}
			},
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
	SubmitPayment: function(){
		try {
			IntegratedPayment.props.api.pay(IntegratedPayment.props.paymentId, Payplug.Scheme.AUTO, {save_card: false});
		} catch(error) {
			console.log(error);
		}
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

/*	IntegratedPayment.props.pan.onChange(function(err) {
		if (err.error) {
			document.querySelector(".payplug.IntegratedPayment_error.-pan").classList.remove("-hide");
			document.querySelector('.pan-input-container').classList.add("-invalid");

			if (err.error.name === "FIELD_EMPTY") {
				document.querySelector(".payplug.IntegratedPayment_error.-pan").querySelector(".emptyField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-pan").querySelector(".invalidField").classList.add("-hide");
			} else {
				document.querySelector(".payplug.IntegratedPayment_error.-pan").querySelector(".invalidField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-pan").querySelector(".emptyField").classList.add("-hide");
			}
		} else {
			document.querySelector(".payplug.IntegratedPayment_error.-pan").classList.add("-hide");
			document.querySelector('.pan-input-container').classList.remove("-invalid");
			document.querySelector(".payplug.IntegratedPayment_error.-pan").querySelector(".invalidField").classList.add("-hide");
			document.querySelector(".payplug.IntegratedPayment_error.-pan").querySelector(".emptyField").classList.add("-hide");
			IntegratedPayment.props.fieldsValid.pan = true;
			IntegratedPayment.props.fieldsEmpty.pan = false;
		}
	});

	IntegratedPayment.props.cvv.onChange(function(err) {
		if (err.error) {
			document.querySelector(".payplug.IntegratedPayment_error.-cvv").classList.remove("-hide");
			document.querySelector('.cvv-input-container').classList.add("-invalid");

			if (err.error.name === "FIELD_EMPTY") {
				document.querySelector(".payplug.IntegratedPayment_error.-cvv").querySelector(".emptyField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-cvv").querySelector(".invalidField").classList.add("-hide");
			} else {
				document.querySelector(".payplug.IntegratedPayment_error.-cvv").querySelector(".emptyField").classList.add("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-cvv").querySelector(".invalidField").classList.remove("-hide");
			}
		} else {
			document.querySelector(".payplug.IntegratedPayment_error.-cvv").classList.add("-hide");
			document.querySelector('.cvv-input-container').classList.remove("-invalid");
			document.querySelector(".payplug.IntegratedPayment_error.-cvv").querySelector(".invalidField").classList.add("-hide");
			document.querySelector(".payplug.IntegratedPayment_error.-cvv").querySelector(".emptyField").classList.add("-hide");
			IntegratedPayment.props.fieldsValid.cvv = true;
			IntegratedPayment.props.fieldsEmpty.cvv = false;
		}
	});

	IntegratedPayment.props.cardHolder.onChange(function(err) {
		if (err.error) {
			document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").classList.remove("-hide");
			document.querySelector('.cardholder-input-container').classList.add("-invalid");

			if (err.error.name === "FIELD_EMPTY") {
				document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").querySelector(".emptyField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").querySelector(".invalidField").classList.add("-hide");
			} else {
				document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").querySelector(".invalidField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").querySelector(".emptyField").classList.add("-hide");
			}
		} else {
			document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").classList.add("-hide");
			document.querySelector('.cardholder-input-container').classList.remove("-invalid");
			document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").querySelector(".invalidField").classList.add("-hide");
			document.querySelector(".payplug.IntegratedPayment_error.-cardHolder").querySelector(".emptyField").classList.add("-hide");
			IntegratedPayment.props.fieldsValid.cardHolder = true;
			IntegratedPayment.props.fieldsEmpty.cardHolder = false;
		}
	});

	IntegratedPayment.props.exp.onChange(function(err) {
		if (err.error) {
			document.querySelector(".payplug.IntegratedPayment_error.-exp").classList.remove("-hide");
			document.querySelector('.exp-input-container').classList.add("-invalid");

			if (err.error.name === "FIELD_EMPTY") {
				document.querySelector(".payplug.IntegratedPayment_error.-exp").querySelector(".emptyField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-exp").querySelector(".invalidField").classList.add("-hide");
			} else {
				document.querySelector(".payplug.IntegratedPayment_error.-exp").querySelector(".invalidField").classList.remove("-hide");
				document.querySelector(".payplug.IntegratedPayment_error.-exp").querySelector(".emptyField").classList.add("-hide");
			}
		} else {
			document.querySelector(".payplug.IntegratedPayment_error.-exp").classList.add("-hide");
			document.querySelector('.exp-input-container').classList.remove("-invalid");
			document.querySelector(".payplug.IntegratedPayment_error.-exp").querySelector(".invalidField").classList.add("-hide");
			document.querySelector(".payplug.IntegratedPayment_error.-exp").querySelector(".emptyField").classList.add("-hide");
			IntegratedPayment.props.fieldsValid.exp = true;
			IntegratedPayment.props.fieldsEmpty.exp = false;
		}
	});*/

	IntegratedPayment.props.api.onCompleted(function (event) {
		//TODO:: ADD VALIDATION ABOUT THE PAYMENT WAS VALID OR NOT! AND REDIRECT TO THE RIGHT PAGE
		window.location.href = IntegratedPayment.props.return_url;
	})
});

(function ($) {

	$("body").attr("payplug-domain", payplug_integrated_payment_params.secureDomain);
	//on submit event
	$('form.woocommerce-checkout').on('submit', IntegratedPayment.onSubmit);

})(jQuery);
