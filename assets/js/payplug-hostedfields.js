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
const $ = jQuery;
const hosted_fields_mid = typeof hosted_fields_params.HOSTED_FIELD_MID !== 'undefined'
	? hosted_fields_params.HOSTED_FIELD_MID
	: {
		'api_key_id': null,
		'api_key': null
	};

var HostedFields = {
	hfields: typeof dalenys !== 'undefined' ? dalenys.hostedFields({
		// API Keys
		key: {
			id: hosted_fields_mid.api_key_id,
			value: hosted_fields_mid.api_key
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
						$("input[type='radio'][name='schemeOptions'][value='"+event['cardType']+"']").attr("checked", true);
					}

					HostedFields.handleInvalidFieldErrors(event, $(".IntegratedPayment_error.-pan .invalidField"))
				}
			},
			'expiry': {
				id: 'expiry-container',
				placeholder: payplug_integrated_payment_params.expiration_date,
				style: inputStyle,
				onInput: function (event) {
					HostedFields.handleInvalidFieldErrors(event, $(".IntegratedPayment_error.-exp .invalidField"))
				}
			},
			'cryptogram': {
				id: 'cvv-container',
				placeholder: payplug_integrated_payment_params.cvv,
				style: inputStyle,
				onInput: function (event) {
					HostedFields.handleInvalidFieldErrors(event, $(".IntegratedPayment_error.-cvv .invalidField"))
				}
			}
		}
	}) : null,
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
		$(input).addClass("hosted-fields-invalid-state");
		$(error_targer).removeClass("-hide");
		$(error_targer).parent().removeClass("-hide");
	},
	hideInputErrorBorder: function(input, error_targer) {
		$(input).removeClass("hosted-fields-invalid-state");
		$(error_targer).addClass("-hide");
		$(error_targer).parent().addClass("-hide");
	},
	validateInput : function(input, error_targer){
		// Check if the input value has more than 4 letters
		if ($(input).val().length < 5 && $(input).val().length > 0) {
			HostedFields.showInputErrorBorder(input, error_targer);
			return false;

		}else{
			HostedFields.hideInputErrorBorder(input, error_targer);
			return true;
		}
	},
	submitValidation: function (input, error_targer) {
		// Check if the input value has more than 4 letters
		if ($(input).val().length < 1) {
			HostedFields.showInputErrorBorder(input, error_targer);
			return false;

		}else{
			HostedFields.hideInputErrorBorder(input, error_targer);
			return true;
		}
	},
	isPayplugChosen: function () {
		return $('#payment_method_payplug').is(':checked');
	},
	tokenizeHandler: async function (event) {

		if (!HostedFields.isPayplugChosen()) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();

		try {

			const isValid = HostedFields.submitValidation(
				$("[name=hosted-fields-cardHolder]"),
				$(".IntegratedPayment_error.-cardHolder .invalidField")
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
				document.getElementById("card-last4").value = result.cardCode ? result.cardCode.slice(-4) : "";
				document.getElementById("card-expiry").value = document.getElementById("card-expiry").value = result.cardValidityDate ? result.cardValidityDate.replace("-", "/") : "";

				// Ensure no duplicate listeners
				$('form.woocommerce-checkout, form#order_review').off('submit', HostedFields.tokenizeHandler);
				event.target.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
			}
		} catch (error) {
			console.error("Error during tokenization:", error);
		}

		// Prevents the submit of the form in case of failed tokenization request
		return false;
	}
}

$( 'body' ).on( 'updated_checkout', function() {
	HostedFields.hfields.load();

	// Attach the event listener
	$("[name=hosted-fields-cardHolder]").on("input", function (event) {
		HostedFields.validateInput(event.target, $(".IntegratedPayment_error.-cardHolder .invalidField"));
	});

});


(function ($) {
	// Attach the event listener
	$('form.woocommerce-checkout, form#order_review').on('submit', HostedFields.tokenizeHandler );
})($);



