/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/frontend/helper/wc-payplug-apple_pay-requests.js":
/*!***********************************************************************!*\
  !*** ./resources/js/frontend/helper/wc-payplug-apple_pay-requests.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   apple_pay_CancelOrder: () => (/* binding */ apple_pay_CancelOrder),
/* harmony export */   apple_pay_Payment: () => (/* binding */ apple_pay_Payment),
/* harmony export */   apple_pay_PlaceOrderWithDummyData: () => (/* binding */ apple_pay_PlaceOrderWithDummyData),
/* harmony export */   apple_pay_UpdateOrder: () => (/* binding */ apple_pay_UpdateOrder),
/* harmony export */   apple_pay_update_payment: () => (/* binding */ apple_pay_update_payment),
/* harmony export */   getPayment: () => (/* binding */ getPayment)
/* harmony export */ });
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! jquery */ "jquery");
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_1__);


const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('apple_pay_data', {});
const getPayment = (props, order_id) => {
  const data = getPaymentData(props);
  return new Promise((resolve, reject) => {
    return jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.payplug_create_intent_payment
    }).success(function (response) {
      resolve(response);
    }).error(function (error) {
      reject(error);
    });
  });
  function getPaymentData(props) {
    return {
      "order_id": order_id,
      "woocommerce-process-checkout-nonce": settings.wp_nonce,
      "gateway": "apple_pay"
    };
  }
};
const apple_pay_update_payment = data => {
  return new Promise((resolve, reject) => {
    jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.ajax_url_applepay_update_payment
    }).success(function (response) {
      resolve(response);
    }).error(function (xhr, status, error) {
      reject(error); // NOT WORKING!!
    });
  });
};
const apple_pay_UpdateOrder = data => {
  return new Promise((resolve, reject) => {
    jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.ajax_url_update_applepay_order
    }).success(function (response) {
      resolve(response);
    }).error(function (xhr, status, error) {
      reject(error); // NOT WORKING!!
    });
  });
};
const apple_pay_Payment = data => {
  return new Promise((resolve, reject) => {
    jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.ajax_url_update_applepay_payment
    }).success(function (response) {
      resolve(response);
    }).error(function (xhr, status, error) {
      reject(error); // NOT WORKING!!
    });
  });
};
const apple_pay_PlaceOrderWithDummyData = data => {
  return new Promise((resolve, reject) => {
    jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.ajax_url_place_order_with_dummy_data
    }).success(function (response) {
      resolve(response);
    }).error(function (xhr, status, error) {
      reject(error); // NOT WORKING!!
    });
  });
};
const apple_pay_CancelOrder = data => {
  return new Promise((resolve, reject) => {
    jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.ajax_url_applepay_cancel_order
    }).success(function (response) {
      resolve(response);
    }).error(function (xhr, status, error) {
      reject(error); // NOT WORKING!!
    });
  });
};

/***/ }),

/***/ "./resources/js/frontend/wc-payplug-apple_pay_cart-blocks.js":
/*!*******************************************************************!*\
  !*** ./resources/js/frontend/wc-payplug-apple_pay_cart-blocks.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./helper/wc-payplug-apple_pay-requests */ "./resources/js/frontend/helper/wc-payplug-apple_pay-requests.js");




