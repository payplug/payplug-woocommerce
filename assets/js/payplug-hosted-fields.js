var HostedFields = {
	props: {
		instance: null,
		submitting: false,
		// Same state as the PrestaShop module's hosted_fields sub-module: the SDK only reports
		// a field's validity through onInput, so every field starts out empty until it does.
		fieldsInvalid: { cardHolder: true, pan: true, exp: true, cvv: true },
		fieldsEmpty: { cardHolder: true, pan: true, exp: true, cvv: true },
	},
	ALLOWED_BRANDS: ['CB', 'VISA', 'MASTERCARD'],
	CARDHOLDER_PATTERN: /^[A-Za-zÀ-ÖØ-öø-ÿ' .-]{2,}$/,
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
		jQuery('body').on('payment_method_selected', HostedFields.maybeMount);
		jQuery('body').on('updated_checkout', HostedFields.handleCheckoutUpdated);
		jQuery('body').on('checkout_error', HostedFields.resetToken);

		// Delegated (not bound directly on the radios): updated_checkout replaces the
		// payment-methods markup, including these radios, with fresh elements that a
		// direct .on('change', ...) binding would never reach again.
		jQuery('body').on('change', '[name=wc-payplug-payment-token]', HostedFields.manageSavedCards);
		jQuery('body').on('change', '[name=payplug_uhf_card_choice]', HostedFields.manageSavedCards);
		jQuery('body').on('input blur', '#hf-cardholder', HostedFields.validateCardholder);

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
		var tokenIsNew = !$tokens.length || 'new' === $tokens.filter(':checked').val();

		var $uhfCards = jQuery('[name=payplug_uhf_card_choice]');
		var uhfCardIsOther = !$uhfCards.length || 'other' === $uhfCards.filter(':checked').val();

		return tokenIsNew && uhfCardIsOther;
	},
	// WooCommerce's own checkout AJAX refresh (updated_checkout - quantity, coupon,
	// shipping method, address...) tears down and rebuilds the entire #payment markup
	// from scratch, including these containers: any previously mounted SDK instance is
	// bound to DOM nodes that no longer exist. Forgetting it here, unconditionally and
	// deterministically, is what lets the next maybeMount() call mount fresh into the new
	// containers - relying on a DOM inspection instead (checking for an existing iframe)
	// raced against the SDK's own async iframe injection, letting two calls on the same
	// tick (this event alone used to bind two separate handlers that both called
	// maybeMount()) both slip through and mount a second, duplicate set of iframes before
	// the first attempt had actually rendered.
	handleCheckoutUpdated: function () {
		HostedFields.props.instance = null;
		HostedFields.resetFieldsState();
		HostedFields.manageSavedCards();
	},
	// Cross-origin iframes mounted into a display:none container don't render. The real
	// mount is therefore deferred until the method is both selected and its container is
	// actually visible - never at document ready unconditionally.
	maybeMount: function () {
		if (typeof window.dalenys === 'undefined' || !document.getElementById('hosted-fields-card')) {
			return;
		}

		// Checked synchronously, the instant a mount is decided (below) - not inferred
		// from whether the SDK's iframe has actually rendered yet (asynchronous), which
		// would otherwise let a second maybeMount() call - a rapid radio switch, or two
		// event handlers firing on the same tick - slip through and mount a duplicate set
		// of iframes before the first one appears. handleCheckoutUpdated() is the only
		// place this is ever reset back to null, once WooCommerce has genuinely replaced
		// the containers this instance was mounted into.
		if (HostedFields.props.instance) {
			return;
		}

		if (!HostedFields.isSelected() || !HostedFields.isNewCardSelected() || !jQuery('#hosted-fields-card').is(':visible')) {
			return;
		}

		HostedFields.props.instance = window.dalenys.hostedFields({
			companyId: payplug_hosted_fields_params.company_id,
			// Same field options as the PrestaShop module, so the SDK renders the same form.
			fields: {
				brand: {
					id: 'hosted-fields-brand',
					version: 2,
					useInlineSelection: true,
				},
				card: {
					id: 'hosted-fields-card',
					placeholder: payplug_hosted_fields_params.placeholder_pan,
					onInput: function (event) {
						HostedFields.handleFieldEvent('pan', event);
					},
				},
				expiry: {
					id: 'hosted-fields-expiry',
					placeholder: payplug_hosted_fields_params.placeholder_exp,
					onInput: function (event) {
						HostedFields.handleFieldEvent('exp', event);
					},
				},
				cryptogram: {
					id: 'hosted-fields-cryptogram',
					placeholder: payplug_hosted_fields_params.placeholder_cvv,
					onInput: function (event) {
						HostedFields.handleFieldEvent('cvv', event);
					},
				},
			},
		});
		HostedFields.props.instance.load();
	},
	resetFieldsState: function () {
		['cardHolder', 'pan', 'exp', 'cvv'].forEach(function (field) {
			HostedFields.props.fieldsInvalid[field] = true;
			HostedFields.props.fieldsEmpty[field] = true;
		});
	},
	handleFieldEvent: function (field, event) {
		if ('invalid' === event.type) {
			HostedFields.props.fieldsInvalid[field] = true;
			HostedFields.props.fieldsEmpty[field] = 'FIELD_EMPTY' === event.errorName;
			HostedFields.showFieldError(field);
		} else if ('valid' === event.type) {
			HostedFields.props.fieldsInvalid[field] = false;
			HostedFields.props.fieldsEmpty[field] = false;
			HostedFields.showFieldError(field);
		}
	},
	// Unlike the PrestaShop module, an untouched cardholder field shows no error until the
	// shopper leaves it or submits: nothing has been typed wrong yet.
	validateCardholder: function () {
		var value = String(jQuery('#hf-cardholder').val() || '').trim();
		var empty = '' === value;

		HostedFields.props.fieldsEmpty.cardHolder = empty;
		HostedFields.props.fieldsInvalid.cardHolder = !empty && !HostedFields.CARDHOLDER_PATTERN.test(value);
		HostedFields.showFieldError('cardHolder');
	},
	showFieldError: function (field) {
		var empty = HostedFields.props.fieldsEmpty[field];
		var invalid = HostedFields.props.fieldsInvalid[field] && !empty;
		var $error = jQuery('.payplug.HostedFields_error.-' + field);

		$error.find('.emptyField').toggleClass('-hide', !empty);
		$error.find('.invalidField').toggleClass('-hide', !invalid);
		jQuery('.payplug.HostedFields_container.-' + field).toggleClass('-invalid', empty || invalid);
	},
	// Shows every field's error at once on submit, and tells whether the form can be tokenized.
	validateFields: function () {
		HostedFields.validateCardholder();

		// map() then every(), not every() alone: every() stops at the first bad field and would
		// leave the others' errors hidden.
		return ['cardHolder', 'pan', 'exp', 'cvv'].map(function (field) {
			HostedFields.showFieldError(field);

			return !HostedFields.props.fieldsInvalid[field] && !HostedFields.props.fieldsEmpty[field];
		}).every(Boolean);
	},
	// One-click: a stored card is a completely different submission path (no card fields
	// tokenized), so the form must be hidden - and never mounted, see isNewCardSelected()
	// above - whenever the customer has a saved card selected rather than "pay with
	// another card".
	manageSavedCards: function () {
		jQuery('.payplug.HostedFields').toggleClass('-hide', !HostedFields.isNewCardSelected());
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

		HostedFields.hideErrors();

		if (!HostedFields.validateFields()) {
			return false;
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
		jQuery('#hf-last4').val('');
		jQuery('#hf-expiration-month').val('');
		jQuery('#hf-expiration-year').val('');
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

			// Best-effort fallback metadata for the "save card" flow - server-side persistence
			// (Upc\PaymentCaptureOutcomeApplier::maybe_persist_uhf_card()) falls back to these
			// only when its own post-payment metadata fetch fails. Field names confirmed against
			// a real staging createToken() result (2026-10-05): cardCode is the masked PAN
			// ("406560XXXXXX0000"), cardValidityDate is "MM-YY".
			if (jQuery('[name=savecard]').is(':checked')) {
				var card = HostedFields.cardMetadata(result);
				jQuery('#hf-last4').val(card.last4);
				jQuery('#hf-expiration-month').val(card.expirationMonth);
				jQuery('#hf-expiration-year').val(card.expirationYear);
			}

			// The order isn't created yet at this point (tokenization happens before the
			// real checkout POST), so there is no payment to create from this token here -
			// process_payment() reads hf_token/hf_selected_brand from the checkout POST
			// once the order exists. The token is already validated client-side above; no
			// server round-trip is needed before submitting.
			HostedFields.props.submitting = true;
			HostedFields.form().trigger('submit');
		});
	},
	cardMetadata: function (result) {
		var last4 = String(result.cardCode || '').slice(-4);
		var validity = /^(\d{2})-(\d{2})$/.exec(String(result.cardValidityDate || ''));

		return {
			last4: /^\d{4}$/.test(last4) ? last4 : '',
			expirationMonth: validity ? String(parseInt(validity[1], 10)) : '',
			expirationYear: validity ? String(2000 + parseInt(validity[2], 10)) : ''
		};
	},
	showError: function (className) {
		jQuery('.payplug.HostedFields_error.-payment').addClass('-show');
		jQuery('.payplug.HostedFields_error.-payment .' + className).removeClass('-hide');
	},
	hideErrors: function () {
		jQuery('.payplug.HostedFields_error.-payment').removeClass('-show');
		jQuery('.payplug.HostedFields_error.-payment .genericError, .payplug.HostedFields_error.-payment .brandError').addClass('-hide');
	},
};

jQuery(function () {
	HostedFields.init();
});
