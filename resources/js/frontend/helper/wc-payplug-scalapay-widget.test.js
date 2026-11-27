import { act } from 'react';
import { createRoot, renderToString } from '@wordpress/element';
import { ScalapayWidget } from './wc-payplug-scalapay-widget';

const widget = {
	'merchant-token': 'TOKEN123',
	type: 'checkout',
	'amount-selectors': '["#payplug-scalapay-amount"]',
	locale: 'fr',
};

const render = ( props ) => {
	document.body.innerHTML = `<div id="content">${ renderToString( <ScalapayWidget { ...props } /> ) }</div>`;

	return document.getElementById( 'content' );
};

describe( 'ScalapayWidget', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'wraps the widget in a plain element so that its container is never empty', () => {
		const content = render( { widget, amountKey: 24000 } );

		expect( content.children ).toHaveLength( 1 );

		const root = content.firstElementChild;
		expect( root.tagName ).toBe( 'DIV' );
		expect( root.classList.contains( 'payplug-scalapay-widget' ) ).toBe( true );
		expect( root.getAttribute( 'style' ) ).toBeNull();
		expect( root.matches( ':empty' ) ).toBe( false );
		expect( content.matches( ':has(> *:only-child:empty)' ) ).toBe( false );
	} );

	it( 'renders exactly one widget with the attributes prepared by the plugin', () => {
		const content = render( { widget, amountKey: 24000 } );

		const elements = content.querySelectorAll( 'scalapay-widget' );
		expect( elements ).toHaveLength( 1 );

		const element = elements[ 0 ];
		expect( element.parentElement.classList.contains( 'payplug-scalapay-widget' ) ).toBe( true );
		expect( element.getAttributeNames().sort() ).toEqual( Object.keys( widget ).sort() );
		Object.entries( widget ).forEach( ( [ name, value ] ) => {
			expect( element.getAttribute( name ) ).toBe( value );
		} );
	} );

	it( 'remounts the widget when the total changes', () => {
		globalThis.IS_REACT_ACT_ENVIRONMENT = true;
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		const root = createRoot( container );

		act( () => root.render( <ScalapayWidget widget={ widget } amountKey={ 24000 } /> ) );
		const first = container.querySelector( 'scalapay-widget' );

		act( () => root.render( <ScalapayWidget widget={ widget } amountKey={ 24000 } /> ) );
		expect( container.querySelector( 'scalapay-widget' ) ).toBe( first );

		act( () => root.render( <ScalapayWidget widget={ widget } amountKey={ 30000 } /> ) );
		expect( container.querySelector( 'scalapay-widget' ) ).not.toBe( first );
		expect( container.querySelectorAll( 'scalapay-widget' ) ).toHaveLength( 1 );

		act( () => root.unmount() );
	} );

	it( 'renders nothing without a widget', () => {
		expect( render( { widget: null, amountKey: 24000 } ).innerHTML ).toBe( '' );
		expect( render( { widget: undefined, amountKey: 24000 } ).innerHTML ).toBe( '' );
	} );
} );
