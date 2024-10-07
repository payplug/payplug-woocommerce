(()=>{"use strict";var e={n:t=>{var a=t&&t.__esModule?()=>t.default:()=>t;return e.d(a,{a}),a},d:(t,a)=>{for(var n in a)e.o(a,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:a[n]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.element,a=window.wp.i18n,n=window.wc.wcBlocksRegistry,r=window.wp.htmlEntities,l=window.wc.wcSettings,c=window.React,i=window.wp.data,p=window.jQuery;var d=e.n(p);const o=(0,l.getSetting)("payplug_data",{}),s=(e,t,a)=>{const n={order_id:a,"woocommerce-process-checkout-nonce":o.wp_nonce,gateway:t?.payment_method};return new Promise(((e,t)=>d().ajax({type:"POST",data:n,url:o.payplug_create_intent_payment}).success((function(t){e(t)})).error((function(e){t(e)}))))},m=(0,l.getSetting)("payplug_data",{}),u=({props:e})=>{const{eventRegistration:a,emitResponse:n,shouldSavePayment:r}=e,{onCheckoutValidation:l,onPaymentSetup:p,onCheckoutSuccess:d}=a,{PAYMENT_STORE_KEY:o,CHECKOUT_STORE_KEY:u}=window.wc.wcBlocksData,y=(0,i.useSelect)((e=>e(u).getOrderId()));(0,c.useEffect)((()=>{_.api=new Payplug.IntegratedPayment(1!=m?.mode),_.api.setDisplayMode3ds(Payplug.DisplayMode3ds.LIGHTBOX),_.form.cardHolder=_.api.cardHolder(document.querySelector(".cardHolder-input-container"),{default:_.inputStyle.default,placeholder:m?.payplug_integrated_payment_cardholder}),_.form.pan=_.api.cardNumber(document.querySelector(".pan-input-container"),{default:_.inputStyle.default,placeholder:m?.payplug_integrated_payment_card_number}),_.form.cvv=_.api.cvv(document.querySelector(".cvv-input-container"),{default:_.inputStyle.default,placeholder:m?.payplug_integrated_payment_cvv}),_.form.exp=_.api.expiration(document.querySelector(".exp-input-container"),{default:_.inputStyle.default,placeholder:m?.payplug_integrated_payment_expiration_date}),_.scheme=_.api.getSupportedSchemes(),g()}),[]),(0,c.useEffect)((()=>{const e=l((async()=>{_.api.validateForm();let e=!1;return await new Promise((async(e,t)=>{await _.api.onValidateForm((({isFormValid:t})=>{e(t)}))})).then((t=>{e=t})),e||{errorMessage:m?.payplug_invalid_form}}));return()=>{e()}}),[l]),(0,c.useEffect)((()=>{const e=p((async()=>{let e={};console.log(y),await s(0,m,y).then((async t=>{_.paymentId=t.data.payment_id,e={payment_id:t.data.payment_id},_.return_url=t.data.redirect;try{return await _.api.pay(_.paymentId,Payplug.Scheme.AUTO,{save_card:!1}),await new Promise(((e,t)=>{_.api.onCompleted((function(e){window.location=_.return_url}))}))}catch(e){return{type:"error",message:e.message}}}))}));return()=>{e()}}),[p]);const g=()=>{jQuery.each(_.form,(function(e,t){t.onChange((function(t){t.error?(document.querySelector(".payplug.IntegratedPayment_error.-"+e).classList.remove("-hide"),document.querySelector("."+e+"-input-container").classList.add("-invalid"),"FIELD_EMPTY"===t.error.name?(document.querySelector(".payplug.IntegratedPayment_error.-"+e).querySelector(".emptyField").classList.remove("-hide"),document.querySelector(".payplug.IntegratedPayment_error.-"+e).querySelector(".invalidField").classList.add("-hide")):(document.querySelector(".payplug.IntegratedPayment_error.-"+e).querySelector(".invalidField").classList.remove("-hide"),document.querySelector(".payplug.IntegratedPayment_error.-"+e).querySelector(".emptyField").classList.add("-hide"))):(document.querySelector(".payplug.IntegratedPayment_error.-"+e).classList.add("-hide"),document.querySelector("."+e+"-input-container").classList.remove("-invalid"),document.querySelector(".payplug.IntegratedPayment_error.-"+e).querySelector(".invalidField").classList.add("-hide"),document.querySelector(".payplug.IntegratedPayment_error.-"+e).querySelector(".emptyField").classList.add("-hide"),_.fieldsValid[e]=!0,_.fieldsEmpty[e]=!1)}))}))};var _={cartId:null,paymentId:null,paymentOptionId:null,form:{},checkoutForm:null,api:null,integratedPayment:null,token:null,notValid:!0,fieldsValid:{cardHolder:!1,pan:!1,cvv:!1,exp:!1},fieldsEmpty:{cardHolder:!0,pan:!0,cvv:!0,exp:!0},inputStyle:{default:{color:"#2B343D",fontFamily:"Poppins, sans-serif",fontSize:"14px",textAlign:"left","::placeholder":{color:"#969a9f"},":focus":{color:"#2B343D"}},invalid:{color:"#E91932"}},save_card:!1,scheme:null,query:null,submit:null,order_review:!1,return_url:null};return(0,t.createElement)(t.Fragment,null,(0,t.createElement)("div",{id:"payplug-integrated-payment",className:"payplug IntegratedPayment -loaded"},(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -cardHolder cardHolder-input-container","data-e2e-name":"cardHolder"}),(0,t.createElement)("div",{className:"payplug IntegratedPayment_error -cardHolder -hide"},(0,t.createElement)("span",{className:"-hide invalidField","data-e2e-error":"invalidField"},m?.payplug_integrated_payment_cardHolder_error),(0,t.createElement)("span",{className:"-hide emptyField","data-e2e-error":"paymentError"},m?.payplug_integrated_payment_empty)),(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -scheme"},(0,t.createElement)("div",null,m?.payplug_integrated_payment_your_card),(0,t.createElement)("div",{className:"payplug IntegratedPayment_schemes"},(0,t.createElement)("label",{className:"payplug IntegratedPayment_scheme -visa"},(0,t.createElement)("input",{type:"radio",name:"schemeOptions",value:"visa"}),(0,t.createElement)("span",null)),(0,t.createElement)("label",{className:"payplug IntegratedPayment_scheme -mastercard"},(0,t.createElement)("input",{type:"radio",name:"schemeOptions",value:"mastercard"}),(0,t.createElement)("span",null)),(0,t.createElement)("label",{className:"payplug IntegratedPayment_scheme -cb"},(0,t.createElement)("input",{type:"radio",name:"schemeOptions",value:"cb"}),(0,t.createElement)("span",null)))),(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -pan pan-input-container","data-e2e-name":"pan"}),(0,t.createElement)("div",{className:"payplug IntegratedPayment_error -pan -hide"},(0,t.createElement)("span",{className:"-hide invalidField","data-e2e-error":"invalidField"},m?.payplug_integrated_payment_pan_error),(0,t.createElement)("span",{className:"-hide emptyField","data-e2e-error":"paymentError"},m?.payplug_integrated_payment_empty)),(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -exp exp-input-container","data-e2e-name":"expiration"}),(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -cvv cvv-input-container","data-e2e-name":"cvv"}),(0,t.createElement)("div",{className:"payplug IntegratedPayment_error -exp -hide"},(0,t.createElement)("span",{className:"-hide invalidField","data-e2e-error":"invalidField"},m?.payplug_integrated_payment_exp_error),(0,t.createElement)("span",{className:"-hide emptyField","data-e2e-error":"paymentError"},m?.payplug_integrated_payment_empty)),(0,t.createElement)("div",{className:"payplug IntegratedPayment_error -cvv -hide"},(0,t.createElement)("span",{className:"-hide invalidField","data-e2e-error":"invalidField"},m?.payplug_integrated_payment_cvv_error),(0,t.createElement)("span",{className:"-hide emptyField","data-e2e-error":"paymentError"},m?.payplug_integrated_payment_empty)),(0,t.createElement)("div",{className:"payplug IntegratedPayment_error -payment"},(0,t.createElement)("span",null,m?.payplug_integrated_payment_error)),(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -transaction"},(0,t.createElement)("img",{className:"lock-icon",src:m?.lock}),(0,t.createElement)("label",{className:"transaction-label"},m?.payplug_integrated_payment_transaction_secure),(0,t.createElement)("img",{className:"payplug-logo",src:m?.logo})),(0,t.createElement)("div",{className:"payplug IntegratedPayment_container -privacy-policy"},(0,t.createElement)("a",{href:m?.payplug_integrated_payment_privacy_policy_url,target:"_blank"},m?.payplug_integrated_payment_privacy_policy))))},y=((0,l.getSetting)("payplug_data",{}),({props:e,settings:a})=>{const{eventRegistration:n,emitResponse:r,shouldSavePayment:l}=e,{onPaymentSetup:p,onCheckoutSuccess:d}=n,{CHECKOUT_STORE_KEY:o}=window.wc.wcBlocksData,m=(0,i.useSelect)((e=>e(o).getOrderId()));let u;return(0,c.useEffect)((()=>{const e=p((async()=>(await s(0,a,m).then((async e=>{u=e})),{type:"success"})));return()=>{e()}}),[l,p,r.noticeContexts.PAYMENTS,r.responseTypes.ERROR,r.responseTypes.SUCCESS]),(0,c.useEffect)((()=>{const e=d((async({processingResponse:{paymentDetails:e}})=>{await function(e){return new Promise((async(t,a)=>{try{window.redirection_url=e.data.cancel||!1,await Payplug.showPayment(e.data.redirect)}catch(e){a(e)}}))}(u).then((()=>({type:"error",message:"Timeout",messageContext:r.noticeContexts.PAYMENTS})))}));return()=>{e()}}),[d]),(0,t.createElement)(t.Fragment,null)}),g=(0,l.getSetting)("payplug_data",{}),_=(0,a.__)("Gateway method title","payplug"),E=(0,r.decodeEntities)(g?.title)||_,v=e=>!0===g?.IP?(0,t.createElement)(u,{settings:g,props:e}):!0===g?.popup?(0,t.createElement)(y,{settings:g,props:e}):window.wp.htmlEntities.decodeEntities(g?.description||""),h=()=>(0,t.createElement)("img",{src:g?.icon.src,alt:g?.icon.icon_alt,className:"payplug-payment-icon",style:{float:"right"}}),w={name:"payplug",label:(0,t.createElement)((()=>(0,t.createElement)("span",{style:{width:"100%"}},E,(0,t.createElement)(h,null))),null),content:(0,t.createElement)(v,null),edit:(0,t.createElement)(v,null),canMakePayment:()=>!0,ariaLabel:E,supports:{features:g.supports}};(0,n.registerPaymentMethod)(w)})();