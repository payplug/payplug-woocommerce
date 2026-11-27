/**
 * Scalapay payment method label. With type="checkout" the Scalapay widget writes its title into
 * the span of div.scalapay-checkout-label, so the icon sits outside that span.
 *
 * @param {Object} props
 * @param {string} props.label Payment method title.
 * @param {Object} props.icon  src and icon_alt of the PayPlug icon.
 */
export const ScalapayLabel = ( { label, icon } ) => (
	<div className="scalapay-checkout-label" style={ { width: '100%' } }>
		<span>{ label }</span>
		<img src={ icon?.src } alt={ icon?.icon_alt } className="payplug-payment-icon scalapay-checkout-label__icon" style={ { float: 'right' } } />
	</div>
);
