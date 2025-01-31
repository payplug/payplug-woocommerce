(()=>{"use strict";var e={n:t=>{var n=t&&t.__esModule?()=>t.default:()=>t;return e.d(n,{a:n}),n},d:(t,n)=>{for(var a in n)e.o(n,a)&&!e.o(t,a)&&Object.defineProperty(t,a,{enumerable:!0,get:n[a]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.element,n=window.wp.i18n,a=window.wc.wcBlocksRegistry,s=window.wp.htmlEntities,o=window.wc.wcSettings,r=window.React,c=window.wp.data,i=window.jQuery;var l=e.n(i);const p=(0,o.getSetting)("payplug_data",{}),w=((0,o.getSetting)("payplug_data",{}),({props:e,settings:n})=>{const{eventRegistration:a,emitResponse:s,shouldSavePayment:o}=e,{onPaymentSetup:i,onCheckoutSuccess:w}=a,{CHECKOUT_STORE_KEY:u}=window.wc.wcBlocksData,d=(0,c.useSelect)((e=>e(u).getOrderId()));let m;return(0,r.useEffect)((()=>{const e=i((async()=>(await((e,t,n)=>{const a={order_id:n,"woocommerce-process-checkout-nonce":p.wp_nonce,gateway:t?.payment_method};return new Promise(((e,t)=>l().ajax({type:"POST",data:a,url:p.payplug_create_intent_payment}).success((function(t){e(t)})).error((function(e){t(e)}))))})(0,n,d).then((async e=>{m=e})),{type:"success"})));return()=>{e()}}),[o,i,s.noticeContexts.PAYMENTS,s.responseTypes.ERROR,s.responseTypes.SUCCESS]),(0,r.useEffect)((()=>{const e=w((async({processingResponse:{paymentDetails:e}})=>{await function(e){return new Promise((async(t,n)=>{try{window.redirection_url=e.data.cancel||!1,await Payplug.showPayment(e.data.redirect)}catch(e){n(e)}}))}(m).then((()=>({type:"error",message:"Timeout",messageContext:s.noticeContexts.PAYMENTS})))}));return()=>{e()}}),[w]),(0,t.createElement)(t.Fragment,null)}),u=(0,o.getSetting)("american_express_data",{}),d=(0,n.__)("Gateway method title","payplug"),m=(0,s.decodeEntities)(u?.title)||d,y=e=>!0===u?.popup?(0,t.createElement)(w,{settings:u,props:e}):window.wp.htmlEntities.decodeEntities(u?.description||""),g=()=>(0,t.createElement)("img",{src:u?.icon.src,alt:u?.icon.icon_alt,className:"payplug-payment-icon",style:{float:"right"}}),E={name:"american_express",label:(0,t.createElement)((()=>(0,t.createElement)("span",{style:{width:"100%"}},m,(0,t.createElement)(g,null))),null),content:(0,t.createElement)(y,null),edit:(0,t.createElement)(y,null),canMakePayment:()=>!0,ariaLabel:m,supports:{features:u.supports}};(0,a.registerPaymentMethod)(E)})();