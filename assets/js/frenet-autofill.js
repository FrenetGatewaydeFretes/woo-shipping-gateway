/**
 * Frenet: fills the address from the CEP (classic checkout, checkout/cart blocks and My Account).
 * Never overwrites the number, the complement or what the customer typed; shows a short status under the CEP field.
 */
( function () {
	'use strict';
	if ( typeof frenetAutofill === 'undefined' ) {
		return;
	}
	var cache = {};
	var filled = { billing: {}, shipping: {} };
	var initial = { billing: {}, shipping: {} };

	function digits( value ) {
		return String( value || '' ).replace( /\D/g, '' );
	}

	function lookup( cep ) {
		cep = digits( cep );
		if ( cep.length !== 8 ) {
			return Promise.resolve( null );
		}
		if ( ! cache[ cep ] ) {
			var body = new URLSearchParams( { action: frenetAutofill.action, nonce: frenetAutofill.nonce, cep: cep } );
			var failed = function ( message ) {
				delete cache[ cep ];
				return { error: message || frenetAutofill.notFound };
			};
			cache[ cep ] = fetch( frenetAutofill.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) {
					return r.json().then( function ( j ) {
						var message = j && j.data && j.data.message;
						if ( ! r.ok ) {
							return failed( message );
						}
						return j && j.success ? j.data : { error: message || frenetAutofill.notFound };
					} );
				} )
				.catch( function () {
					return failed();
				} );
		}
		return cache[ cep ];
	}

	// Street + neighborhood go in address_1 unless the store has its own neighborhood field.
	function street( a, hasDistrictField ) {
		return hasDistrictField || ! a.district ? a.address_1 : a.address_1 + ' - ' + a.district;
	}

	function writable( type, key, current, strict ) {
		current = String( current || '' ).trim();
		return '' === current || current === filled[ type ][ key ] || ( ! strict && current === initial[ type ][ key ] );
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

	// The CEP comes as a value, not from the input: on block pages the billing form (and its input) is not rendered
	// while "use the same address for billing" is checked.
	function run( input, cep, fill ) {
		status( input, frenetAutofill.loading, 'loading' );
		lookup( cep ).then( function ( a ) {
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
	function field( prefix, name ) {
		return document.getElementById( prefix + '_' + name );
	}

	function setValue( prefix, key, el, value, strict ) {
		if ( ! el || ! value || ! writable( prefix, key, el.value, strict ) ) {
			return;
		}
		filled[ prefix ][ key ] = value;
		if ( el.value === value ) {
			return;
		}
		el.value = value;
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		if ( window.jQuery ) {
			window.jQuery( el ).trigger( 'change' );
		}
	}

	function fillClassic( prefix, a, input ) {
		var district = field( prefix, 'neighborhood' ); // Brazilian market plugins add this field.
		setValue( prefix, 'address_1', field( prefix, 'address_1' ), street( a, !! district ), true );
		setValue( prefix, 'district', district, a.district, false );
		setValue( prefix, 'city', field( prefix, 'city' ), a.city, false );
		setValue( prefix, 'state', field( prefix, 'state' ), a.state, false );
		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'update_checkout' );
		}
		var number = field( prefix, 'number' ) || field( prefix, 'address_2' );
		if ( number && ! number.value && document.activeElement === input ) {
			number.focus();
		}
	}

	var lastClassic = {};
	[ 'billing', 'shipping' ].forEach( function ( prefix ) {
		var postcode = field( prefix, 'postcode' );
		lastClassic[ prefix ] = postcode ? digits( postcode.value ) : '';
		[ 'address_1', 'city', 'state' ].forEach( function ( key ) {
			var el = field( prefix, key );
			initial[ prefix ][ key ] = el ? String( el.value || '' ).trim() : '';
		} );
		var district = field( prefix, 'neighborhood' );
		initial[ prefix ].district = district ? String( district.value || '' ).trim() : '';
	} );

	function onClassic( e ) {
		var id = e.target && e.target.id;
		var m = id && id.match( /^(billing|shipping)_postcode$/ );
		if ( ! m ) {
			return;
		}
		var cep = digits( e.target.value );
		if ( cep.length !== 8 || lastClassic[ m[ 1 ] ] === cep ) {
			return;
		}
		lastClassic[ m[ 1 ] ] = cep;
		run( e.target, cep, function ( a ) {
			fillClassic( m[ 1 ], a, e.target );
		} );
	}
	document.addEventListener( 'input', onClassic );
	document.addEventListener( 'change', onClassic );

	// ---- Checkout/cart blocks (React state in the wc/store/cart data store) ----
	if ( ! window.wp || ! wp.data || ! wp.data.select( 'wc/store/cart' ) ) {
		return;
	}
	var types = [ 'shipping', 'billing' ];
	var last = { billing: '', shipping: '' };
	var hydrated = false;

	function customerData() {
		var store = wp.data.select( 'wc/store/cart' );
		return store && store.getCustomerData ? store.getCustomerData() : null;
	}

	function addressOf( data, type ) {
		return ( type === 'shipping' ? data.shippingAddress : data.billingAddress ) || {};
	}

	// Neighborhood added as a block-checkout field by Brazilian plugins (e.g. "br-checkout-fields/billing_neighborhood").
	function districtKeyOf( addr ) {
		return Object.keys( addr ).filter( function ( k ) {
			return /(^|\/)((billing|shipping)_)?neighborhood$/.test( k );
		} )[ 0 ];
	}

	function seed( data ) {
		types.forEach( function ( type ) {
			var addr = addressOf( data, type );
			var districtKey = districtKeyOf( addr );
			last[ type ] = digits( addr.postcode );
			initial[ type ] = {
				address_1: String( addr.address_1 || '' ).trim(),
				city: String( addr.city || '' ).trim(),
				state: String( addr.state || '' ).trim(),
				district: districtKey ? String( addr[ districtKey ] || '' ).trim() : ''
			};
		} );
	}

	function resolved() {
		var store = wp.data.select( 'wc/store/cart' );
		return ! store.hasFinishedResolution || store.hasFinishedResolution( 'getCartData' );
	}

	function patchFor( type, addr, a ) {
		var districtKey = districtKeyOf( addr );
		var patch = {};
		var add = function ( key, storeKey, value, strict ) {
			if ( value && writable( type, key, addr[ storeKey ], strict ) ) {
				filled[ type ][ key ] = value;
				if ( addr[ storeKey ] !== value ) {
					patch[ storeKey ] = value;
				}
			}
		};
		add( 'address_1', 'address_1', street( a, !! districtKey ), true );
		add( 'city', 'city', a.city, false );
		add( 'state', 'state', a.state, false );
		if ( districtKey ) {
			add( 'district', districtKey, a.district, false );
		}
		if ( Object.keys( patch ).length && ! addr.country ) {
			patch.country = 'BR';
		}
		return patch;
	}

	var current = customerData();
	if ( current && resolved() ) {
		seed( current );
		hydrated = true;
	}

	wp.data.subscribe( function () {
		var data = customerData();
		if ( ! data || ! resolved() ) {
			return;
		}
		if ( ! hydrated ) {
			seed( data );
			hydrated = true;
			return;
		}
		types.forEach( function ( type ) {
			var cep = digits( addressOf( data, type ).postcode );
			if ( cep.length !== 8 || cep === last[ type ] ) {
				return;
			}
			last[ type ] = cep;
			run( document.getElementById( type + '-postcode' ), cep, function ( a ) {
				var patch = patchFor( type, addressOf( customerData() || data, type ), a );
				var dispatch = wp.data.dispatch( 'wc/store/cart' );
				if ( ! Object.keys( patch ).length ) {
					return;
				}
				if ( type === 'shipping' && dispatch.setShippingAddress ) {
					dispatch.setShippingAddress( patch );
				} else if ( type === 'billing' && dispatch.setBillingAddress ) {
					dispatch.setBillingAddress( patch );
				}
			} );
		} );
	} );
} )();
