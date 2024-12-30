/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/frontend/helper/wc-payplug-requests.js":
/*!*************************************************************!*\
  !*** ./resources/js/frontend/helper/wc-payplug-requests.js ***!
  \*************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   check_payment: () => (/* binding */ check_payment),
/* harmony export */   getPayment: () => (/* binding */ getPayment)
/* harmony export */ });
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! jquery */ "jquery");
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_1__);


const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('payplug_data', {});
const getPayment = (props, _settings, order_id) => {
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
      "gateway": _settings?.payment_method
    };
  }
};
const check_payment = data => {
  return new Promise((resolve, reject) => {
    jquery__WEBPACK_IMPORTED_MODULE_1___default().ajax({
      type: 'POST',
      data: data,
      url: settings.payplug_integrated_payment_check_payment_url
    }).success(function (response) {
      resolve(response);
    }).error(function (error) {
      reject(error); // NOT WORKING!!
    });
  });
};

/***/ }),

/***/ "./resources/js/frontend/wc-payplug-integratedPayment-blocks.js":
/*!**********************************************************************!*\
  !*** ./resources/js/frontend/wc-payplug-integratedPayment-blocks.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helper_wc_payplug_requests__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./helper/wc-payplug-requests */ "./resources/js/frontend/helper/wc-payplug-requests.js");





