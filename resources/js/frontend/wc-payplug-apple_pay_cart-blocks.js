import { useEffect, useRef } from 'react';
import { getSetting } from '@woocommerce/settings';
import { apple_pay_CancelOrder, apple_pay_Payment, apple_pay_PlaceOrderWithDummyData, apple_pay_UpdateOrder, apple_pay_get_shippings } from "./helper/wc-payplug-apple_pay-requests";
const settings = getSetting( 'apple_pay_data', {} );

const ApplePayCart = ( props ) =>{

	// A plain local variable would be reset on every re-render, but it's read from
	// async callbacks/DOM handlers that can fire well after a re-render has happened.
	const sessionRef = useRef(null);
	let apple_pay_Session_status = null;
	// Same reasoning as sessionRef: fetched asynchronously in an effect below, then read
	// later from CreateSession(), which can run after a re-render has reset a plain variable.
	const carriersRef = useRef([]);
	const apple_pay_wrapper = jQuery("#apple-pay-button-wrapper");
	const apple_pay = {
		load_order_total: false,
		OrderPaymentCreated: function (response) {
			if ('success' !== response.payment_data.result) {
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
					"amount": parseFloat(settings.total_amount/100)
				},
				'applicationData': btoa(JSON.stringify({
					'apple_pay_domain': settings.apple_pay_domain
				})),
				'requiredBillingContactFields' : [
					'postalAddress',
					'name',
				],
			}
			request.requiredShippingContactFields = [
				"postalAddress",
				"name",
				"phone",
				"email"
			];

			if (settings.payplug_apple_pay_shipping_required) {

				request.shippingMethods = carriersRef.current;

			}
			sessionRef.current = new ApplePaySession(4, request);

		},
		CancelOrder: function () {
			sessionRef.current.oncancel = event => {
				apple_pay_CancelOrder({'order_id': sessionRef.current.order_id, 'payment_id': sessionRef.current.payment_id}).then(() => {
					enabled_button();
				});
			}
		},
		BeginSession: function (response) {
			const session = sessionRef.current;
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
						label: 'Apple Pay',
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
		},
		AddErrorMessage: function(message){
			apple_pay_wrapper.append(jQuery('<div class="apple-pay-cart-notice"></div>').append(jQuery('<span></span>').text(message)));
		},
		DeleteErrorMessage: function(){
			setTimeout(function () {
				jQuery('.apple-pay-cart-notice').remove();
			}, 4000);
		}
	}

	function CheckPaymentOnPaymentAuthorized() {
		return new Promise((resolve, reject) => {
			sessionRef.current.onpaymentauthorized = event => {
				let event_data = event.payment;

				let data = {
					'order_id': sessionRef.current.order_id,
					'shipping' : event_data.shippingContact,
					'billing' : event_data.billingContact,
					'shipping_method' : sessionRef.current.shippingMethod
				};

				apple_pay_UpdateOrder(data).then( (result_order) => {
					data = {
						'action': 'applepay_update_payment',
						'post_type': 'POST',
						'payment_id': sessionRef.current.payment_id,
						'payment_token': event.payment.token,
						'order_id': sessionRef.current.order_id,
						'amount': sessionRef.current.amount / 100
					}

					apple_pay_Payment(data).then((result_payment) => {

						apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;

						if (result_payment.success !== true) {
							apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
							apple_pay.AddErrorMessage(result_payment.data.message)
							apple_pay.DeleteErrorMessage();
							apple_pay.CancelOrder()
						}
						sessionRef.current.completePayment({"status": apple_pay_Session_status})
						resolve();
					});
				});
			}
		})
	}

	useEffect(() => {
		apple_pay_get_shippings().then((result_shippings) => {
			carriersRef.current = result_shippings.data;
		});
	}, []);

	function disabled_button(){
		jQuery('apple-pay-button').addClass("isDisabled");
	}

	function enabled_button(){
		jQuery('apple-pay-button').removeClass("isDisabled");
	}

	useEffect(() => {
		const btn = document.getElementById('apple-pay-button');
		const onApplePayButtonClick = (e) => {
			e.preventDefault();
			e.stopImmediatePropagation();
			disabled_button();

			apple_pay.CreateSession();
			apple_pay.CancelOrder();
			apple_pay_PlaceOrderWithDummyData().then(async (response) => {
				if (response.success === false) {
					apple_pay.AddErrorMessage(response.data.message)
					apple_pay.DeleteErrorMessage();
					enabled_button();
					return;
				}
				settings.total = response.total
				apple_pay.OrderPaymentCreated(response);

				await CheckPaymentOnPaymentAuthorized().then((res) => {
					window.location = sessionRef.current.return_url
				});

			});
		};
		if (btn) {
			btn.addEventListener('click', onApplePayButtonClick);
		}
		return () => {
			if (btn) {
				btn.removeEventListener('click', onApplePayButtonClick);
			}
		};
	}, []);

	return (<> </>);
}

export default ApplePayCart;
