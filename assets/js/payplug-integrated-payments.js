/* global window, payplug_integrated_payment_params */
jQuery( 'body' ).on( 'updated_checkout', function() {

	IntegratedPayment = {
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
			document.querySelector('.payment_box.payment_method_payplug br').remove()

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
		}
	}

	PayplugIntegrated = {};

	//TODO:: remove this method once development is done
	PayplugIntegrated.showErrors = function(){
		elems = document.querySelectorAll(".IntegratedPayment_error span");
		elems.forEach(function (elem, index) {
			if(elem.classList.contains('-hide')){
				elem.classList.remove('-hide');
				elem.classList.add("-show");
			}else{
				elem.classList.remove('-show');
				elem.classList.add("-hide");
			}
		});
		elems = document.querySelectorAll(".IntegratedPayment_error");
		elems.forEach(function (elem, index) {
			if(elem.classList.contains('-hide')){
				elem.classList.remove('-hide');
				elem.classList.add("-show");
			}else{
				elem.classList.remove('-show');
				elem.classList.add("-hide");
			}
		});
	}

	IntegratedPayment.init();
});


