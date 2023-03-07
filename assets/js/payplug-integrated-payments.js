/* global window, payplug_integrated_payment_params */
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
		notValid: false,
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
	},
	init: function(){
		const Integrated = IntegratedPayment.props;
		document.querySelector('.payment_box.payment_method_payplug br').remove();

		this.manageSaveCard(Integrated);

		if( !IntegratedPayment.checkLoaded() ){
			// Create an instance of Integrated Payments
			Integrated.api = new Payplug.IntegratedPayment(false);

			// Add each payments fields
			Integrated.api.cardHolder(
				document.querySelector('.cardholder-input-container'),
				{default: Integrated.inputStyle.default, placeholder:payplug_integrated_payment_params.cardholder } );
			Integrated.api.cardNumber(
				document.querySelector('.pan-input-container'),
				{default: Integrated.inputStyle.default, placeholder:payplug_integrated_payment_params.card_number } );
			Integrated.api.cvv(
				document.querySelector('.cvv-input-container'),
				{default: Integrated.inputStyle.default, placeholder:payplug_integrated_payment_params.cvv } );
			// With one field for expiration date
			Integrated.api.expiration(
				document.querySelector('.exp-input-container'),
				{default: Integrated.inputStyle.default, placeholder:payplug_integrated_payment_params.expiration_date } );
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

		var res = IntegratedPayment.getPayment();

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
				/*if (result && result.payment_id) {
					integrated.props.paymentId = result.payment_id;
					integrated.props.cart_id = result.cart_id;
					integrated.form.submitIntPayment();
				} else {
					window[module_name+'Module'].popup.set(integratedPaymentError);
					integrated.form.clearIntPayment();
					return false;
				}*/
				console.log(result);
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
	}
};

jQuery( 'body' ).on( 'updated_checkout', function() {
	IntegratedPayment.init();
});

(function ($) {

	//on submit event
	$('form.woocommerce-checkout').on('submit', IntegratedPayment.onSubmit);

})(jQuery);
