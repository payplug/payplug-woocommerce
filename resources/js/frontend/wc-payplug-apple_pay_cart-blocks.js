import React from 'react';
import { getSetting } from '@woocommerce/settings';
const settings = getSetting( 'apple_pay_data', {} );

const ApplePayCart = ( props ) =>{
	return (
		<>
			<div id="apple-pay-button-wrapper">
				<apple-pay-button
					buttonstyle="black"
					type="pay"
					locale={settings?.payplug_locale}
				></apple-pay-button>
			</div>
		</>
	)
}

export default ApplePayCart;