const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__.getSetting)('apple_pay_data', {});
const ApplePayCart = props => {
  let session = null;
  let apple_pay_Session_status = null;
  const apple_pay_btn = jQuery('apple-pay-button');
  const apple_pay_wrapper = jQuery("#apple-pay-button-wrapper");
  const apple_pay = {
    load_order_total: false,
    OrderPaymentCreated: function (response) {
      if ('success' !== response.payment_data.result) {
        apple_pay.CancelOrder();
      }
      apple_pay.BeginSession(response);
    },
    CreateSession: function () {
      const request = {
        "countryCode": settings.countryCode,
        "currencyCode": settings.currencyCode,
        "merchantCapabilities": ["supports3DS"],
        "supportedNetworks": ["visa", "masterCard"],
        "total": {
          "label": "Apple Pay",
          "type": "final",
          "amount": settings.total_amount / 100
        },
        'applicationData': btoa(JSON.stringify({
          'apple_pay_domain': settings.apple_pay_domain
        })),
        'requiredBillingContactFields': ['postalAddress', 'name']
      };
      if (settings.payplug_apple_pay_shipping_required) {
        request.requiredShippingContactFields = ["postalAddress", "name", "phone", "email"];
        request.requiredBillingContactFields = ['postalAddress', 'name'];
        request.shippingMethods = settings.payplug_carriers;
      }
      session = new ApplePaySession(3, request);
    },
    CancelOrder: function () {
      session.oncancel = event => {
        (0,_helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_3__.apple_pay_CancelOrder)({
          'order_id': session.order_id,
          'payment_id': session.payment_id
        }).then(() => {
          enabled_button();
        });
      };
    },
    BeginSession: function (response) {
      session.payment_id = response.payment_data.payment_id;
      session.order_id = response.order_id;
      session.cancel_url = response.payment_data.cancel_url;
      session.return_url = response.payment_data.return_url;
      apple_pay.MerchantValidated(session, response.payment_data.merchant_session);
      session.amount = parseFloat(settings.total / 100) * 100;
      session.onshippingmethodselected = event => {
        const shippingMethod = event.shippingMethod;
        session.shippingMethod = shippingMethod.identifier;
        const baseTotal = settings.total / 100;
        let currentShippingCost = shippingMethod.amount;
        const newTotalAmount = parseFloat(baseTotal) + parseFloat(currentShippingCost);
        session.amount = newTotalAmount * 100;
        const update = {
          newTotal: {
            label: 'Total',
            amount: newTotalAmount
          },
          newLineItems: [{
            label: shippingMethod.label,
            type: 'final',
            amount: currentShippingCost
          }]
        };
        session.completeShippingMethodSelection(update);
      };
      session.begin();
    },
    MerchantValidated: function (session, merchant_session) {
      session.onvalidatemerchant = event => {
        try {
          session.completeMerchantValidation(merchant_session);
        } catch (err) {
          apple_pay.CancelOrder();
        }
      };
    },
    AddErrorMessage: function (message) {
      apple_pay_wrapper.append(jQuery('<div class="apple-pay-cart-notice"></div>').append("<span>" + message + "</span>"));
    },
    DeleteErrorMessage: function () {
      setTimeout(function () {
        jQuery('.apple-pay-cart-notice').contents().first().remove();
      }, 4000);
    }
  };
  function CheckPaymentOnPaymentAuthorized() {
    return new Promise((resolve, reject) => {
      session.onpaymentauthorized = event => {
        let event_data = event.payment;
        let data = {
          'order_id': session.order_id,
          'shipping': event_data.shippingContact,
          'billing': event_data.billingContact,
          'shipping_method': session.shippingMethod
        };
        (0,_helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_3__.apple_pay_UpdateOrder)(data).then(result_order => {
          data = {
            'action': 'applepay_update_payment',
            'post_type': 'POST',
            'payment_id': session.payment_id,
            'payment_token': event.payment.token,
            'order_id': session.order_id,
            'amount': session.amount
          };
          (0,_helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_3__.apple_pay_Payment)(data).then(result_payment => {
            apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;
            if (result_payment.success !== true) {
              apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
              apple_pay.AddErrorMessage(result_payment.data.message);
              apple_pay.DeleteErrorMessage();
              apple_pay.CancelOrder();
            }
            session.completePayment({
              "status": apple_pay_Session_status
            });
            resolve();
          });
        });
      };
    });
  }
  jQuery(function ($) {
    jQuery('apple-pay-button').on("click", e => {
      e.preventDefault();
      e.stopImmediatePropagation();
      disabled_button();
      apple_pay.CreateSession();
      apple_pay.CancelOrder();
      (0,_helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_3__.apple_pay_PlaceOrderWithDummyData)().then(async response => {
        if (response.success === false) {
          apple_pay.AddErrorMessage(response.data.message);
          apple_pay.DeleteErrorMessage();
          enabled_button();
          return;
        }
        settings.total = response.total;
        apple_pay.OrderPaymentCreated(response);
        await CheckPaymentOnPaymentAuthorized().then(res => {
          window.location = session.return_url;
        });
      });
    });
  });
  function disabled_button() {
    apple_pay_btn.addClass("isDisabled");
  }
  function enabled_button() {
    apple_pay_btn.removeClass("isDisabled");
  }
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, " ");
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ApplePayCart);

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "jquery":
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/***/ ((module) => {

module.exports = window["jQuery"];

/***/ }),

