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
			id: "fadc44f6-b98b-4ea1-a8a0-50ab1d2e216f",
			value: "Gf=}k6]*E@EYBxau"
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
	isPayplugChosen: function () {
		return jQuery('#payment_method_payplug').is(':checked');
	},
	tokenizeHandler: async function (event) {

		if (!HostedFields.isPayplugChosen()) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();

		try {

			const isValid = HostedFields.submitValidation(
				jQuery("[name=hosted-fields-cardHolder]"),
				jQuery(".IntegratedPayment_error.-cardHolder .invalidField")
			);

			const result = await new Promise((resolve, reject) => {
				HostedFields.hfields.createToken((response) => {
					if (response.execCode === "0000") {
						resolve(response);
					} else {
						reject(new Error("Tokenization failed"));
					}
				});
			});


			if (isValid) {
				document.getElementById("hftoken").value = result.hfToken;

				// Ensure no duplicate listeners
				jQuery('form.woocommerce-checkout, form#order_review').off('submit', HostedFields.tokenizeHandler);
				event.target.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
			}
		} catch (error) {
			console.error("Error during tokenization:", error);
		}

		// Prevents the submit of the form in case of failed tokenization request
		return false;
	}
}

jQuery( 'body' ).on( 'updated_checkout', function() {
	HostedFields.hfields.load();

	// Attach the event listener
	jQuery("[name=hosted-fields-cardHolder]").on("input", function (event) {
		HostedFields.validateInput(event.target, jQuery(".IntegratedPayment_error.-cardHolder .invalidField"));
	});

});


(function ($) {
	// Attach the event listener
	jQuery('form.woocommerce-checkout, form#order_review').on('submit', HostedFields.tokenizeHandler );
})(jQuery);



