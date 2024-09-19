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
/*!*********************************************************************!*\
  !*** ./resources/js/frontend/wc-payplug-american_express-blocks.js ***!
  \*********************************************************************/
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
/* harmony import */ var _wc_payplug_popup_blocks__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./wc-payplug-popup-blocks */ "./resources/js/frontend/wc-payplug-popup-blocks.js");






const settings = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_4__.getSetting)('american_express_data', {});
const defaultLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Gateway method title', 'payplug');
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(settings?.title) || defaultLabel;
/**
 * Content component
 */
const Content = props => {
  if (settings?.popup === true) {
    console.log("popup");
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wc_payplug_popup_blocks__WEBPACK_IMPORTED_MODULE_5__["default"], {
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
const Amex = {
  name: "american_express",
  label: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Label, null),
  content: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, null),
  edit: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports
  }
};
(0,_woocommerce_blocks_registry__WEBPACK_IMPORTED_MODULE_2__.registerPaymentMethod)(Amex);
/******/ })()
;
//# sourceMappingURL=wc-payplug-american_express-blocks.js.map