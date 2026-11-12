/**
 * Frenet tracking (admin): update now, save a code on the order screen and the bulk import with a
 * preview before applying. Uses frenetLabels.ajaxUrl / nonce (admin-labels.js) and frenetTracking.i18n.
 */
( function () {
	'use strict';
	if ( typeof frenetLabels === 'undefined' || typeof frenetTracking === 'undefined' ) {
		return;
	}
	var t = frenetTracking.i18n;

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', 'frenet_tracking_' + action );
		body.append( 'nonce', frenetLabels.nonce );
		Object.keys( data || {} ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( frenetLabels.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json().catch( function () { return null; } );
			}, function () {
				return null;
			} )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( ( j && j.data && j.data.message ) || frenetLabels.i18n.error );
				}
				return j.data;
			} );
	}

	function say( el, text, kind ) {
		if ( el ) {
			el.textContent = text || '';
			el.className = 'frenet-status-line' + ( kind ? ' is-' + kind : '' );
		}
	}

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( str ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var v = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
			return v === undefined ? m : String( v );
		} );
	}

	function plural( forms, n ) {
		return 1 === Number( n ) ? forms.one : forms.other;
	}

	function count( forms, n ) {
		return fmt( plural( forms, n ), n );
	}

	function node( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text !== undefined && text !== null ) {
			n.textContent = String( text );
		}
		return n;
	}

	function paintEvent( el, s ) {
		el.textContent = '';
		if ( s.description ) {
			el.appendChild( node( 'strong', '', s.description ) );
			el.appendChild( node( 'br' ) );
			el.appendChild( node( 'span', 'frenet-muted', s.when ) );
		} else {
			el.appendChild( node( 'span', 'frenet-muted', t.noMovement ) );
		}
	}

	// Update one order (row of the tracking page or the order box).
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-frenet-track-sync]' );
		if ( ! btn ) {
			return;
		}
		var id = btn.getAttribute( 'data-frenet-track-sync' );
		var row = btn.closest( '[data-frenet-track-row]' );
		var box = btn.closest( '[data-frenet-tracking-box]' );
		var msg = box ? box.querySelector( '[data-frenet-track-msg]' ) : document.querySelector( '[data-frenet-track-all-status]' );
		btn.disabled = true;
		say( msg, t.updating, 'loading' );
		post( 'sync', { order: id } ).then( function ( s ) {
			var holder = row || box;
			var ev = holder.querySelector( '[data-frenet-track-event]' );
			if ( ev ) {
				paintEvent( ev, s );
			}
			var st = holder.querySelector( '[data-frenet-track-status]' );
			if ( st ) {
				st.textContent = s.status;
			}
			var sy = holder.querySelector( '[data-frenet-track-synced]' );
			if ( sy ) {
				sy.textContent = s.synced;
			}
			// No events is normal right after the label is created: say so in a calm tone, never as an error.
			if ( s.note ) {
				say( msg, fmt( t.checkedNote, s.note ), 'warn' );
			} else if ( s.pending ) {
				say( msg, t.checkedNone, 'ok' );
			} else {
				say( msg, t.updated, 'ok' );
			}
		} ).catch( function ( err ) {
			say( msg, err.message, 'error' );
		} ).then( function () {
			btn.disabled = false;
		} );
	} );

	// Update all now.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-frenet-track-all]' );
		if ( ! btn ) {
			return;
		}
		var msg = document.querySelector( '[data-frenet-track-all-status]' );
		var sum = { synced: 0, changed: 0, pending: 0, errors: 0 };
		var rounds = 0;
		var step = function ( offset ) {
			return post( 'sync_all', { offset: offset } ).then( function ( r ) {
				Object.keys( sum ).forEach( function ( k ) { sum[ k ] += Number( r[ k ] || 0 ); } );
				rounds++;
				if ( false === r.done && r.remaining > 0 && Number( r.synced ) + Number( r.errors ) > 0 && rounds < 200 ) {
					say( msg, fmt( t.progress, sum.synced + sum.errors, sum.synced + sum.errors + r.remaining ), 'loading' );
					return step( r.offset );
				}
				return sum;
			} );
		};
		btn.disabled = true;
		say( msg, t.updating, 'loading' );
		step( 0 ).then( function ( r ) {
			say( msg, [ count( t.synced, r.synced ), count( t.changed, r.changed ), count( t.pending, r.pending ), count( t.errors, r.errors ) ].join( ' ' ), r.errors ? 'warn' : 'ok' );
			window.setTimeout( function () { window.location.reload(); }, 1500 );
		} ).catch( function ( err ) {
			say( msg, err.message, 'error' );
			btn.disabled = false;
		} );
	} );

	// Save a code on the order screen.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-frenet-track-save]' );
		if ( ! btn ) {
			return;
		}
		var box = btn.closest( '[data-frenet-tracking-box]' );
		var msg = box.querySelector( '[data-frenet-track-msg]' );
		var code = box.querySelector( '[data-frenet-track-code]' ).value;
		var notify = box.querySelector( '[data-frenet-track-notify]' ).checked ? '1' : '';
		btn.disabled = true;
		say( msg, t.saving, 'loading' );
		post( 'save', { order: box.getAttribute( 'data-order' ), code: code, notify: notify } ).then( function ( s ) {
			box.querySelector( '[data-frenet-track-code]' ).value = s.code;
			say( msg, s.code ? t.saved : t.removed, 'ok' );
		} ).catch( function ( err ) {
			say( msg, err.message, 'error' );
		} ).then( function () {
			btn.disabled = false;
		} );
	} );

	// Bulk import: review, then apply.
	var imp = document.querySelector( '[data-frenet-import]' );
	if ( ! imp ) {
		return;
	}
	var lines = imp.querySelector( '#frenet-import-lines' );
	var notifyBox = imp.querySelector( '#frenet-import-notify' );
	var status = imp.querySelector( '[data-frenet-import-status]' );
	var box = imp.querySelector( '[data-frenet-import-preview-box]' );
	var rows = imp.querySelector( '[data-frenet-import-rows]' );
	var apply = imp.querySelector( '[data-frenet-import-apply]' );
	var reviewBtn = imp.querySelector( '[data-frenet-import-preview]' );
	var labels = { add: [ t.resultAdd, 'ok' ], replace: [ t.resultRep, 'wait' ], same: [ t.resultSame, 'off' ], not_found: [ t.resultNone, 'bad' ], duplicate: [ t.resultDup, 'off' ] };

	function previewRow( r ) {
		var l = labels[ r.result ] || [ r.result, 'off' ];
		var tr = node( 'tr' );
		var order = node( 'td' );
		var link;
		tr.appendChild( node( 'td', '', r.line ) );
		if ( r.url && /^https?:\/\//i.test( r.url ) ) {
			link = node( 'a', '', '#' + r.order );
			link.href = r.url;
			order.appendChild( link );
		} else {
			order.textContent = '#' + r.order;
		}
		if ( r.status ) {
			order.appendChild( node( 'br' ) );
			order.appendChild( node( 'span', 'frenet-muted', r.status ) );
		}
		tr.appendChild( order );
		tr.appendChild( node( 'td', 'frenet-code', r.code ) );
		tr.appendChild( node( 'td', 'frenet-code', r.current || '—' ) );
		tr.appendChild( node( 'td' ) ).appendChild( node( 'span', 'frenet-pill is-' + l[ 1 ], l[ 0 ] ) );
		return tr;
	}

	function editMode() {
		box.hidden = true;
		lines.readOnly = false;
		reviewBtn.hidden = false;
	}

	reviewBtn.addEventListener( 'click', function () {
		if ( ! lines.value.trim() ) {
			say( status, t.paste, 'error' );
			lines.focus();
			return;
		}
		reviewBtn.disabled = true;
		say( status, '', 'loading' );
		post( 'preview', { lines: lines.value } ).then( function ( p ) {
			rows.textContent = '';
			p.rows.forEach( function ( r ) {
				rows.appendChild( previewRow( r ) );
			} );
			var parts = [];
			if ( p.invalid.length ) {
				parts.push( fmt( t.invalid, p.invalid.join( ', ' ) ) );
			}
			if ( ! p.apply ) {
				parts.unshift( t.nothing );
			}
			say( status, parts.join( ' ' ), p.invalid.length || ! p.apply ? 'warn' : '' );
			apply.textContent = count( t.apply, p.apply );
			apply.disabled = ! p.apply;
			box.hidden = false;
			lines.readOnly = true;
			reviewBtn.hidden = true;
			apply.focus();
		} ).catch( function ( err ) {
			say( status, err.message, 'error' );
		} ).then( function () {
			reviewBtn.disabled = false;
		} );
	} );

	imp.querySelector( '[data-frenet-import-back]' ).addEventListener( 'click', function () {
		editMode();
		say( status, '' );
		lines.focus();
	} );

	apply.addEventListener( 'click', function () {
		apply.disabled = true;
		say( status, t.saving, 'loading' );
		post( 'import', { lines: lines.value, notify: notifyBox.checked ? '1' : '' } ).then( function ( r ) {
			say( status, count( t.applied, r.applied ) + ' ' + count( t.skipped, r.skipped ), 'ok' );
			lines.value = '';
			editMode();
		} ).catch( function ( err ) {
			say( status, err.message, 'error' );
			apply.disabled = false;
		} );
	} );
} )();
