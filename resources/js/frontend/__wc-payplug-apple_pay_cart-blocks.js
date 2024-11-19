import React, {useEffect} from 'react';
import { getSetting } from '@woocommerce/settings';
import { apple_pay_CancelOrder, apple_pay_Payment, apple_pay_PlaceOrderWithDummyData, apple_pay_UpdateOrder} from "./helper/wc-payplug-apple_pay-requests";
const settings = getSetting( 'apple_pay_data', {} );

const ApplePayCart = ( props ) =>{

	let session = null;
	let order_id = null;
	const { eventRegistration, emitResponse } = props;
	const { onPaymentProcessing, onCheckoutSuccess } = eventRegistration;
	const apple_pay = {
		load_order_total: false,
		OrderPaymentCreated: function (response) {

			console.log("OrderPaymentCreated")
			console.log(response)


			if ('success' !== response.payment_data.result) {
				console.log("?????")
				apple_pay.CancelOrder();
			}

			apple_pay.BeginSession(response)
		},
		CreateSession: function () {
			const request = {
				"countryCode": settings.countryCode,
				"currencyCode": settings.currencyCode,
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
				'shippingMethods': settings.payplug_carriers,
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': settings.apple_pay_domain
				})),
				'requiredBillingContactFields' : [
					'postalAddress',
					'name',
				],
				'requiredShippingContactFields' : [
					"postalAddress",
					"name",
					"phone",
					"email"
				],
			}
			session = new ApplePaySession(3, request);

		},
		CancelOrder: function () {
			session.oncancel = event => {
				apple_pay_CancelOrder({'order_id': session.order_id, 'payment_id': session.payment_id});
			}
		},
		BeginSession: function (response) {
			session.payment_id = response.payment_data.payment_id
			session.order_id = response.order_id
			session.cancel_url = response.payment_data.cancel_url
			session.return_url = response.payment_data.return_url
			apple_pay.MerchantValidated(session, response.payment_data.merchant_session);

			session.amount = parseFloat(settings.total/100) * 100;
			session.onshippingmethodselected = event => {

				const shippingMethod = event.shippingMethod;
				session.shippingMethod = shippingMethod.identifier;

				const baseTotal = settings.total/100 ;
				let currentShippingCost = shippingMethod.amount;

				const newTotalAmount = parseFloat(baseTotal) + parseFloat(currentShippingCost);
				session.amount = newTotalAmount * 100;

				const update = {
					newTotal: {
						label: 'Total',
						amount: newTotalAmount
					},
					newLineItems: [
						{
							label: shippingMethod.label,
							type: 'final',
							amount: currentShippingCost
						}
					]
				};

				session.completeShippingMethodSelection(update);
			};
			session.begin();
		},
		MerchantValidated: function(session, merchant_session) {
			session.onvalidatemerchant = event => {
				try {
					session.completeMerchantValidation(merchant_session);
				} catch (err) {
					apple_pay.CancelOrder();
				}
			}
		}
	}


	useEffect(() => {
		const handlePaymentProcessing = () => {

			console.log("*****" + order_id);

			apple_pay_PlaceOrderWithDummyData().then((response) => {
				if (response.payment_data.success === false) {
					console.log("HÂAAAA")

				//	apple_pay_CancelOrder({'order_id': response.order_id, 'payment_id': response.payment_data.payment_id});
					return;
				}

				console.log("apple_pay_PlaceOrderWithDummyData");
				console.log(response);

				settings.total = response.total
				apple_pay.OrderPaymentCreated(response);

			});

			return {
				type: 'error',
					message: "pppppppp"
			}

		}

		const unsubscribeAfterProcessing = onPaymentProcessing(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };
	}, [
		onPaymentProcessing,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS
	]);









	/*useEffect(() => {
		const handlePaymentProcessing = async ({processingResponse: {paymentDetails}}) => {
			var apple_pay_Session_status;
			let result = {};

			console.log("onCheckoutSuccess")
			console.log(paymentDetails);

			if( typeof paymentDetails.redirect ){
				const url_data = new URL(paymentDetails.redirect);
				order_id = url_data.searchParams.get('order-received');
			}

			console.log("#####" + order_id);

/*
			await CheckPaymentOnPaymentAuthorized().then((res) => {
				window.location = session.return_url
			});


			return {"result":"success"};

			function CheckPaymentOnPaymentAuthorized() {
				return new Promise((resolve, reject) => {
					session.onpaymentauthorized = event => {
						let event_data = event.payment;

						let data = {
							'order_id': session.order_id,
							'shipping' : event_data.shippingContact,
							'billing' : event_data.billingContact,
							'shipping_method' : session.shippingMethod
						};

						 apple_pay_UpdateOrder(data).then( (result_order) => {
							data = {
								'action': 'applepay_update_payment',
								'post_type': 'POST',
								'payment_id': session.payment_id,
								'payment_token': event.payment.token,
								'order_id': session.order_id,
								'amount': session.amount
							}

							console.log("apple_pay_UpdateOrder")
							console.log(result_order)

							apple_pay_Payment(data).then((result_payment) => {

								console.log("apple_pay_Payment")
								console.log(result_payment)

								apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;

								if (result_payment.success !== true) {
									apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
									apple_pay.CancelOrder()
								}
								session.completePayment({"status": apple_pay_Session_status})
								resolve();
							});
						});
					}
				})
			}
		}
		const unsubscribeAfterProcessing = onCheckoutSuccess(handlePaymentProcessing);
		return () => { unsubscribeAfterProcessing(); };
	}, [onCheckoutSuccess]);
*/
	useEffect(() => {
		jQuery('apple-pay-button').on("click", (e) => {
			e.preventDefault();
			e.stopImmediatePropagation();
			apple_pay.CreateSession();
			apple_pay.CancelOrder();
			props.onSubmit();
		});
	},[]);

	return (<>
		<div id="apple-pay-button-wrapper">
			<apple-pay-button
				buttonstyle="black"
				type="pay"
				locale={settings?.payplug_locale}
			></apple-pay-button>
		</div>
	</>)
}

export default ApplePayCart;
