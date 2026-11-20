import { createElement } from '@wordpress/element';

/**
 * Scalapay installment widget, rendered with the attributes prepared by the plugin and nothing else.
 * A new amountKey remounts the element so that it reads the amount again.
 *
 * @param {Object}        props
 * @param {Object|null}   props.widget    merchant-token, type, amount-selectors and locale attributes.
 * @param {string|number} props.amountKey Current total.
 */
export const ScalapayWidget = ( { widget, amountKey } ) => {
	if ( ! widget ) {
		return null;
	}

	return createElement( 'scalapay-widget', { ...widget, key: String( amountKey ?? '' ) } );
};
