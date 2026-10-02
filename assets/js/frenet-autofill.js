/**
 * Frenet: fills the address from the CEP (classic checkout, checkout/cart blocks and My Account).
 * Never overwrites the number or the complement; shows a short status under the CEP field.
 */
( function () {
	'use strict';
	if ( typeof frenetAutofill === 'undefined' ) {
		return;
	}
	var cache = {};

	function lookup( cep ) {
		cep = String( cep || '' ).replace( /\D/g, '' );
		if ( cep.length !== 8 ) {
			return Promise.resolve( null );
		}
		if ( ! cache[ cep ] ) {
			var body = new URLSearchParams( { action: frenetAutofill.action, nonce: frenetAutofill.nonce, cep: cep } );
			cache[ cep ] = fetch( frenetAutofill.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( j ) { return j && j.success ? j.data : { error: ( j && j.data && j.data.message ) || frenetAutofill.notFound }; } )
				.catch( function () { return { error: frenetAutofill.notFound }; } );
		}
		return cache[ cep ];
	}

	// Street + neighborhood go in address_1 unless the store has its own neighborhood field.
	function street( a, hasDistrictField ) {
		return hasDistrictField || ! a.district ? a.address_1 : a.address_1 + ' - ' + a.district;
	}

	// Status line under the CEP field (aria-live, so screen readers hear it).
	function status( input, text, kind ) {
		if ( ! input ) {
			return;
		}
		var holder = input.closest( '.form-row, .wc-block-components-text-input, p' ) || input.parentNode;
		var el = holder.querySelector( '.frenet-cep-status' );
		if ( ! el ) {
			el = document.createElement( 'span' );
			el.className = 'frenet-cep-status';
			el.setAttribute( 'role', 'status' );
			el.setAttribute( 'aria-live', 'polite' );
			holder.appendChild( el );
		}
		el.textContent = text || '';
		el.className = 'frenet-cep-status' + ( kind ? ' is-' + kind : '' );
		input.setAttribute( 'aria-busy', kind === 'loading' ? 'true' : 'false' );
	}

	function run( input, fill ) {
		status( input, frenetAutofill.loading, 'loading' );
		lookup( input.value ).then( function ( a ) {
			if ( ! a ) {
				status( input, '' );
				return;
			}
			if ( a.error ) {
				status( input, a.error, 'error' );
				return;
			}
			status( input, '' );
			fill( a );
		} );
	}

	// ---- Classic checkout and My Account (inputs billing_postcode / shipping_postcode) ----
	function setValue( el, value ) {
		if ( ! el || ! value ) {
			return;
		}
		el.value = value;
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		if ( window.jQuery ) {
			window.jQuery( el ).trigger( 'change' );
		}
	}

	function fillClassic( prefix, a ) {
		var get = function ( n ) { return document.getElementById( prefix + '_' + n ); };
		var district = get( 'neighborhood' ); // Brazilian market plugins add this field.
		setValue( get( 'address_1' ), street( a, !! district ) );
		setValue( district, a.district );
		setValue( get( 'city' ), a.city );
		setValue( get( 'state' ), a.state );
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'update_checkout' );
		}
		var number = get( 'number' ) || get( 'address_2' );
		if ( number && ! number.value ) {
			number.focus();
		}
	}

	var lastClassic = {};
	function onClassic( e ) {
		var id = e.target && e.target.id;
		var m = id && id.match( /^(billing|shipping)_postcode$/ );
		if ( ! m ) {
			return;
		}
		var cep = String( e.target.value ).replace( /\D/g, '' );
		if ( cep.length !== 8 || lastClassic[ m[ 1 ] ] === cep ) {
			return;
		}
		lastClassic[ m[ 1 ] ] = cep;
		run( e.target, function ( a ) { fillClassic( m[ 1 ], a ); } );
	}
	document.addEventListener( 'input', onClassic );
	document.addEventListener( 'change', onClassic );

	// ---- Checkout/cart blocks (React state in the wc/store/cart data store) ----
	if ( ! window.wp || ! wp.data || ! wp.data.select( 'wc/store/cart' ) ) {
		return;
	}
	var last = { billing: '', shipping: '' };
	wp.data.subscribe( function () {
		var store = wp.data.select( 'wc/store/cart' );
		var data = store && store.getCustomerData ? store.getCustomerData() : null;
		if ( ! data ) {
			return;
		}
		[ 'shipping', 'billing' ].forEach( function ( type ) {
			var addr = type === 'shipping' ? data.shippingAddress : data.billingAddress;
			var cep = addr ? String( addr.postcode || '' ).replace( /\D/g, '' ) : '';
			if ( cep.length !== 8 || cep === last[ type ] ) {
				return;
			}
			last[ type ] = cep;
			var input = document.getElementById( type + '-postcode' );
			run( input, function ( a ) {
				var patch = { address_1: street( a, false ), city: a.city, state: a.state, country: addr.country || 'BR' };
				var dispatch = wp.data.dispatch( 'wc/store/cart' );
				if ( type === 'shipping' && dispatch.setShippingAddress ) {
					dispatch.setShippingAddress( patch );
				} else if ( type === 'billing' && dispatch.setBillingAddress ) {
					dispatch.setBillingAddress( patch );
				}
			} );
		} );
	} );
} )();
