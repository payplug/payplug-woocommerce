import {
	getFailureMessage,
	getPayErrorMessage,
	clearFields,
	uncheckSaveCard,
	createCompletionQueue,
} from './wc-payplug-integrated-payment-errors';

const fallback = 'We could not confirm your payment.';

const fakeField = () => ( { clear: jest.fn() } );

describe( 'getFailureMessage', () => {
	it( 'returns the payment failure message of a check_payment error', () => {
		const response = { success: false, data: { code: 'card_declined', message: "The customer's bank declined the payment." } };

		expect( getFailureMessage( response, fallback ) ).toBe( "The customer's bank declined the payment." );
	} );

	it.each( [
		[ 'Invalid request.' ],
		[ 'You have been disconnected from the Payplug module (error 401)' ],
		[ 'cURL error 28: Operation timed out after 30001 milliseconds' ],
	] )( 'falls back to the given message for the plain-string error "%s"', ( data ) => {
		expect( getFailureMessage( { success: false, data }, fallback ) ).toBe( fallback );
	} );

	it( 'trims the message', () => {
		expect( getFailureMessage( { success: false, data: { message: '  Card expired.  ' } }, fallback ) ).toBe( 'Card expired.' );
	} );

	it.each( [
		[ 'an empty message', { success: false, data: { code: 500, message: '' } } ],
		[ 'a whitespace-only message', { success: false, data: { message: '   ' } } ],
		[ 'no message key', { success: false, data: { code: 'card_declined' } } ],
		[ 'a non-string message', { success: false, data: { message: { text: 'x' } } } ],
		[ 'no data', { success: false } ],
		[ 'a null data', { success: false, data: null } ],
		[ 'an empty string data', { success: false, data: '' } ],
		[ 'no response', undefined ],
	] )( 'falls back to the given message for %s', ( _label, response ) => {
		expect( getFailureMessage( response, fallback ) ).toBe( fallback );
	} );
} );

describe( 'getPayErrorMessage', () => {
	const payError = 'The transaction failed and your card was not charged.';

	beforeEach( () => {
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		console.error.mockRestore();
	} );

	it.each( [
		[ 'an SDK error', new Error( 'Payment token is not defined or his format is invalid' ) ],
		[ 'a server error sent as a plain string', new Error( 'You have been disconnected from the Payplug module (error 401)' ) ],
		[ 'an error without message', new Error() ],
		[ 'a thrown string', 'Request failed' ],
		[ 'no error', undefined ],
	] )( 'returns the given message for %s', ( _label, error ) => {
		expect( getPayErrorMessage( error, payError ) ).toBe( payError );
	} );

	it( 'logs the technical message', () => {
		getPayErrorMessage( new Error( 'Payment token is not defined or his format is invalid' ), payError );

		expect( console.error ).toHaveBeenCalledWith( 'Payment token is not defined or his format is invalid' );
	} );
} );

describe( 'clearFields', () => {
	it( 'clears the four card fields', () => {
		const form = { cardHolder: fakeField(), pan: fakeField(), cvv: fakeField(), exp: fakeField() };

		clearFields( form );

		expect( form.cardHolder.clear ).toHaveBeenCalledTimes( 1 );
		expect( form.pan.clear ).toHaveBeenCalledTimes( 1 );
		expect( form.cvv.clear ).toHaveBeenCalledTimes( 1 );
		expect( form.exp.clear ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'clears the mounted fields when one is missing or cannot be cleared', () => {
		const form = { cardHolder: fakeField(), pan: {}, cvv: null, exp: fakeField() };

		expect( () => clearFields( form ) ).not.toThrow();
		expect( form.cardHolder.clear ).toHaveBeenCalledTimes( 1 );
		expect( form.exp.clear ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'clears the other fields when one clear() throws', () => {
		const form = { cardHolder: { clear: jest.fn( () => { throw new Error( 'iframe gone' ); } ) }, pan: fakeField(), cvv: fakeField(), exp: fakeField() };

		expect( () => clearFields( form ) ).not.toThrow();
		expect( form.pan.clear ).toHaveBeenCalledTimes( 1 );
		expect( form.cvv.clear ).toHaveBeenCalledTimes( 1 );
		expect( form.exp.clear ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'accepts a missing form', () => {
		expect( () => clearFields( undefined ) ).not.toThrow();
	} );
} );

describe( 'uncheckSaveCard', () => {
	it( 'unchecks the save payment method option of the payment store', () => {
		const store = { __internalSetShouldSavePaymentMethod: jest.fn() };

		uncheckSaveCard( store );

		expect( store.__internalSetShouldSavePaymentMethod ).toHaveBeenCalledWith( false );
	} );

	it.each( [
		[ 'a store without the action', {} ],
		[ 'a missing store', undefined ],
		[ 'an action that throws', { __internalSetShouldSavePaymentMethod: () => { throw new Error( 'removed' ); } } ],
	] )( 'does nothing for %s', ( _label, store ) => {
		expect( () => uncheckSaveCard( store ) ).not.toThrow();
	} );
} );

describe( 'createCompletionQueue', () => {
	it( 'hands the completion event to the attempt waiting for it', async () => {
		const queue = createCompletionQueue();
		const attempt = queue.wait();

		queue.complete( { token: 'pay_test_123' } );

		await expect( attempt ).resolves.toEqual( { token: 'pay_test_123' } );
	} );

	it( 'ignores a completion event when no attempt is waiting', () => {
		const queue = createCompletionQueue();

		expect( queue.complete( { token: 'pay_test_123' } ) ).toBe( false );
	} );

	it( 'delivers each completion event once', async () => {
		const queue = createCompletionQueue();
		const attempt = queue.wait();

		expect( queue.complete( { token: 'pay_test_1' } ) ).toBe( true );
		expect( queue.complete( { token: 'pay_test_1' } ) ).toBe( false );
		await expect( attempt ).resolves.toEqual( { token: 'pay_test_1' } );
	} );

	it( 'hands a retry its own completion event', async () => {
		const queue = createCompletionQueue();
		const first = queue.wait();
		queue.complete( { token: 'pay_test_1' } );
		const second = queue.wait();

		queue.complete( { token: 'pay_test_2' } );

		await expect( first ).resolves.toEqual( { token: 'pay_test_1' } );
		await expect( second ).resolves.toEqual( { token: 'pay_test_2' } );
	} );
} );
