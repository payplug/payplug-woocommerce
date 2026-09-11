import { useEffect, useRef } from 'react';

const ALLOWED_BRANDS = ['CB', 'VISA', 'MASTERCARD'];

const HostedFields = ({ settings: settings, props: props }) => {
	const { eventRegistration } = props;
	const { onPaymentSetup } = eventRegistration;
	const instance = useRef(null);

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

		// Switching payment method away and back remounts this component; clearing the
		// ref (rather than leaving it pointing at a torn-down instance) means the next
		// mount's window.dalenys.hostedFields() call above starts clean instead of a
		// createToken() call ever racing against a stale instance.
		return () => {
			instance.current = null;
		};
	}, []);

	useEffect(() => {
		const handlePaymentProcessing = () => {
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
					// POST), and process_payment() reads hf_token/hf_selected_brand from
					// meta.paymentMethodData once the order exists. The token is already
					// validated client-side above; no server round-trip is needed before
					// resolving.
					resolve({
						type: 'success',
						meta: {
							paymentMethodData: {
								hf_token: result.hfToken,
								hf_selected_brand: selectedBrand,
							},
						},
					});
				});
			});
		};
		const unsubscribe = onPaymentSetup(handlePaymentProcessing);

		return () => { unsubscribe(); };
	}, [onPaymentSetup]);

	return (
		<div id="payplug-hosted-fields" className="payplug HostedFields -loaded">
			<div className="payplug HostedFields_container -brand" id="hosted-fields-brand" data-e2e-name="brand"></div>
			<div className="payplug HostedFields_container -card" id="hosted-fields-card" data-e2e-name="card"></div>
			<div className="payplug HostedFields_container -expiry" id="hosted-fields-expiry" data-e2e-name="expiry"></div>
			<div className="payplug HostedFields_container -cryptogram" id="hosted-fields-cryptogram" data-e2e-name="cryptogram"></div>
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
	);
};

export default HostedFields;