/***/ "@woocommerce/blocks-registry":
/*!******************************************!*\
  !*** external ["wc","wcBlocksRegistry"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksRegistry"];

/***/ }),

/***/ "@woocommerce/settings":
/*!************************************!*\
  !*** external ["wc","wcSettings"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcSettings"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
/*!**************************************************************!*\
  !*** ./resources/js/frontend/wc-payplug-apple_pay-blocks.js ***!
  \**************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @woocommerce/blocks-registry */ "@woocommerce/blocks-registry");
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./helper/wc-payplug-apple_pay-requests */ "./resources/js/frontend/helper/wc-payplug-apple_pay-requests.js");
/* harmony import */ var _wc_payplug_apple_pay_cart_blocks__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./wc-payplug-apple_pay_cart-blocks */ "./resources/js/frontend/wc-payplug-apple_pay_cart-blocks.js");









const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__.getSetting)('apple_pay_data', {});
const defaultLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Gateway method title', 'payplug');
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_2__.decodeEntities)(settings?.title) || defaultLabel;
const Content = props => {
  const {
    eventRegistration,
    emitResponse
  } = props;
  const {
    onPaymentSetup,
    onCheckoutSuccess
  } = eventRegistration;
  const {
    CHECKOUT_STORE_KEY
  } = window.wc.wcBlocksData;
  const order_id = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select(CHECKOUT_STORE_KEY).getOrderId());
  let session = null;
  (0,react__WEBPACK_IMPORTED_MODULE_6__.useEffect)(() => {
    jQuery(function ($) {
      let element = $("form .wp-block-woocommerce-checkout-actions-block .wc-block-components-button");
      element.on("click", async e => {
        e.preventDefault();
        apple_pay.CreateSession();
        apple_pay.CancelOrder();
      });
    });
  }, []);
  (0,react__WEBPACK_IMPORTED_MODULE_6__.useEffect)(() => {
    const handlePaymentProcessing = async () => {
      await (0,_helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_7__.getPayment)(props, order_id).then(async response => {
        await apple_pay.BeginSession(response);
      }).then(async response => {
        return {
          type: "success"
        };
      });
    };
    const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
    return () => {
      unsubscribeAfterProcessing();
    };
  }, [onPaymentSetup, emitResponse.noticeContexts.PAYMENTS, emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS]);
  (0,react__WEBPACK_IMPORTED_MODULE_6__.useEffect)(() => {
    const handlePaymentProcessing = async ({
      processingResponse: {
        paymentDetails
      }
    }) => {
      var apple_pay_Session_status;
      let result = {};
      await CheckPaymentOnPaymentAuthorized().then(() => {
        result = {
          type: "success",
          "redirectUrl": session.return_url
        };
      });
      return result;
      function CheckPaymentOnPaymentAuthorized() {
        return new Promise((resolve, reject) => {
          session.onpaymentauthorized = async event => {
            let data = {
              'action': 'applepay_update_payment',
              'post_type': 'POST',
              'payment_id': session.payment_id,
              'payment_token': event.payment.token,
              'order_id': session.order_id
            };
            await (0,_helper_wc_payplug_apple_pay_requests__WEBPACK_IMPORTED_MODULE_7__.apple_pay_update_payment)(data).then(res => {
              apple_pay_Session_status = ApplePaySession.STATUS_SUCCESS;
              if (res.success !== true) {
                apple_pay_Session_status = ApplePaySession.STATUS_FAILURE;
              }
              session.completePayment({
                "status": apple_pay_Session_status
              });
              resolve();
            });
          };
        });
      }
    };
    const unsubscribeAfterProcessing = onCheckoutSuccess(handlePaymentProcessing);
    return () => {
      unsubscribeAfterProcessing();
    };
  }, [onCheckoutSuccess]);
  let apple_pay = {
    CreateSession: function () {
      const request = {
        "countryCode": settings.payplug_countryCode,
        "currencyCode": settings.payplug_currencyCode,
        "merchantCapabilities": ["supports3DS"],
        "supportedNetworks": ["visa", "masterCard"],
        "total": {
          "label": "Apple Pay",
          "type": "final",
          "amount": props.billing.cartTotal.value / 100
        },
        'applicationData': btoa(JSON.stringify({
          'apple_pay_domain': settings.payplug_apple_pay_domain
        }))
      };
      session = new ApplePaySession(3, request);
    },
    CancelOrder: function () {
      session.oncancel = event => {
        window.location = session.cancel_url;
      };
    },
    BeginSession: function (response) {
      session.payment_id = response.data.payment_id;
      session.order_id = order_id;
      session.cancel_url = response.data.cancel;
      session.return_url = response.data.redirect;
      apple_pay.MerchantValidated(session, response.data.merchant_session);
      session.begin();
    },
    MerchantValidated: function (session, merchant_session) {
      session.onvalidatemerchant = async event => {
        try {
          session.completeMerchantValidation(merchant_session);
        } catch (err) {
          alert(err);
        }
      };
    }
  };
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null);
};
/**
 * Label component
 *
 */
