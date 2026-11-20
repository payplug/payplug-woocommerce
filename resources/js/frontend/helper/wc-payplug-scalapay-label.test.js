import { renderToString } from '@wordpress/element';
import { ScalapayLabel } from './wc-payplug-scalapay-label';

const CHECKOUT_TITLE_SELECTOR = 'div.scalapay-checkout-label > span';

const icon = { src: 'https://shop.test/scalapay.svg', icon_alt: 'Scalapay' };

const renderLabel = () => {
	document.body.innerHTML = renderToString( <ScalapayLabel label="Pay with Scalapay" icon={ icon } /> );
};

describe( 'ScalapayLabel', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'exposes the title alone in the element the widget rewrites', () => {
		renderLabel();

		const title = document.querySelector( CHECKOUT_TITLE_SELECTOR );

		expect( title ).not.toBeNull();
		expect( title.textContent ).toBe( 'Pay with Scalapay' );
		expect( title.querySelector( 'img' ) ).toBeNull();
	} );

	it( 'keeps the PayPlug icon when the widget replaces the title', () => {
		renderLabel();

		document.querySelector( CHECKOUT_TITLE_SELECTOR ).innerHTML = 'Pay in 3 instalments of 80,00 €';

		const container = document.querySelector( 'div.scalapay-checkout-label' );
		const img = container.querySelector( 'img.payplug-payment-icon' );
		expect( container.textContent ).toBe( 'Pay in 3 instalments of 80,00 €' );
		expect( img.getAttribute( 'src' ) ).toBe( icon.src );
		expect( img.getAttribute( 'alt' ) ).toBe( icon.icon_alt );
	} );

	it( 'gives the icon the classes of the classic checkout icon', () => {
		renderLabel();

		const img = document.querySelector( 'div.scalapay-checkout-label > img' );

		expect( img.className ).toBe( 'payplug-payment-icon scalapay-checkout-label__icon' );
	} );

	it( 'keeps the full-width label with the icon floated right', () => {
		renderLabel();

		const container = document.querySelector( 'div.scalapay-checkout-label' );

		expect( container.style.width ).toBe( '100%' );
		expect( container.querySelector( 'img' ).style.float ).toBe( 'right' );
	} );
} );
