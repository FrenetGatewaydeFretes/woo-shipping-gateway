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
			.then( function ( r ) { return r.json(); } )
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

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = s == null ? '' : String( s );
		return d.innerHTML;
	}

	function eventHtml( s ) {
		if ( s.description ) {
			return '<strong>' + esc( s.description ) + '</strong><br><span class="frenet-muted">' + esc( s.when ) + '</span>';
		}
		return '<span class="frenet-muted">' + esc( frenetTracking.i18n.updated ) + '</span>';
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
				ev.innerHTML = eventHtml( s );
			}
			var st = holder.querySelector( '[data-frenet-track-status]' );
			if ( st ) {
				st.textContent = s.status;
			}
			var sy = holder.querySelector( '[data-frenet-track-synced]' );
			if ( sy ) {
				sy.textContent = s.synced;
			}
			say( msg, t.updated, 'ok' );
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
		btn.disabled = true;
		say( msg, t.updating, 'loading' );
		post( 'sync_all', {} ).then( function ( r ) {
			say( msg, t.syncedAll.replace( '%1$d', r.synced ).replace( '%2$d', r.changed ).replace( '%3$d', r.errors ), r.errors ? 'warn' : 'ok' );
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
			rows.innerHTML = p.rows.map( function ( r ) {
				var l = labels[ r.result ] || [ r.result, 'off' ];
				var order = r.url ? '<a href="' + esc( r.url ) + '">#' + esc( r.order ) + '</a>' : '#' + esc( r.order );
				return '<tr><td>' + esc( r.line ) + '</td><td>' + order + ( r.status ? '<br><span class="frenet-muted">' + esc( r.status ) + '</span>' : '' ) +
					'</td><td class="frenet-code">' + esc( r.code ) + '</td><td class="frenet-code">' + esc( r.current || '—' ) +
					'</td><td><span class="frenet-pill is-' + l[ 1 ] + '">' + esc( l[ 0 ] ) + '</span></td></tr>';
			} ).join( '' );
			var parts = [];
			if ( p.invalid.length ) {
				parts.push( t.invalid.replace( '%s', p.invalid.join( ', ' ) ) );
			}
			if ( ! p.apply ) {
				parts.unshift( t.nothing );
			}
			say( status, parts.join( ' ' ), p.invalid.length || ! p.apply ? 'warn' : '' );
			apply.textContent = p.apply === 1 ? t.applyOne : t.applyN.replace( '%d', p.apply );
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
			say( status, t.imported.replace( '%1$d', r.applied ).replace( '%2$d', r.skipped ), 'ok' );
			lines.value = '';
			editMode();
		} ).catch( function ( err ) {
			say( status, err.message, 'error' );
			apply.disabled = false;
		} );
	} );
} )();