const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_1__.getSetting)('payplug_data', {});
let saved_card = false;
const IntegratedPayment = ({
  props: props
}) => {
  const {
    eventRegistration,
    emitResponse
  } = props;
  saved_card = props.shouldSavePayment;
  const {
    onCheckoutValidation,
    onPaymentSetup,
    onCheckoutSuccess
  } = eventRegistration;
  const {
    PAYMENT_STORE_KEY,
    CHECKOUT_STORE_KEY
  } = window.wc.wcBlocksData;
  const order_id = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select(CHECKOUT_STORE_KEY).getOrderId());
  (0,react__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    ObjIntegratedPayment.api = new Payplug.IntegratedPayment(settings?.mode == 1 ? false : true);
    ObjIntegratedPayment.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX);
    ObjIntegratedPayment.form.cardHolder = ObjIntegratedPayment.api.cardHolder(document.querySelector('.cardHolder-input-container'), {
      default: ObjIntegratedPayment.inputStyle.default,
      placeholder: settings?.payplug_integrated_payment_cardholder
    });
    ObjIntegratedPayment.form.pan = ObjIntegratedPayment.api.cardNumber(document.querySelector('.pan-input-container'), {
      default: ObjIntegratedPayment.inputStyle.default,
      placeholder: settings?.payplug_integrated_payment_card_number
    });
    ObjIntegratedPayment.form.cvv = ObjIntegratedPayment.api.cvv(document.querySelector('.cvv-input-container'), {
      default: ObjIntegratedPayment.inputStyle.default,
      placeholder: settings?.payplug_integrated_payment_cvv
    });
    ObjIntegratedPayment.form.exp = ObjIntegratedPayment.api.expiration(document.querySelector('.exp-input-container'), {
      default: ObjIntegratedPayment.inputStyle.default,
      placeholder: settings?.payplug_integrated_payment_expiration_date
    });
    ObjIntegratedPayment.scheme = ObjIntegratedPayment.api.getSupportedSchemes();
    fieldValidation();
  }, []);
  (0,react__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const onValidation = async () => {
      ObjIntegratedPayment.api.validateForm();
      let isValid = false;
      await validateForm().then(response => {
        isValid = response;
      });
      if (!isValid) {
        return {
          errorMessage: settings?.payplug_invalid_form
        };
      } else {
        return isValid;
      }
      function validateForm() {
        return new Promise(async (resolve, reject) => {
          await ObjIntegratedPayment.api.onValidateForm(({
            isFormValid
          }) => {
            resolve(isFormValid);
          });
        });
      }
    };
    const unsubscribeAfterProcessing = onCheckoutValidation(onValidation);
    return () => {
      unsubscribeAfterProcessing();
    };
  }, [onCheckoutValidation]);
  (0,react__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const handlePaymentProcessing = async () => {
      let data = {};
      await (0,_helper_wc_payplug_requests__WEBPACK_IMPORTED_MODULE_4__.getPayment)(props, settings, order_id).then(async response => {
        ObjIntegratedPayment.paymentId = response.data.payment_id;
        data = {
          'payment_id': response.data.payment_id
        };
        ObjIntegratedPayment.return_url = response.data.redirect;
        try {
          onCompleteEvent();
          await ObjIntegratedPayment.api.pay(ObjIntegratedPayment.paymentId, Payplug.Scheme.AUTO, {
            save_card: saved_card
          });
        } catch (error) {
          return {
            type: 'error',
            message: error.message
          };
        }
      });
      function onCompleteEvent() {
        ObjIntegratedPayment.api.onCompleted(function (event) {
          (0,_helper_wc_payplug_requests__WEBPACK_IMPORTED_MODULE_4__.check_payment)({
            'payment_id': event.token
          }).then(res => {
            window.location = ObjIntegratedPayment.return_url;
          });
        });
      }
    };
    const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
    return () => {
      unsubscribeAfterProcessing();
    };
  }, [onPaymentSetup]);
  const fieldValidation = () => {
    jQuery.each(ObjIntegratedPayment.form, function (key, field) {
      field.onChange(function (err) {
        if (err.error) {
          document.querySelector(".payplug.IntegratedPayment_error.-" + key).classList.remove("-hide");
          document.querySelector('.' + key + '-input-container').classList.add("-invalid");
          if (err.error.name === "FIELD_EMPTY") {
            document.querySelector(".payplug.IntegratedPayment_error.-" + key).querySelector(".emptyField").classList.remove("-hide");
            document.querySelector(".payplug.IntegratedPayment_error.-" + key).querySelector(".invalidField").classList.add("-hide");
          } else {
            document.querySelector(".payplug.IntegratedPayment_error.-" + key).querySelector(".invalidField").classList.remove("-hide");
            document.querySelector(".payplug.IntegratedPayment_error.-" + key).querySelector(".emptyField").classList.add("-hide");
          }
        } else {
          document.querySelector(".payplug.IntegratedPayment_error.-" + key).classList.add("-hide");
          document.querySelector('.' + key + '-input-container').classList.remove("-invalid");
          document.querySelector(".payplug.IntegratedPayment_error.-" + key).querySelector(".invalidField").classList.add("-hide");
          document.querySelector(".payplug.IntegratedPayment_error.-" + key).querySelector(".emptyField").classList.add("-hide");
          ObjIntegratedPayment.fieldsValid[key] = true;
          ObjIntegratedPayment.fieldsEmpty[key] = false;
        }
      });
    });
  };
  var ObjIntegratedPayment = {
    cartId: null,
    paymentId: null,
    paymentOptionId: null,
    form: {},
    checkoutForm: null,
    api: null,
    integratedPayment: null,
    token: null,
    notValid: true,
    fieldsValid: {
      cardHolder: false,
      pan: false,
      cvv: false,
      exp: false
    },
    fieldsEmpty: {
      cardHolder: true,
      pan: true,
      cvv: true,
      exp: true
    },
    inputStyle: {
      default: {
        color: '#2B343D',
        fontFamily: 'Poppins, sans-serif',
        fontSize: '14px',
        textAlign: 'left',
        '::placeholder': {
          color: '#969a9f'
        },
        ':focus': {
          color: '#2B343D'
        }
      },
      invalid: {
        color: '#E91932'
      }
    },
    save_card: false,
    scheme: null,
    query: null,
    submit: null,
    order_review: false,
    return_url: null
  };
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "payplug-integrated-payment",
    className: "payplug IntegratedPayment -loaded"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -cardHolder cardHolder-input-container",
    "data-e2e-name": "cardHolder"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_error -cardHolder -hide"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide invalidField",
    "data-e2e-error": "invalidField"
  }, settings?.payplug_integrated_payment_cardHolder_error), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide emptyField",
    "data-e2e-error": "paymentError"
  }, settings?.payplug_integrated_payment_empty)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -scheme"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, settings?.payplug_integrated_payment_your_card), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_schemes"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    className: "payplug IntegratedPayment_scheme -cb"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "radio",
    name: "schemeOptions",
    value: "cb"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    className: "payplug IntegratedPayment_scheme -visa"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "radio",
    name: "schemeOptions",
    value: "visa"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    className: "payplug IntegratedPayment_scheme -mastercard"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "radio",
    name: "schemeOptions",
    value: "mastercard"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null)))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -pan pan-input-container",
    "data-e2e-name": "pan"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_error -pan -hide"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide invalidField",
    "data-e2e-error": "invalidField"
  }, settings?.payplug_integrated_payment_pan_error), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide emptyField",
    "data-e2e-error": "paymentError"
  }, settings?.payplug_integrated_payment_empty)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -exp exp-input-container",
    "data-e2e-name": "expiration"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -cvv cvv-input-container",
    "data-e2e-name": "cvv"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_error -exp -hide"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide invalidField",
    "data-e2e-error": "invalidField"
  }, settings?.payplug_integrated_payment_exp_error), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide emptyField",
    "data-e2e-error": "paymentError"
  }, settings?.payplug_integrated_payment_empty)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_error -cvv -hide"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide invalidField",
    "data-e2e-error": "invalidField"
  }, settings?.payplug_integrated_payment_cvv_error), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "-hide emptyField",
    "data-e2e-error": "paymentError"
  }, settings?.payplug_integrated_payment_empty)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_error -payment"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, settings?.payplug_integrated_payment_error)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -transaction"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    className: "lock-icon",
    src: settings?.lock
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    className: "transaction-label"
  }, settings?.payplug_integrated_payment_transaction_secure), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    className: "payplug-logo",
    src: settings?.logo
  })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "payplug IntegratedPayment_container -privacy-policy"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: settings?.payplug_integrated_payment_privacy_policy_url,
    target: "_blank"
  }, settings?.payplug_integrated_payment_privacy_policy))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (IntegratedPayment);

