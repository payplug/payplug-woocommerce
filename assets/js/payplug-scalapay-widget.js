/* global jQuery */
( function ( $ ) {
	'use strict';

	// Fresh copies of the product widgets (same attributes) read the amount of the selected variation.
	function remountWidgets() {
		document.querySelectorAll( 'scalapay-widget[type="product"]' ).forEach( function ( widget ) {
			widget.replaceWith( widget.cloneNode( false ) );
		} );
	}

	$( function () {
		$( 'form.variations_form' ).on( 'found_variation reset_data', function () {
			window.setTimeout( remountWidgets, 0 );
		} );
	} );
}( jQuery ) );
