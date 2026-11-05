/**
 * Whether a PPRO payment method can be offered for the current cart and billing address.
 *
 * @param {Object} restrictions ppro_restrictions of the payment method data: min and max in cents,
 *                              countries (null for any country), currency, ok.
 * @param {Object} data         canMakePayment argument of WooCommerce Blocks.
 * @return {boolean} Eligibility.
 */
export const isEligible = ( restrictions, data ) => {
	if ( ! restrictions || ! restrictions.ok || ! data || ! data.cartTotals ) {
		return false;
	}

	const totals = data.cartTotals;
	if ( String( totals.currency_code || '' ).toUpperCase() !== String( restrictions.currency || '' ).toUpperCase() ) {
		return false;
	}

	const total = parseInt( totals.total_price, 10 );
	if ( Number.isNaN( total ) ) {
		return false;
	}
	const minorUnit = Number.isInteger( totals.currency_minor_unit ) ? totals.currency_minor_unit : 2;
	const cents = Math.round( total * Math.pow( 10, 2 - minorUnit ) );
	if ( cents < restrictions.min || cents > restrictions.max ) {
		return false;
	}

	const address = data.billingAddress || data.billingData || {};
	const country = String( address.country || '' ).toUpperCase();
	if ( '' === country ) {
		return false;
	}

	return null === restrictions.countries || ( Array.isArray( restrictions.countries ) && restrictions.countries.includes( country ) );
};