const Label = () => {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    style: {
      width: '100%'
    }
  }, label, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Icon, null));
};
const Icon = () => {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    src: settings?.icon.src,
    alt: settings?.icon.icon_alt,
    className: "payplug-payment-icon",
    style: {
      float: 'right'
    }
  });
};

/**
 * Payplug payment method config object.
 */
const ApplePay = {
  name: "apple_pay",
  label: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Label, null),
  content: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, null),
  edit: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, null),
  canMakePayment: () => {
    return true;
  },
  ariaLabel: label,
  supports: {
    features: settings.supports
  }
};

/**
 *
 * @param props
 * @returns {JSX.Element}
 * @Content for express payment method
 */
const ExpressContent = props => {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "apple-pay-button-wrapper"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("apple-pay-button", {
    buttonstyle: "black",
    type: "pay",
    locale: settings?.payplug_locale
  })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wc_payplug_apple_pay_cart_blocks__WEBPACK_IMPORTED_MODULE_8__["default"], props));
};
const ExpressApplePay = {
  name: "apple_pay",
  content: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(ExpressContent, null),
  edit: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(ExpressContent, null),
  canMakePayment: data => {
    settings.payplug_apple_pay_shipping_required = data.cartNeedsShipping;
    settings.total_amount = data.cartTotals.total_price;
    if (!settings?.is_cart) {
      return false;
    }
    if (!data.cartNeedsShipping) {
      return true;
    }
    let payplug_authorized_carriers = settings?.payplug_authorized_carriers;
    let selected_shipping = data.selectedShippingMethods[0].split(":");
    let authorized = false;
    payplug_authorized_carriers.forEach(function (item) {
      if (selected_shipping[0] === item) {
        authorized = true;
      }
    });
    return authorized;
  },
  paymentMethodId: "apple_pay"
};
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_5__.registerExpressPaymentMethod)(ExpressApplePay);
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_5__.registerPaymentMethod)(ApplePay);
/******/ })()
;
//# sourceMappingURL=wc-payplug-apple_pay-blocks.js.map