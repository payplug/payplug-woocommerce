import { useEffect, useRef } from 'react';

const ALLOWED_BRANDS = ['cb', 'visa', 'mastercard'];

const HostedFields = ({ settings: settings, props: props }) => {
	const { eventRegistration } = props;
	const { onPaymentSetup } = eventRegistration;
	const instance = useRef(null);

	useEffect(() => {
		if (typeof window.dalenys === 'undefined') {
			return;
		}

		instance.current = window.dalenys.hostedFields({
			key: {
				id: settings?.hostedFieldsKeyId,
				value: settings?.hostedFieldsKeyValue,
			},
			fields: {
				brand: { id: 'hosted-fields-brand' },
				card: { id: 'hosted-fields-card' },
				expiry: { id: 'hosted-fields-expiry' },
				cryptogram: { id: 'hosted-fields-cryptogram' },
			},
		});
		instance.current.load();
	}, []);

	useEffect(() => {
		const handlePaymentProcessing = () => {
			return new Promise((resolve) => {
				instance.current.createToken(function (result) {
					if (!result || '0000' !== result.execCode) {
						resolve({
							type: 'error',
							message: settings?.hostedFieldsTokenizationError,
						});

						return;
					}

					if (-1 === ALLOWED_BRANDS.indexOf(result.selectedBrand)) {
						resolve({
							type: 'error',
							message: settings?.hostedFieldsUnsupportedBrandError,
						});

						return;
					}

					jQuery.post(settings?.hostedFieldsAjaxUrl, {
						hfToken: result.hfToken,
						selectedBrand: result.selectedBrand,
						save_card: props.shouldSavePayment ? 1 : 0,
						nonce: settings?.hostedFieldsNonce,
					}).always(function () {
						resolve({
							type: 'success',
							meta: {
								paymentMethodData: {
									hf_token: result.hfToken,
									hf_selected_brand: result.selectedBrand,
								},
							},
						});
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
		</div>
	);
};

export default HostedFields;
