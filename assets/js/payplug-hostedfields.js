var style = {
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

var hfields = {
	init: dalenys.hostedFields({
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
				style: style,
				onInput: function (event) {
					if( typeof event['cardType'] !== "undefined" ) {
						jQuery("input[type='radio'][name='schemeOptions'][value='"+event['cardType']+"']").attr("checked", true);
					}

					hfields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-pan .invalidField"))
				}
			},
			'expiry': {
				id: 'expiry-container',
				placeholder: payplug_integrated_payment_params.expiration_date,
				style: style,
				onInput: function (event) {
					hfields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-exp .invalidField"))
				}
			},
			'cryptogram': {
				id: 'cvv-container',
				placeholder: payplug_integrated_payment_params.cvv,
				style: style,
				onInput: function (event) {
					hfields.handleInvalidFieldErrors(event, jQuery(".IntegratedPayment_error.-cvv .invalidField"))
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
	validateInput : function(input, $error_targer){

		// Check if the input value has more than 4 letters
		if (jQuery(input).val().length < 5 && jQuery(input).val().length > 0) {
			jQuery(input).addClass("hosted-fields-invalid-state");
			jQuery($error_targer).removeClass("-hide");
			jQuery($error_targer).parent().removeClass("-hide");

		}else{
			jQuery(input).removeClass("hosted-fields-invalid-state");
			jQuery($error_targer).addClass("-hide");
			jQuery($error_targer).parent().addClass("-hide");
		}
	}
}



jQuery( 'body' ).on( 'updated_checkout', function() {
	hfields.init.load();
	jQuery("[name=hosted-fields-cardHolder]").on("input", function (event) {
		hfields.validateInput(event.target, jQuery(".IntegratedPayment_error.-cardHolder .invalidField"));
	});
});

