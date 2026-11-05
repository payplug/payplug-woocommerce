import { isEligible } from './wc-payplug-ppro-eligibility';

const restrictions = {
	min: 30,
	max: 2000000,
	countries: [ 'BE', 'FR' ],
	currency: 'EUR',
	ok: true,
};

const args = ( total, country = 'FR', currency = 'EUR', minorUnit = 2 ) => ( {
	cartTotals: {
		total_price: total,
		currency_code: currency,
		currency_minor_unit: minorUnit,
	},
	billingAddress: { country },
} );

describe( 'isEligible', () => {
	it( 'accepts the exact minimum', () => {
		expect( isEligible( restrictions, args( '30' ) ) ).toBe( true );
	} );

	it( 'refuses one cent below the minimum', () => {
		expect( isEligible( restrictions, args( '29' ) ) ).toBe( false );
	} );

	it( 'accepts the exact maximum and refuses one cent above', () => {
		expect( isEligible( restrictions, args( '2000000' ) ) ).toBe( true );
		expect( isEligible( restrictions, args( '2000001' ) ) ).toBe( false );
	} );

	it( 'converts totals of a shop without decimals to cents', () => {
		expect( isEligible( restrictions, args( '1', 'FR', 'EUR', 0 ) ) ).toBe( true );
		expect( isEligible( { ...restrictions, min: 500 }, args( '4', 'FR', 'EUR', 0 ) ) ).toBe( false );
	} );

	it( 'refuses another currency', () => {
		expect( isEligible( restrictions, args( '1000', 'FR', 'USD' ) ) ).toBe( false );
	} );

	it( 'accepts a billing country of the list and refuses the others', () => {
		expect( isEligible( restrictions, args( '1000', 'BE' ) ) ).toBe( true );
		expect( isEligible( restrictions, args( '1000', 'be' ) ) ).toBe( true );
		expect( isEligible( restrictions, args( '1000', 'IT' ) ) ).toBe( false );
	} );

	it( 'accepts any billing country without restriction', () => {
		expect( isEligible( { ...restrictions, countries: null }, args( '1000', 'US' ) ) ).toBe( true );
	} );

	it( 'refuses every country for an empty selection', () => {
		expect( isEligible( { ...restrictions, countries: [] }, args( '1000', 'FR' ) ) ).toBe( false );
	} );

	it( 'refuses a missing billing country', () => {
		expect( isEligible( { ...restrictions, countries: null }, args( '1000', '' ) ) ).toBe( false );
		expect( isEligible( { ...restrictions, countries: null }, { cartTotals: args( '1000' ).cartTotals } ) ).toBe( false );
	} );

	it( 'falls back to billingData on older WooCommerce Blocks', () => {
		const data = { cartTotals: args( '1000' ).cartTotals, billingData: { country: 'FR' } };

		expect( isEligible( restrictions, data ) ).toBe( true );
	} );

	it( 'refuses without restrictions or when they are not usable', () => {
		expect( isEligible( undefined, args( '1000' ) ) ).toBe( false );
		expect( isEligible( { ...restrictions, ok: false }, args( '1000' ) ) ).toBe( false );
	} );

	it( 'refuses a malformed total', () => {
		expect( isEligible( restrictions, args( 'abc' ) ) ).toBe( false );
		expect( isEligible( restrictions, { billingAddress: { country: 'FR' } } ) ).toBe( false );
	} );
} );
