var inputStyle = {
	"input": {
		"font-size": "1em",
		"background-color": "transparent",
	},
	"::placeholder": {
		"font-size": "1em",
		"color": "#777",
		"font-style": "italic"
	},
	":invalid": {
		"color": "#FF0000",
		"font-size": "1em"
	}
};

var HostedFields = {
	hfields: dalenys.hostedFields({
		// API Keys
		key: {
			id: "970c4f7c-c62e-40d2-8084-b61781326c81",
			value: "lr3*{F/4?nLnTq.t"
		},
		// Manages each hosted-field container
		fields: {
			'card': {
				id: 'card-container',
				placeholder: payplug_integrated_payment_params.card_number,
				enableAutospacing: true,
				style: inputStyle,
				onInput: function (event) {
					if( typeof event['cardType'] !== "undefined" ) {
						jQuery("input[type='radio'][name='schemeOptions'][value='"+event['cardType']+"']").attr("checked", true);
					}

					HostedFields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-pan .invalidField"))
				}
			},
			'expiry': {
				id: 'expiry-container',
				placeholder: payplug_integrated_payment_params.expiration_date,
				style: inputStyle,
				onInput: function (event) {
					HostedFields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-exp .invalidField"))
				}
			},
			'cryptogram': {
				id: 'cvv-container',
				placeholder: payplug_integrated_payment_params.cvv,
				style: inputStyle,
				onInput: function (event) {
					HostedFields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-cvv .invalidField"))
				}
			}
		}
	}),
	handleInvalidFieldErrors: function(event, $element){
		if(event["type"] === "invalid"){
			$element.removeClass("-hide");
			$element.parent().removeClass("-hide");
		}

		if(event["type"] === "valid" || event["type"] === "empty"){
			$element.addClass("-hide");
			$element.parent().addClass("-hide");
		}
	},
	showInputErrorBorder: function(input, error_targer){
		jQuery(input).addClass("hosted-fields-invalid-state");
		jQuery(error_targer).removeClass("-hide");
		jQuery(error_targer).parent().removeClass("-hide");
	},
	hideInputErrorBorder: function(input, error_targer) {
		jQuery(input).removeClass("hosted-fields-invalid-state");
		jQuery(error_targer).addClass("-hide");
		jQuery(error_targer).parent().addClass("-hide");
	},
	validateInput : function(input, error_targer){
		// Check if the input value has more than 4 letters
		if (jQuery(input).val().length < 5 && jQuery(input).val().length > 0) {
			HostedFields.showInputErrorBorder(input, error_targer);
			return false;

		}else{
			HostedFields.hideInputErrorBorder(input, error_targer);
			return true;
		}
	},
	submitValidation: function (input, error_targer) {
		// Check if the input value has more than 4 letters
		if (jQuery(input).val().length < 1) {
			HostedFields.showInputErrorBorder(input, error_targer);
			return false;

		}else{
			HostedFields.hideInputErrorBorder(input, error_targer);
			return true;
		}
	},
	tokenizeHandler: function () {
		return new Promise((resolve, reject) => {
			HostedFields.hfields.createToken(function (result) {
				if ( HostedFields.submitValidation(jQuery("[name=hosted-fields-cardHolder]"), jQuery(".IntegratedPayment_error.-cardHolder .invalidField")) && result.execCode == "0000") {
					document.getElementById("hf-token").value = result.hfToken;
					resolve(result);
				}
				reject({stt:"error"});
			});
		});
	}
}



jQuery( 'body' ).on( 'updated_checkout', function() {
	HostedFields.hfields.load();
	jQuery("[name=hosted-fields-cardHolder]").on("input", function (event) {
		HostedFields.validateInput(event.target, jQuery(".IntegratedPayment_error.-cardHolder .invalidField"));
	});
});


jQuery(document).ready(function($) {
	jQuery('form.woocommerce-checkout, form#order_review').on('checkout_place_order',  function (e) {
		e.preventDefault();
		e.stopPropagation();
		HostedFields.tokenizeHandler().then((response) => {
			e.target.submit();

		}).catch((error => {
            jQuery(".IntegratedPayment_error.-payment").removeClass("-hide");
            jQuery(".IntegratedPayment_error.-payment").addClass("-show");
            jQuery(".IntegratedPayment_error.-payment").html(payplug_integrated_payment_params.payplug_integrated_payment_error);
            jQuery(".IntegratedPayment_error.-payment").fadeIn(300).delay(2000).fadeOut(300);

        }));

		return false;

	});
});

