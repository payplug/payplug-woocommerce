import { useEffect, useRef, useState } from 'react';

const ALLOWED_BRANDS = ['CB', 'VISA', 'MASTERCARD'];

const HostedFields = ({ settings: settings, props: props }) => {
	const { eventRegistration, shouldSavePayment } = props;
	const { onPaymentSetup } = eventRegistration;
	const instance = useRef(null);
	const cards = settings?.uhfCards || [];
	const [selectedCard, setSelectedCard] = useState('other');
	const showingNewCardForm = 'other' === selectedCard;

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
			fields: {
				brand: { id: 'hosted-fields-brand' },
				card: { id: 'hosted-fields-card' },
				expiry: { id: 'hosted-fields-expiry' },
				cryptogram: { id: 'hosted-fields-cryptogram' },
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
					// resolving. CAVEAT (PRE-3638): result.last4/expirationMonth/expirationYear
					// are this SDK's best-known field names, unconfirmed against a real
					// createToken() response - guarded by `|| ''` below so a wrong guess
					// degrades rather than crashes, but combined with a failed server-side
					// getOperation() fetch this is a card that silently never saves. Confirm/
					// correct these against the browser console on a real staging tokenization
					// before relying on them in production.
					resolve({
						type: 'success',
						meta: {
							paymentMethodData: {
								hf_token: result.hfToken,
								hf_selected_brand: selectedBrand,
								savecard: shouldSavePayment ? '1' : '',
								hf_last4: result.last4 || '',
								hf_expiration_month: result.expirationMonth || '',
								hf_expiration_year: result.expirationYear || '',
							},
						},
					});
				});
			});
		};
		const unsubscribe = onPaymentSetup(handlePaymentProcessing);

		return () => { unsubscribe(); };
	}, [onPaymentSetup, showingNewCardForm, selectedCard, shouldSavePayment]);

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
			<div id="payplug-hosted-fields" className="payplug HostedFields -loaded">
				<div className="payplug HostedFields_container -brand" id="hosted-fields-brand" data-e2e-name="brand" hidden={!showingNewCardForm}></div>
				<div className="payplug HostedFields_container -card" id="hosted-fields-card" data-e2e-name="card" hidden={!showingNewCardForm}></div>
				<div className="payplug HostedFields_container -expiry" id="hosted-fields-expiry" data-e2e-name="expiry" hidden={!showingNewCardForm}></div>
				<div className="payplug HostedFields_container -cryptogram" id="hosted-fields-cryptogram" data-e2e-name="cryptogram" hidden={!showingNewCardForm}></div>
				{/* Visually identical to Integrated Payment's footer, so it reuses the same
					IntegratedPayment_container classes and settings keys rather than duplicating them. */}
				<div className="payplug IntegratedPayment_container -transaction">
					<img className="lock-icon" src={settings?.lock} />
					<label className="transaction-label">{settings?.payplug_integrated_payment_transaction_secure}</label>
					<img className="payplug-logo" src={settings?.logo} />
				</div>
				<div className="payplug IntegratedPayment_container -privacy-policy">
					<a href={settings?.payplug_integrated_payment_privacy_policy_url} target="_blank">{settings?.payplug_integrated_payment_privacy_policy}</a>
				</div>
			</div>
		</>
	);
};

export default HostedFields;
