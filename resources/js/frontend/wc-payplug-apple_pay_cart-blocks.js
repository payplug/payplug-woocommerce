import React, { useEffect, MouseEvent } from 'react';
import { getSetting } from '@woocommerce/settings';
const settings = getSetting( 'apple_pay_data', {} );


import {
	apple_pay_CancelOrder,
	apple_pay_getShippings, apple_pay_Payment, apple_pay_PlaceOrderWithDummyData,
	apple_pay_UpdateOrder,
	apple_pay_updateOrderTotal
} from './helper/wc-payplug-apple_pay-requests';

const ApplePayCart = ( props ) =>{

	//const {onClick} = props;

	console.log(props);
	console.log(props.billing.cartTotal);

	let $apple_pay_button = jQuery('apple-pay-button');
	let session = null;
	let response = null;

	let apple_pay = {
		load_order_total: false,
		init: async function () {
			/*await apple_pay_updateOrderTotal().then((response) => {
				settings.total = response.data;

			})*/

			apple_pay.getShippings();
			apple_pay.ProcessCheckout();
		},
		getShippings: function () {
			apple_pay_getShippings().then((response) => {
				if (response.data.length === 0) {
					//$apple_pay_button.remove();
					settings.carriers = [];
					return;
				}

				//some extra validations which i don't quite understand
				settings.carriers = response.data;
			});

		},
		ProcessCheckout: function () {
			//apple_pay.CreateSession();
			//apple_pay.CancelOrder()
			apple_pay.PaymentCompleted();

			//loading layer
			//	jQuery('.woocommerce').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } })

			apple_pay_PlaceOrderWithDummyData().then((response) => {

				if (response.success === false) {
					apple_pay.showError(response.data.msg, "error");
					apple_pay.handle_process_error(response.data.order_id);
					return;
				}

				//TODO ver este serviço
				settings.total = response.total
				apple_pay.OrderPaymentCreated(response);


			});

		},
		OrderPaymentCreated: function (response) {
			if ('success' !== response.payment_data.result) {
				var error_messages = response.messages || ''
				apple_pay.CancelOrder(error_messages);
			}

			apple_pay.BeginSession(response)
		},
		CreateSession: function () {

			console.log(settings.total);
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
				'shippingMethods': settings.carriers,
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
		CancelOrder: function (message) {
			session.oncancel = event => {

				//TODO:: check this
				//$('apple-pay-button').removeClass("isDisabled")
				apple_pay.cancel_order_request(session.order_id, session.payment_id);

			}
		},
		PaymentCompleted: function () {
			session.onpaymentauthorized = async event => {
				let data = event.payment;

				await apple_pay_UpdateOrder({
					'order_id': session.order_id,
					'shipping': data.shippingContact,
					'billing': data.billingContact,
					'shipping_method': session.shippingMethod

				}).then(async (response) => {
					await apple_pay_Payment({
						'action': 'applepay_update_payment',
						'post_type': 'POST',
						'payment_id': session.payment_id,
						'payment_token': event.payment.token,
						'order_id': session.order_id,
						'amount': session.amount
					}).then((response) => {

						//jQuery('woocommerce').unblock();
						var apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;

						if (response.success !== true) {
							apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
							apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.')
						}

						session.completePayment({"status": apple_pay_Session_status})
						window.location = session.return_url


					})


				}).catch(() => {
					apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.');
				})
				/*
									jQuery.post({
										'url': settings.ajax_url_update_applepay_order,
										'data': {
											'order_id': session.order_id,
											'shipping': data.shippingContact,
											'billing': data.billingContact,
											'shipping_method': session.shippingMethod
										}
									}).done(function (response) {

										jQuery.ajax({
											url: apple_pay_params.ajax_url_update_applepay_payment,
											type: 'post',
											data: {
												'action': 'applepay_update_payment',
												'post_type': 'POST',
												'payment_id': session.payment_id,
												'payment_token': event.payment.token,
												'order_id': session.order_id,
												'amount': session.amount
											},
											dataType: 'json',
											success: function (res) {
												jQuery('woocommerce').unblock();
												var apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;

												if (res.success !== true) {
													apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
													apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.')
												}
												session.completePayment({"status": apple_pay_Session_status})
												window.location = session.return_url
											},
											error: function (err) {
												apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.')
											},
										});

									}).fail(function (response) {
										apple_pay.CancelOrder('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.');
									})
				*/
				//$('apple-pay-button').addClass("isDisabled")

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
		handle_process_error: function (order_id = null) {
			//$('apple-pay-button').removeClass("isDisabled")
			apple_pay.cancel_order_request(order_id, null);
		},
		cancel_order_request: function (order_id, payment_id = null) {
			apple_pay_CancelOrder({'order_id': order_id, 'payment_id': payment_id}).then(() => {
				//	apple_pay.showError(response.data.message, "info");
				apple_pay.updateOrderTotal();
			})
		},
	}

	useEffect(() => {
		console.log(" - ready - ");
		jQuery(async function ($) {
			response = await apple_pay_updateOrderTotal();
		});


	},[]);


	$apple_pay_button.on("click", (e) => {
		e.preventDefault();
		console.log("clicked");
		apple_pay.getShippings();
		apple_pay.CreateSession();
		apple_pay.CancelOrder()

		apple_pay.ProcessCheckout();

	});


	let build_fakeSession = function(){
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
				"amount": "10.00"
			},
			'applicationData': btoa(JSON.stringify({
				'apple_pay_domain': settings.payplug_apple_pay_domain
			}))
		}

		console.log("--->")
		session = new ApplePaySession(3, request)
		console.log(session);
		console.log("--->")

	}



	return (
		<>
			<div id="apple-pay-button-wrapper">
				<apple-pay-button
					buttonstyle="black"
					type="pay"
					locale={settings?.locale}
				></apple-pay-button>
			</div>
		</>
	)
}

export default ApplePayCart;




