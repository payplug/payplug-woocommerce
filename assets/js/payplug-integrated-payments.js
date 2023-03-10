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
		document.querySelector('.payment_box.payment_method_payplug br').remove();
		this.manageSaveCard(IntegratedPayment.props);

		if( !IntegratedPayment.checkLoaded() ){
			// Create an instance of Integrated Payments
			IntegratedPayment.props.api = new Payplug.IntegratedPayment(false);

			// Add each payments fields
			IntegratedPayment.props.api.cardHolder(
				document.querySelector('.cardholder-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.cardholder } );
			IntegratedPayment.props.api.cardNumber(
				document.querySelector('.pan-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.card_number } );
			IntegratedPayment.props.api.cvv(
				document.querySelector('.cvv-input-container'),
				{default: IntegratedPayment.props.inputStyle.default, placeholder:payplug_integrated_payment_params.cvv } );
			// With one field for expiration date
			IntegratedPayment.props.api.expiration(
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
		IntegratedPayment.getPayment();

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
