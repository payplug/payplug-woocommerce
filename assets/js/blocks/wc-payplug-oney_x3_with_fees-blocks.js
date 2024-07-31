(()=>{"use strict";var e={20:(e,s,n)=>{var t=n(609),i=Symbol.for("react.element"),a=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),l=t.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,o={key:!0,ref:!0,__self:!0,__source:!0};function r(e,s,n){var t,r={},c=null,d=null;for(t in void 0!==n&&(c=""+n),void 0!==s.key&&(c=""+s.key),void 0!==s.ref&&(d=s.ref),s)a.call(s,t)&&!o.hasOwnProperty(t)&&(r[t]=s[t]);if(e&&e.defaultProps)for(t in s=e.defaultProps)void 0===r[t]&&(r[t]=s[t]);return{$$typeof:i,type:e,key:c,ref:d,props:r,_owner:l.current}}s.jsx=r,s.jsxs=r},848:(e,s,n)=>{e.exports=n(20)},609:e=>{e.exports=window.React}},s={};const n=window.wp.i18n,t=window.wc.wcBlocksRegistry,i=window.wp.htmlEntities,a=window.wc.wcSettings;var l=function n(t){var i=s[t];if(void 0!==i)return i.exports;var a=s[t]={exports:{}};return e[t](a,a.exports,n),a.exports}(848);const o=(0,a.getSetting)("oney_x3_with_fees_data",{}),r=(0,n.__)("Gateway method title","payplug"),c=(0,i.decodeEntities)(o?.title)||r;let d;const _=o?.translations,y=o?.requirements.allowed_country_codes,u=o?.oney_response;if(void 0!==u.x3_with_fees){var x=parseFloat(o?.oney_response.x3_with_fees.down_payment_amount),m=x;o?.oney_response.x3_with_fees.installments.forEach((e=>{m+=parseFloat(e.amount)})),d=e=>{let s=e.shippingData.shippingAddress.country;return-1===y.indexOf(s)?(o.icon.class="disable-checkout-icons",(0,l.jsx)("div",{className:o?.oney_disabled.validations.country.class,children:o?.oney_disabled.validations.country.text})):e.cartData.cartItems.length>o?.requirements.max_quantity?(o.icon.class="disable-checkout-icons",(0,l.jsx)("div",{className:o?.oney_disabled.validations.items_count.class,children:o?.oney_disabled.validations.items_count.text})):e.billing.cartTotal.value>o?.requirements.max_threshold||e.billing.cartTotal.value<o?.requirements.min_threshold?(o.icon.class="disable-checkout-icons",(0,l.jsx)("div",{className:o?.oney_disabled.validations.amount.class,children:o?.oney_disabled.validations.amount.text})):(o.icon.class="payplug-payment-icon",(0,l.jsxs)("div",{children:[(0,l.jsxs)("div",{className:"payplug-oney-flex",children:[(0,l.jsxs)("div",{children:[_.bring," :"]}),(0,l.jsxs)("div",{children:[x," ",e.billing.currency.symbol]})]}),(0,l.jsx)("div",{className:"payplug-oney-flex",children:(0,l.jsxs)("small",{children:["( ",_.oney_financing_cost,(0,l.jsxs)("b",{children:[o?.oney_response.x3_with_fees.total_cost," ",e.billing.currency.symbol]})," TAEG : ",(0,l.jsxs)("b",{children:[o?.oney_response.x3_with_fees.effective_annual_percentage_rate," %"]})," )"]})}),(0,l.jsxs)("div",{className:"payplug-oney-flex",children:[(0,l.jsxs)("div",{children:[_["1st monthly payment"],":"]}),(0,l.jsxs)("div",{children:[o?.oney_response.x3_with_fees.installments[0].amount," ",e.billing.currency.symbol]})]}),(0,l.jsxs)("div",{className:"payplug-oney-flex",children:[(0,l.jsxs)("div",{children:[_["2nd monthly payment"],":"]}),(0,l.jsxs)("div",{children:[o?.oney_response.x3_with_fees.installments[1].amount," ",e.billing.currency.symbol]})]}),(0,l.jsxs)("div",{className:"payplug-oney-flex",children:[(0,l.jsx)("div",{children:(0,l.jsx)("b",{children:_.oney_total})}),(0,l.jsx)("div",{children:(0,l.jsxs)("b",{children:[m," ",e.billing.currency.symbol]})})]})]}))}}else d=e=>{let s=e.shippingData.shippingAddress.country;return-1===y.indexOf(s)?(0,l.jsx)("div",{className:o?.oney_disabled.validations.country.class,children:o?.oney_disabled.validations.country.text}):e.cartData.cartItems.length>o?.requirements.max_quantity?(0,l.jsx)("div",{className:o?.oney_disabled.validations.items_count.class,children:o?.oney_disabled.validations.items_count.text}):e.billing.cartTotal.value>o?.requirements.max_threshold||e.billing.cartTotal.value<o?.requirements.min_threshold?(0,l.jsx)("div",{className:o?.oney_disabled.validations.amount.class,children:o?.oney_disabled.validations.amount.text}):void 0};const h=()=>(0,l.jsxs)("span",{style:{width:"100%"},children:[c,(0,l.jsx)(p,{})]}),p=()=>(0,l.jsx)("img",{src:o?.icon.src,alt:o?.icon.alt,className:o.icon.class,style:{float:"right"}});let v={name:"oney_x3_with_fees",label:(0,l.jsx)(h,{}),content:(0,l.jsx)(d,{}),edit:(0,l.jsx)(d,{}),canMakePayment:()=>!0,ariaLabel:c,supports:{features:o.supports}};(0,t.registerPaymentMethod)(v)})();