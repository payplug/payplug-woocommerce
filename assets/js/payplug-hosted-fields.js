var HostedFields = {
	props: {
		instance: null,
		submitting: false,
	},
	ALLOWED_BRANDS: ['cb', 'visa', 'mastercard'],
	form: function () {
		if (jQuery('form.woocommerce-checkout').length) {
			return jQuery('form.woocommerce-checkout');
		}

		if (jQuery('form#order_review').length) {
			return jQuery('form#order_review');
		}
	},
	init: function () {
		if (typeof window.dalenys === 'undefined' || !document.getElementById('hosted-fields-card')) {
			return;
		}

		HostedFields.props.instance = window.dalenys.hostedFields({
			key: {
				id: payplug_hosted_fields_params.key_id,
				value: payplug_hosted_fields_params.key_value,
			},
			fields: {
				brand: { id: 'hosted-fields-brand' },
				card: { id: 'hosted-fields-card' },
				expiry: { id: 'hosted-fields-expiry' },
				cryptogram: { id: 'hosted-fields-cryptogram' },
			},
		});
		HostedFields.props.instance.load();

		HostedFields.form().on('checkout_place_order_payplug submit', HostedFields.onSubmit);
	},
	onSubmit: function () {
		if (HostedFields.props.submitting) {
			return true;
		}

		if (jQuery('#hf-token').val()) {
			return true;
		}

		HostedFields.tokenize();

		return false;
	},
	tokenize: function () {
		HostedFields.hideErrors();
		HostedFields.props.instance.createToken(function (result) {
			if (!result || '0000' !== result.execCode) {
				HostedFields.showError('genericError');

				return;
			}

			if (-1 === HostedFields.ALLOWED_BRANDS.indexOf(result.selectedBrand)) {
				HostedFields.showError('brandError');

				return;
			}

			jQuery('#hf-token').val(result.hfToken);
			jQuery('#hf-selected-brand').val(result.selectedBrand);

			jQuery.post(payplug_hosted_fields_params.ajax_url, {
				hfToken: result.hfToken,
				selectedBrand: result.selectedBrand,
				save_card: jQuery('[name=savecard]').is(':checked') ? 1 : 0,
				nonce: payplug_hosted_fields_params.nonce,
			}).always(function () {
				HostedFields.props.submitting = true;
				HostedFields.form().trigger('submit');
			});
		});
	},
	showError: function (className) {
		jQuery('.payplug.HostedFields_error.-tokenization').removeClass('-hide');
		jQuery('.payplug.HostedFields_error.-tokenization .' + className).removeClass('-hide');
	},
	hideErrors: function () {
		jQuery('.payplug.HostedFields_error.-tokenization').addClass('-hide');
		jQuery('.payplug.HostedFields_error.-tokenization .genericError, .payplug.HostedFields_error.-tokenization .brandError').addClass('-hide');
	},
};

jQuery(function () {
	HostedFields.init();
});
