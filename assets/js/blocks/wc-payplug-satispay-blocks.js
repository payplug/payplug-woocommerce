(()=>{"use strict";var t={20:(t,e,r)=>{var s=r(609),o=Symbol.for("react.element"),n=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),i=s.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,a={key:!0,ref:!0,__self:!0,__source:!0};function p(t,e,r){var s,p={},c=null,l=null;for(s in void 0!==r&&(c=""+r),void 0!==e.key&&(c=""+e.key),void 0!==e.ref&&(l=e.ref),e)n.call(e,s)&&!a.hasOwnProperty(s)&&(p[s]=e[s]);if(t&&t.defaultProps)for(s in e=t.defaultProps)void 0===p[s]&&(p[s]=e[s]);return{$$typeof:o,type:t,key:c,ref:l,props:p,_owner:i.current}}e.jsx=p,e.jsxs=p},848:(t,e,r)=>{t.exports=r(20)},609:t=>{t.exports=window.React}},e={};const r=window.wp.i18n,s=window.wc.wcBlocksRegistry,o=window.wp.htmlEntities,n=window.wc.wcSettings;var i=function r(s){var o=e[s];if(void 0!==o)return o.exports;var n=e[s]={exports:{}};return t[s](n,n.exports,r),n.exports}(848);const a=(0,n.getSetting)("satispay_data",{}),p=(0,r.__)("Gateway method title","payplug"),c=(0,o.decodeEntities)(a?.title)||p,l=()=>window.wp.htmlEntities.decodeEntities(a?.description||""),w=()=>(0,i.jsxs)("span",{style:{width:"100%"},children:[c,(0,i.jsx)(d,{})]}),d=()=>(0,i.jsx)("img",{src:a?.icon.src,alt:a?.icon.icon_alt,className:"payplug-payment-icon",style:{float:"right"}}),y={name:"satispay",label:(0,i.jsx)(w,{}),content:(0,i.jsx)(l,{}),edit:(0,i.jsx)(l,{}),canMakePayment:()=>!0,ariaLabel:c,supports:{features:a.supports}};(0,s.registerPaymentMethod)(y)})();