/**
 * Message to show for a failed check_payment response: the payment failure message (data.message),
 * else the fallback. A plain-string data is a technical error sent before the payment status is
 * known, so it is never shown.
 *
 * @param {Object} response check_payment JSON response.
 * @param {string} fallback Message used when the response carries no payment failure message.
 * @return {string} Message.
 */
export const getFailureMessage = ( response, fallback ) => {
	const message = response?.data?.message;

	return typeof message === 'string' && message.trim() ? message.trim() : fallback;
};

/**
 * Message to show when the payment could not be started (payment creation or SDK pay call):
 * always the given message, the technical one only goes to the console.
 *
 * @param {Error|string} error    Error thrown by the request or the SDK.
 * @param {string}       fallback Message shown to the customer.
 * @return {string} Message.
 */
export const getPayErrorMessage = ( error, fallback ) => {
	console.error( error?.message || error );

	return fallback;
};

/**
 * Empties the integrated payment card fields.
 *
 * @param {Object} form SDK fields: cardHolder, pan, cvv, exp.
 */
export const clearFields = ( form ) => {
	[ 'cardHolder', 'pan', 'cvv', 'exp' ].forEach( ( key ) => {
		const field = form?.[ key ];
		if ( typeof field?.clear !== 'function' ) {
			return;
		}
		try {
			field.clear();
		} catch ( e ) {
			// A field that cannot be cleared must not stop the others nor hide the error message.
		}
	} );
};

/**
 * Unchecks the "save payment method" option of the WooCommerce Blocks payment store.
 *
 * @param {Object} paymentStore Actions of the wc/store/payment store.
 */
export const uncheckSaveCard = ( paymentStore ) => {
	if ( typeof paymentStore?.__internalSetShouldSavePaymentMethod !== 'function' ) {
		return;
	}
	try {
		paymentStore.__internalSetShouldSavePaymentMethod( false );
	} catch ( e ) {
		// Internal WooCommerce action: if it changes, the box stays as the customer left it.
	}
};

/**
 * The SDK keeps every callback given to onCompleted, so the component registers a single one
 * that hands each completion event to the payment attempt waiting for it.
 *
 * @return {{wait: function(): Promise<Object>, complete: function(Object): boolean}} Queue.
 */
export const createCompletionQueue = () => {
	let pending = null;

	return {
		wait: () => new Promise( ( resolve ) => {
			pending = resolve;
		} ),
		complete: ( event ) => {
			if ( ! pending ) {
				return false;
			}
			const resolve = pending;
			pending = null;
			resolve( event );

			return true;
		},
	};
};
