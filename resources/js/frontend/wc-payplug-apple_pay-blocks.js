import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useSelect } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import {useEffect} from "react";
import {apple_pay_update_payment, getPayment} from "./helper/wc-payplug-apple_pay-requests";
import {check_payment} from "./helper/wc-payplug-requests";
const settings = getSetting( 'apple_pay_data', {} );
const defaultLabel = __('Gateway method title', 'payplug');
const label = decodeEntities( settings?.title ) || defaultLabel;

const Content = (props) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup, onCheckoutSuccess, onCheckoutFail} = eventRegistration;
	const { PAYMENT_STORE_KEY, CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;
	const order_id = useSelect( ( select ) => select( CHECKOUT_STORE_KEY ).getOrderId() );
	let session = null;

	useEffect(() => {
		const handlePaymentProcessing = async () => {
			apple_pay.CreateSession();
			apple_pay.CancelOrder();
			await getPayment(props, order_id).then(async (response) => {
				apple_pay.BeginSession(response);
			})

			return {
				type: "success"
			}

		}

		const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onPaymentSetup,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS
	]);

	useEffect(() => {
		const handlePaymentProcessing = async ({processingResponse: {paymentDetails}}) => {
			var apple_pay_Session_status;
			await authorizedPayment();
			var flag = false;

			function authorizedPayment(){
				return new Promise((resolve, reject) => {
					session.onpaymentauthorized = async event => {
						let data = {
							'action': 'applepay_update_payment',
							'post_type': 'POST',
							'payment_id': session.payment_id,
							'payment_token': event.payment.token,
							'order_id': session.order_id
						};

						if(flag){
							reject();
						}

						await apple_pay_update_payment(data).then((res) => {
							apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;
							if (res.success !== true) {
								apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
							}
							session.completePayment({"status": apple_pay_Session_status})
							resolve();
						});
					}
				});
			}

			return {
				"type":"success",
				"redirectUrl": session.return_url,
			}
		}
		const unsubscribeAfterProcessing = onCheckoutSuccess(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };

	}, [
		onCheckoutSuccess
	]);

	let apple_pay = {
		CreateSession: function () {
			const request = {
				"countryCode": settings.payplug_countryCode,
				"currencyCode": settings.payplug_currencyCode,
				"merchantCapabilities": [
					"supports3DS"
				],
				"supportedNetworks": [
					"visa",
					"masterCard"
				],
				"total": {
					"label": "Apple Pay",
					"type": "final",
					"amount": props.billing.cartTotal.value/100
				},
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': settings.payplug_apple_pay_domain
				}))
			}

			session = new ApplePaySession(3, request)
		},
		CancelOrder: function () {
			session.oncancel = event => {
				window.location = session.cancel_url
			}
		},
		BeginSession: function (response) {
			session.payment_id = response.data.payment_id;
			session.order_id = order_id
			session.cancel_url = response.data.cancel;
			session.return_url = response.data.redirect;
			apple_pay.MerchantValidated(session, response.data.merchant_session)
			session.begin()
		},
		MerchantValidated: function(session, merchant_session) {
			session.onvalidatemerchant = async event => {
				try {
					session.completeMerchantValidation(merchant_session)
				} catch (err) {
					alert(err)
				}
			}
		}
	}

	return (
		<></>
	)

	return window.wp.htmlEntities.decodeEntities(settings?.description || '');

};
/**
 * Label component
 *
 */
const Label = () => {
	return (
		<span style={{ width: '100%' }}>
            {label}
			<Icon />
        </span>
	)
}

const Icon = () => {
	return (
		<img src={settings?.icon.src} alt={settings?.icon.icon_alt} className="payplug-payment-icon" style={{float: 'right'}}/>
	)
}

/**
 * Payplug payment method config object.
 */
const ApplePay = {
	name: "apple_pay",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => {return true},
	ariaLabel: label,
	supports: {
		features: settings.supports
	},
};

registerPaymentMethod( ApplePay );

