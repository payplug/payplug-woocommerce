import React from 'react';
import { getSetting } from '@woocommerce/settings';
import { apple_pay_CancelOrder, apple_pay_Payment, apple_pay_PlaceOrderWithDummyData, apple_pay_UpdateOrder} from "./helper/wc-payplug-apple_pay-requests";
const settings = getSetting( 'apple_pay_data', {} );

const ApplePayCart = ( props ) =>{

	let session = null;
	let apple_pay_Session_status = null;
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
					"amount": settings.total_amount/100
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

				request.shippingMethods = settings.payplug_carriers;

			}
			session = new ApplePaySession(3, request);

		},
		CancelOrder: function () {
			session.oncancel = event => {
				apple_pay_CancelOrder({'order_id': session.order_id, 'payment_id': session.payment_id}).then(() => {
					enabled_button();
				});
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
		},
		AddErrorMessage: function(message){
			apple_pay_wrapper.append(jQuery('<div class="apple-pay-cart-notice"></div>').append("<span>" + message + "</span>"));
		},
		DeleteErrorMessage: function(){
			setTimeout(function () {
				jQuery('.apple-pay-cart-notice').contents().first().remove();
			}, 4000);
		}
	}

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
						'amount': session.amount/100
					}

					apple_pay_Payment(data).then((result_payment) => {

						apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;

						if (result_payment.success !== true) {
							apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
							apple_pay.AddErrorMessage(result_payment.data.message)
							apple_pay.DeleteErrorMessage();
							apple_pay.CancelOrder()
						}
						session.completePayment({"status": apple_pay_Session_status})
						resolve();
					});
				});
			}
		})
	}

	jQuery(function ($) {
		jQuery('apple-pay-button').on("click", (e) => {
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
					window.location = session.return_url
				});

			});
		});
	});

	function disabled_button(){
		jQuery('apple-pay-button').addClass("isDisabled");
	}

	function enabled_button(){
		jQuery('apple-pay-button').removeClass("isDisabled");
	}

	return (<> </>);
}

export default ApplePayCart;
