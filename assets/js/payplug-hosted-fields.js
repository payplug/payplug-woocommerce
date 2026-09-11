var HostedFields = {
	props: {
		instance: null,
		submitting: false,
	},
	ALLOWED_BRANDS: ['CB', 'VISA', 'MASTERCARD'],
	form: function () {
		if (jQuery('form.woocommerce-checkout').length) {
			return jQuery('form.woocommerce-checkout');
		}

		if (jQuery('form#order_review').length) {
			return jQuery('form#order_review');
		}
	},
	init: function () {
		HostedFields.bindEvents();
		HostedFields.maybeMount();
	},
	bindEvents: function () {
		jQuery('body').on('payment_method_selected updated_checkout', HostedFields.maybeMount);
		jQuery('body').on('updated_checkout', HostedFields.manageSavedCards);
		jQuery('body').on('checkout_error', HostedFields.resetToken);

		// Delegated (not bound directly on the radios): updated_checkout replaces the
		// payment-methods markup, including these radios, with fresh elements that a
		// direct .on('change', ...) binding would never reach again.
		jQuery('body').on('change', '[name=wc-payplug-payment-token]', HostedFields.manageSavedCards);

		// WooCommerce's own submit handler is bound on the same form; a plain .on('submit')
		// runs after it, so return false can't cancel the already-dispatched place-order
		// AJAX. bindFirst (bundled for exactly this, see payplug-integrated-payments.js)
		// guarantees this handler runs first, so preventDefault()/stopImmediatePropagation()
		// can actually stop the submission while the token is being tokenized.
		HostedFields.form().bindFirst('submit', function (event) {
			if (!HostedFields.isSelected()) {
				return;
			}

			if (HostedFields.onSubmit()) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();
		});

		HostedFields.manageSavedCards();
	},
	isSelected: function () {
		return jQuery('#payment_method_payplug').is(':checked');
	},
	isNewCardSelected: function () {
		var $tokens = jQuery('[name=wc-payplug-payment-token]');

		return !$tokens.length || 'new' === $tokens.filter(':checked').val();
	},
	// Mirrors payplug-integrated-payments.js's checkLoaded(): checking the DOM for an
	// actually-mounted iframe (rather than trusting a boolean flag) means a checkout AJAX
	// refresh that replaces the containers - country, postcode, shipping method, coupon,
	// quantity - is detected as "not loaded" and correctly triggers a remount, instead of
	// leaving the customer with orphaned, unrecoverable iframes.
	checkLoaded: function () {
		return jQuery('#hosted-fields-card iframe').length > 0;
	},
	// Cross-origin iframes mounted into a display:none container don't render. The real
	// mount is therefore deferred until the method is both selected and its container is
	// actually visible - never at document ready unconditionally.
	maybeMount: function () {
		if (typeof window.dalenys === 'undefined' || !document.getElementById('hosted-fields-card')) {
			return;
		}

		if (HostedFields.checkLoaded()) {
			return;
		}

		if (!HostedFields.isSelected() || !HostedFields.isNewCardSelected() || !jQuery('#hosted-fields-card').is(':visible')) {
			return;
		}

		HostedFields.props.instance = window.dalenys.hostedFields({
			companyId: payplug_hosted_fields_params.company_id,
			fields: {
				brand: { id: 'hosted-fields-brand' },
				card: { id: 'hosted-fields-card' },
				expiry: { id: 'hosted-fields-expiry' },
				cryptogram: { id: 'hosted-fields-cryptogram' },
			},
		});
		HostedFields.props.instance.load();
	},
	// One-click: a stored card is a completely different submission path (no card fields
	// tokenized), so the form must be hidden - and never mounted, see isNewCardSelected()
	// above - whenever the customer has a saved card selected rather than "pay with
	// another card".
	manageSavedCards: function () {
		jQuery('form.payplug.HostedFields').toggleClass('-hide', !HostedFields.isNewCardSelected());
		HostedFields.maybeMount();
	},
	onSubmit: function () {
		// A saved card is a completely different submission path - no card fields were
		// ever mounted/tokenized for it (see maybeMount()), so props.instance can be null
		// here. Let WooCommerce's own saved-card handling proceed unblocked.
		if (!HostedFields.isNewCardSelected()) {
			return true;
		}

		if (HostedFields.props.submitting) {
			return true;
		}

		if (jQuery('#hf-token').val()) {
			return true;
		}

		HostedFields.tokenize();

		return false;
	},
	// The hfToken the SDK returns is single-use. Without this, a server-side checkout
	// failure followed by a retry would re-submit an already-consumed token instead of
	// tokenizing again.
	resetToken: function () {
		HostedFields.props.submitting = false;
		jQuery('#hf-token').val('');
		jQuery('#hf-selected-brand').val('');
	},
	tokenize: function () {
		HostedFields.hideErrors();
		HostedFields.props.instance.createToken(function (result) {
			if (!result || '0000' !== result.execCode) {
				HostedFields.showError('genericError');

				return;
			}

			// Normalized once and reused below: the Unified API expects CB/VISA/MASTERCARD
			// in uppercase (per the UHF spec), so whatever case the SDK actually returns,
			// the value relayed onward must already be uppercase - not just the whitelist
			// comparison.
			var selectedBrand = String(result.selectedBrand).toUpperCase();

			if (-1 === HostedFields.ALLOWED_BRANDS.indexOf(selectedBrand)) {
				HostedFields.showError('brandError');

				return;
			}

			jQuery('#hf-token').val(result.hfToken);
			jQuery('#hf-selected-brand').val(selectedBrand);

			// The order isn't created yet at this point (tokenization happens before the
			// real checkout POST), so there is no payment to create from this token here -
			// process_payment() reads hf_token/hf_selected_brand from the checkout POST
			// once the order exists. The token is already validated client-side above; no
			// server round-trip is needed before submitting.
			HostedFields.props.submitting = true;
			HostedFields.form().trigger('submit');
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
