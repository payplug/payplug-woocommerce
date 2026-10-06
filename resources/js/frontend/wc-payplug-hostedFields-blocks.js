import { useEffect, useRef, useState } from 'react';

const ALLOWED_BRANDS = ['CB', 'VISA', 'MASTERCARD'];
const CARDHOLDER_PATTERN = /^[A-Za-zÀ-ÖØ-öø-ÿ' .-]{2,}$/;
const FIELDS = ['cardHolder', 'pan', 'exp', 'cvv'];

// 'empty', 'invalid' or null (valid) - same states as the PrestaShop module's UHF form.
const cardholderState = (value) => {
	const trimmed = String(value || '').trim();

	if ('' === trimmed) {
		return 'empty';
	}

	return CARDHOLDER_PATTERN.test(trimmed) ? null : 'invalid';
};

const HostedFields = ({ settings: settings, props: props }) => {
	const { eventRegistration, shouldSavePayment } = props;
	const { onPaymentSetup } = eventRegistration;
	const instance = useRef(null);
	const cards = settings?.uhfCards || [];
	const [selectedCard, setSelectedCard] = useState('other');
	const showingNewCardForm = 'other' === selectedCard;
	const [cardholder, setCardholder] = useState('');
	// What each field's error line currently shows. The SDK only reports a card field's
	// validity through onInput, so fieldStates (a ref, read at submit time) starts out
	// 'empty' for all of them, while nothing is displayed until the shopper interacts or submits.
	const fieldStates = useRef({ cardHolder: 'empty', pan: 'empty', exp: 'empty', cvv: 'empty' });
	const [displayedErrors, setDisplayedErrors] = useState({});

	const setFieldState = (field, state) => {
		fieldStates.current[field] = state;
		setDisplayedErrors((errors) => ({ ...errors, [field]: state }));
	};

	const handleFieldEvent = (field) => (event) => {
		if ('invalid' === event.type) {
			setFieldState(field, 'FIELD_EMPTY' === event.errorName ? 'empty' : 'invalid');
		} else if ('valid' === event.type) {
			setFieldState(field, null);
		}
	};

	const onCardholderChange = (value) => {
		setCardholder(value);
		setFieldState('cardHolder', cardholderState(value));
	};

	// Mounted exactly once, on initial render (selectedCard's initial state is always
	// 'other', so showingNewCardForm is guaranteed true here) - never re-run when
	// selectedCard later toggles. The Dalenys SDK exposes no destroy()/unmount() method,
	// only load(): remounting on every showingNewCardForm change (this effect previously
	// depended on it) would inject a second, duplicate set of iframes into the same
	// containers each time the customer switched back to "other", since the first
	// mount's iframes are never actually removed from the DOM, only forgotten by this
	// component's own ref. Visibility while a saved card is selected is handled entirely
	// by the "hidden" attribute below, matching classic checkout's own single-mount
	// strategy (assets/js/payplug-hosted-fields.js's maybeMount()/props.instance guard).
	useEffect(() => {
		if (typeof window.dalenys === 'undefined') {
			// payplug-hosted-fields-sdk is declared as a dependency of this block's own
			// script bundle (see Blocks/PayplugCreditCard.php's get_payment_method_script_handles()),
			// so by the time this component mounts the SDK should already be loaded; this
			// guard only protects against that dependency ever regressing, not a real retry
			// path - there is no documented Dalenys SDK API to poll for readiness.
			return;
		}

		instance.current = window.dalenys.hostedFields({
			companyId: settings?.hostedFieldsCompanyId,
			// Same field options as the PrestaShop module, so the SDK renders the same form.
			fields: {
				brand: { id: 'hosted-fields-brand', version: 2, useInlineSelection: true },
				card: {
					id: 'hosted-fields-card',
					placeholder: settings?.payplug_integrated_payment_card_number,
					onInput: handleFieldEvent('pan'),
				},
				expiry: {
					id: 'hosted-fields-expiry',
					placeholder: settings?.payplug_integrated_payment_expiration_date,
					onInput: handleFieldEvent('exp'),
				},
				cryptogram: {
					id: 'hosted-fields-cryptogram',
					placeholder: settings?.payplug_integrated_payment_cvv,
					onInput: handleFieldEvent('cvv'),
				},
			},
		});
		instance.current.load();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	useEffect(() => {
		const handlePaymentProcessing = () => {
			if (!showingNewCardForm) {
				// A saved card is a completely different submission path - no card fields
				// were ever mounted/tokenized for it, matching classic checkout's own
				// isNewCardSelected()/onSubmit() short-circuit.
				return Promise.resolve({
					type: 'success',
					meta: {
						paymentMethodData: {
							payplug_uhf_card_choice: selectedCard,
						},
					},
				});
			}

			if (!instance.current) {
				return Promise.resolve({
					type: 'error',
					message: settings?.hostedFieldsTokenizationError,
				});
			}

			// Shows every field's error at once, like the classic form, and never tokenizes an
			// incomplete card.
			fieldStates.current.cardHolder = cardholderState(cardholder);
			setDisplayedErrors({ ...fieldStates.current });

			if (FIELDS.some((field) => null !== fieldStates.current[field])) {
				return Promise.resolve({
					type: 'error',
					message: settings?.hostedFieldsInvalidForm,
				});
			}

			return new Promise((resolve) => {
				instance.current.createToken(function (result) {
					if (!result || '0000' !== result.execCode) {
						resolve({
							type: 'error',
							message: settings?.hostedFieldsTokenizationError,
						});

						return;
					}

					// Normalized once and reused below: the Unified API expects
					// CB/VISA/MASTERCARD in uppercase (per the UHF spec), so whatever case
					// the SDK actually returns, the value relayed onward must already be
					// uppercase - not just the whitelist comparison.
					const selectedBrand = String(result.selectedBrand).toUpperCase();

					if (-1 === ALLOWED_BRANDS.indexOf(selectedBrand)) {
						resolve({
							type: 'error',
							message: settings?.hostedFieldsUnsupportedBrandError,
						});

						return;
					}

					// No payment is created from this token here - the order isn't created
					// yet at this point (tokenization happens before the real checkout
					// POST), and process_payment() reads these straight from
					// meta.paymentMethodData once the order exists. The token is already
					// validated client-side above; no server round-trip is needed before
					// resolving. Card metadata field names confirmed against a real staging
					// createToken() result (2026-10-05): cardCode is the masked PAN
					// ("406560XXXXXX0000"), cardValidityDate is "MM-YY".
					const cardCode = String(result.cardCode || '');
					const last4 = cardCode.slice(-4);
					const validity = /^(\d{2})-(\d{2})$/.exec(String(result.cardValidityDate || ''));

					resolve({
						type: 'success',
						meta: {
							paymentMethodData: {
								hf_token: result.hfToken,
								hf_selected_brand: selectedBrand,
								hf_cardholder: String(cardholder).trim(),
								savecard: shouldSavePayment ? '1' : '',
								hf_last4: /^\d{4}$/.test(last4) ? last4 : '',
								hf_expiration_month: validity ? String(parseInt(validity[1], 10)) : '',
								hf_expiration_year: validity ? String(2000 + parseInt(validity[2], 10)) : '',
							},
						},
					});
				});
			});
		};
		const unsubscribe = onPaymentSetup(handlePaymentProcessing);

		return () => { unsubscribe(); };
	}, [onPaymentSetup, showingNewCardForm, selectedCard, shouldSavePayment, cardholder]);

	const fieldError = (field, invalidMessage) => (
		<div className={`payplug HostedFields_error -${field}`}>
			<span className={'invalid' === displayedErrors[field] ? 'invalidField' : '-hide invalidField'}>{invalidMessage}</span>
			<span className={'empty' === displayedErrors[field] ? 'emptyField' : '-hide emptyField'}>{settings?.payplug_integrated_payment_empty}</span>
		</div>
	);
	const containerClass = (field) => `payplug HostedFields_container -${field}${displayedErrors[field] ? ' -invalid' : ''}`;

	return (
		<>
			{/* A sibling block, rendered before (and outside) the "new card" form below -
				not one of the .HostedFields_container.-X variants: this mirrors how a
				customer's saved payment methods always render as their own section,
				separate from the "new card" fields, the same visual/structural split
				Integrated Payment gets for free from WooCommerce's own native saved-token
				list. Classic checkout's Controller/HostedFields.php applies the same split
				for the same reason - see its own comment for why a UHF alias can't reuse
				that native list instead. */}
			{cards.length > 0 && (
				<div className="payplug HostedFields_savedCards" data-e2e-name="savedCards">
					{cards.map((card) => (
						<label className="payplug HostedFields_savedCard" key={card.id}>
							<input
								type="radio"
								name="payplug_uhf_card_choice"
								value={card.id}
								checked={String(selectedCard) === String(card.id)}
								onChange={() => setSelectedCard(String(card.id))}
							/>
							<span></span>
							{card.brand} &bull;&bull;&bull;&bull; {card.last4} &mdash; {String(card.exp_month).padStart(2, '0')}/{card.exp_year}
						</label>
					))}
					<label className="payplug HostedFields_savedCard -other">
						<input
							type="radio"
							name="payplug_uhf_card_choice"
							value="other"
							checked={showingNewCardForm}
							onChange={() => setSelectedCard('other')}
						/>
						<span></span>
						{settings?.hostedFieldsPayWithAnotherCard}
					</label>
				</div>
			)}
			{/* Same markup and classes as the classic form (Controller/HostedFields.php), itself
				aligned on the PrestaShop module's UHF form. The new-card fields are hidden as a whole
				(never unmounted: the SDK has no way to remount its iframes) while a saved card is selected. */}
			<div id="payplug-hosted-fields" className="payplug HostedFields -loaded">
				<div className="payplug HostedFields_newCard" hidden={!showingNewCardForm}>
					<div className={containerClass('cardHolder')}>
						<input
							type="text"
							name="hf_cardholder"
							id="hf-cardholder"
							placeholder={settings?.payplug_integrated_payment_cardholder}
							autoComplete="cc-name"
							value={cardholder}
							onChange={(event) => onCardholderChange(event.target.value)}
							onBlur={(event) => onCardholderChange(event.target.value)}
						/>
					</div>
					{fieldError('cardHolder', settings?.payplug_integrated_payment_cardHolder_error)}

					<div className="payplug HostedFields_container -scheme">
						<div>{settings?.payplug_integrated_payment_your_card}</div>
						<div className="payplug HostedFields_schemes" id="hosted-fields-brand" data-e2e-name="brand"></div>
					</div>

					<div className={containerClass('pan')} id="hosted-fields-card" data-e2e-name="card"></div>
					{fieldError('pan', settings?.payplug_integrated_payment_pan_error)}

					<div className={containerClass('exp')} id="hosted-fields-expiry" data-e2e-name="expiry"></div>
					<div className={containerClass('cvv')} id="hosted-fields-cryptogram" data-e2e-name="cryptogram"></div>
					{fieldError('exp', settings?.payplug_integrated_payment_exp_error)}
					{fieldError('cvv', settings?.payplug_integrated_payment_cvv_error)}
				</div>

				<div className="payplug HostedFields_privacy_policy">
					<img className="-lock" src={settings?.lock} alt="" />{' '}
					<span>{settings?.payplug_integrated_payment_transaction_secure}</span>{' '}
					<img className="-logo" src={settings?.logo} alt="Payplug" />
					<br />
					<a href={settings?.payplug_integrated_payment_privacy_policy_url} target="_blank" rel="noopener noreferrer">{settings?.payplug_integrated_payment_privacy_policy}</a>
				</div>
			</div>
		</>
	);
};

export default HostedFields;