/***/ }),

/***/ "./resources/js/frontend/wc-payplug-popup-blocks.js":
/*!**********************************************************!*\
  !*** ./resources/js/frontend/wc-payplug-popup-blocks.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helper_wc_payplug_requests__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./helper/wc-payplug-requests */ "./resources/js/frontend/helper/wc-payplug-requests.js");





const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_1__.getSetting)('payplug_data', {});
const Popup = ({
  props: props,
  settings: _settings
}) => {
  const {
    eventRegistration,
    emitResponse,
    shouldSavePayment
  } = props;
  const {
    onPaymentSetup,
    onCheckoutSuccess
  } = eventRegistration;
  const {
    CHECKOUT_STORE_KEY
  } = window.wc.wcBlocksData;
  const order_id = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select(CHECKOUT_STORE_KEY).getOrderId());
  let getPaymentData;
  (0,react__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const handlePaymentProcessing = async () => {
      let result = {};
      await (0,_helper_wc_payplug_requests__WEBPACK_IMPORTED_MODULE_4__.getPayment)(props, _settings, order_id).then(async response => {
        getPaymentData = response;
      });
      return {
        type: 'success'
      };
    };
    const unsubscribeAfterProcessing = onPaymentSetup(handlePaymentProcessing);
    return () => {
      unsubscribeAfterProcessing();
    };
  }, [shouldSavePayment, onPaymentSetup, emitResponse.noticeContexts.PAYMENTS, emitResponse.responseTypes.ERROR, emitResponse.responseTypes.SUCCESS]);
  (0,react__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const handlePaymentProcessing = async ({
      processingResponse: {
        paymentDetails
      }
    }) => {
      await showPopupPayment(getPaymentData).then(() => {
        return {
          type: "error",
          message: "Timeout",
          messageContext: emitResponse.noticeContexts.PAYMENTS
        };
      });
      function showPopupPayment(getPaymentData) {
        return new Promise(async (resolve, reject) => {
          try {
            window.redirection_url = getPaymentData.data.cancel || false;
            await Payplug.showPayment(getPaymentData.data.redirect);
          } catch (e) {
            reject(e);
          }
        });
      }
    };
    const unsubscribeAfterProcessing = onCheckoutSuccess(handlePaymentProcessing);
    return () => {
      unsubscribeAfterProcessing();
    };
  }, [onCheckoutSuccess]);
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null);
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Popup);

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
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!****************************************************!*\
  !*** ./resources/js/frontend/wc-payplug-blocks.js ***!
  \****************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/blocks-registry */ "@woocommerce/blocks-registry");
/* harmony import */ var _woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wc_payplug_integratedPayment_blocks__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./wc-payplug-integratedPayment-blocks */ "./resources/js/frontend/wc-payplug-integratedPayment-blocks.js");
/* harmony import */ var _wc_payplug_popup_blocks__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./wc-payplug-popup-blocks */ "./resources/js/frontend/wc-payplug-popup-blocks.js");
var _settings$showSaveOpt;







const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__.getSetting)('payplug_data', {});
const defaultLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Gateway method title', 'payplug');
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(settings?.title) || defaultLabel;

/**
 * Content component
 */
const Content = props => {
  if (settings?.IP === true) {
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wc_payplug_integratedPayment_blocks__WEBPACK_IMPORTED_MODULE_5__["default"], {
      settings: settings,
      props: props
    });
  }
  if (settings?.popup === true) {
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wc_payplug_popup_blocks__WEBPACK_IMPORTED_MODULE_6__["default"], {
      settings: settings,
      props: props
    });
  }
  return window.wp.htmlEntities.decodeEntities(settings?.description || '');
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
const Payplug = {
  name: "payplug",
  label: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Label, null),
  content: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, null),
  edit: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
    showSaveOption: settings?.oneclick && settings?.IP,
    showSavedCards: (_settings$showSaveOpt = settings.showSaveOption) !== null && _settings$showSaveOpt !== void 0 ? _settings$showSaveOpt : false
  }
};
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__.registerPaymentMethod)(Payplug);
})();

/******/ })()
;
//# sourceMappingURL=wc-payplug-payplug-blocks.js.map