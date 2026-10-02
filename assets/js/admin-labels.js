/**
 * Frenet labels admin: carrier comparison, review before charging, cart payment, cancel, NF-e, tracking.
 * No framework; every request goes to admin-ajax.php with the frenet_labels nonce.
 */
( function () {
	'use strict';

	var cfg = window.frenetLabels;
	if ( ! cfg ) {
		return;
	}
	var t = cfg.i18n;

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return str.replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var v = pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
			return v === undefined ? m : String( v );
		} );
	}

	function money( v ) {
		return 'R$ ' + Number( v || 0 ).toLocaleString( 'pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', 'frenet_labels_' + action );
		body.append( 'nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			var v = data[ k ];
			if ( Array.isArray( v ) ) {
				v.forEach( function ( x ) {
					body.append( k + '[]', x );
				} );
			} else {
				body.append( k, v );
			}
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.catch( function () {
				return { success: false, data: { message: t.error } };
			} );
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text !== undefined ) {
			n.textContent = text;
		}
		return n;
	}

	function pill( label, tone ) {
		return el( 'span', 'frenet-pill is-' + ( tone || 'wait' ), label );
	}

	function balanceNow() {
		var b = document.querySelector( '[data-frenet-wallet] [data-balance]' );
		return b ? parseFloat( b.getAttribute( 'data-balance' ) ) : null;
	}

	function refreshWallet() {
		post( 'wallet' ).then( function ( res ) {
			if ( ! res.success ) {
				return;
			}
			document.querySelectorAll( '[data-frenet-wallet] [data-balance]' ).forEach( function ( b ) {
				b.setAttribute( 'data-balance', res.data.balance );
				b.textContent = money( res.data.balance );
			} );
		} );
	}

	/* ---------------------------------------------------------------- Criar envio */

	var create = document.querySelector( '[data-frenet-create]' );
	if ( create ) {
		var rows = Array.prototype.slice.call( create.querySelectorAll( '[data-frenet-row]' ) );
		var quotes = {};
		var status = create.querySelector( '[data-frenet-status]' );
		var btnCalc = create.querySelector( '[data-frenet-calc]' );
		var btnClear = create.querySelector( '[data-frenet-clear]' );
		var btnReview = create.querySelector( '[data-frenet-review]' );
		var dialog = document.querySelector( '[data-frenet-dialog]' );
		var summary = document.querySelector( '[data-frenet-summary]' );

		var journey = function () {
			var r = document.querySelector( 'input[name="frenet_journey"]:checked' );
			return r ? r.value : cfg.journey;
		};
		var picked = function () {
			return rows.filter( function ( r ) {
				return r.querySelector( '[data-frenet-pick]' ).checked && ! r.hasAttribute( 'data-done' );
			} );
		};

		create.querySelector( '[data-frenet-all]' ).addEventListener( 'change', function ( e ) {
			rows.forEach( function ( r ) {
				r.querySelector( '[data-frenet-pick]' ).checked = e.target.checked;
			} );
			renderSummary();
		} );
		rows.forEach( function ( r ) {
			r.querySelector( '[data-frenet-pick]' ).addEventListener( 'change', renderSummary );
		} );

		var renderService = function ( row, data ) {
			var cell = row.querySelector( '.frenet-service-cell' );
			cell.textContent = '';
			if ( ! data.ok ) {
				cell.appendChild( el( 'span', 'frenet-error', data.message ) );
				return;
			}
			var select = el( 'select', 'frenet-select' );
			select.setAttribute( 'data-frenet-choice', '' );
			select.setAttribute( 'aria-label', t.bestPrice );
			data.services.forEach( function ( s ) {
				var tags = [];
				if ( s.code === data.best.best_price ) {
					tags.push( t.bestPrice );
				}
				if ( s.code === data.best.best_time ) {
					tags.push( t.bestTime );
				}
				if ( s.code === data.chosen ) {
					tags.push( t.customer );
				}
				var o = el( 'option', '', s.carrier + ' · ' + s.name + ' — ' + money( s.price ) + ' · ' + fmt( 1 === Number( s.days ) ? t.day : t.days, s.days ) + ( tags.length ? ' (' + tags.join( ', ' ) + ')' : '' ) );
				o.value = s.code;
				o.selected = s.code === data['default'];
				select.appendChild( o );
			} );
			select.addEventListener( 'change', renderSummary );
			cell.appendChild( select );
		};

		var renderSummary = function () {
			var list = picked().map( function ( r ) {
				var q = quotes[ r.getAttribute( 'data-order' ) ];
				return q && q.ok ? q.services : null;
			} ).filter( Boolean );
			if ( ! list.length ) {
				summary.hidden = true;
				btnReview.disabled = true;
				return;
			}
			var carriers = {};
			var best = 0;
			list.forEach( function ( services ) {
				best += Math.min.apply( null, services.map( function ( s ) {
					return s.price;
				} ) );
				var by = {};
				services.forEach( function ( s ) {
					var name = s.carrier || s.carrier_code;
					( by[ name ] = by[ name ] || [] ).push( s );
				} );
				Object.keys( by ).forEach( function ( name ) {
					var l = by[ name ];
					var cheap = l.slice().sort( function ( a, b ) {
						return a.price - b.price || a.days - b.days;
					} )[ 0 ];
					var fast = l.slice().sort( function ( a, b ) {
						return ( a.days || 999 ) - ( b.days || 999 ) || a.price - b.price;
					} )[ 0 ];
					var c = carriers[ name ] = carriers[ name ] || { name: name, available: 0, price: 0, time: 0 };
					c.available++;
					c.price += cheap.price;
					c.time += fast.price;
				} );
			} );
			var body = summary.querySelector( '[data-frenet-summary-rows]' );
			body.textContent = '';
			Object.keys( carriers ).map( function ( k ) {
				return carriers[ k ];
			} ).sort( function ( a, b ) {
				return b.available - a.available || a.price - b.price;
			} ).forEach( function ( c ) {
				var tr = el( 'tr' );
				tr.appendChild( el( 'td', '', c.name ) );
				tr.appendChild( el( 'td', '', c.available + ' / ' + list.length ) );
				tr.appendChild( el( 'td', 'is-num', money( c.price ) ) );
				tr.appendChild( el( 'td', 'is-num', money( c.time ) ) );
				body.appendChild( tr );
			} );
			summary.querySelector( '[data-frenet-best-total]' ).textContent = money( best );
			summary.hidden = false;
			btnReview.disabled = false;
		};

		btnCalc.addEventListener( 'click', function () {
			var list = picked();
			if ( ! list.length ) {
				status.textContent = t.selectOrders;
				return;
			}
			btnCalc.disabled = true;
			status.textContent = t.calculating;
			var i = 0;
			var next = function () {
				if ( i >= list.length ) {
					btnCalc.disabled = false;
					btnClear.disabled = false;
					btnCalc.classList.remove( 'button-primary' );
					status.textContent = '';
					renderSummary();
					btnReview.focus();
					return;
				}
				var row = list[ i++ ];
				row.querySelector( '.frenet-service-cell' ).textContent = t.calculating;
				post( 'quote', { order_id: row.getAttribute( 'data-order' ) } ).then( function ( res ) {
					var data = res.success ? Object.assign( { ok: true }, res.data ) : { ok: false, message: ( res.data && res.data.message ) || t.error };
					quotes[ row.getAttribute( 'data-order' ) ] = data;
					renderService( row, data );
					next();
				} );
			};
			next();
		} );

		btnClear.addEventListener( 'click', function () {
			quotes = {};
			rows.forEach( function ( r ) {
				if ( ! r.hasAttribute( 'data-done' ) ) {
					r.querySelector( '.frenet-service-cell' ).textContent = '';
				}
			} );
			summary.hidden = true;
			btnReview.disabled = true;
			btnClear.disabled = true;
			btnCalc.classList.add( 'button-primary' );
		} );

		var selection = function () {
			return picked().map( function ( r ) {
				var q = quotes[ r.getAttribute( 'data-order' ) ];
				var sel = r.querySelector( '[data-frenet-choice]' );
				if ( ! q || ! q.ok || ! sel ) {
					return null;
				}
				var s = q.services.filter( function ( x ) {
					return x.code === sel.value;
				} )[ 0 ];
				return { row: r, service: s };
			} ).filter( Boolean );
		};

		btnReview.addEventListener( 'click', function () {
			var sel = selection();
			if ( ! sel.length ) {
				status.textContent = t.selectOrders;
				return;
			}
			var by = {};
			var total = 0;
			sel.forEach( function ( x ) {
				var name = x.service.carrier || x.service.carrier_code;
				by[ name ] = by[ name ] || { n: 0, sum: 0 };
				by[ name ].n++;
				by[ name ].sum += x.service.price;
				total += x.service.price;
			} );
			var body = dialog.querySelector( '[data-frenet-review-rows]' );
			body.textContent = '';
			Object.keys( by ).forEach( function ( name ) {
				var tr = el( 'tr' );
				tr.appendChild( el( 'td', '', name ) );
				tr.appendChild( el( 'td', 'is-num', String( by[ name ].n ) ) );
				tr.appendChild( el( 'td', 'is-num', money( by[ name ].sum ) ) );
				body.appendChild( tr );
			} );
			dialog.querySelector( '[data-frenet-review-total]' ).textContent = money( total );
			var j = journey();
			var pays = 'here' === j && cfg.wallet;
			var now = balanceNow();
			var balanceBox = dialog.querySelector( '[data-frenet-review-balance]' );
			var warn = dialog.querySelector( '[data-frenet-review-warning]' );
			var confirm = dialog.querySelector( '[data-frenet-confirm]' );
			balanceBox.hidden = ! pays || null === now;
			warn.hidden = true;
			confirm.disabled = false;
			if ( pays && null !== now ) {
				dialog.querySelector( '[data-frenet-balance-now]' ).textContent = money( now );
				var after = dialog.querySelector( '[data-frenet-balance-after]' );
				after.textContent = money( now - total );
				after.classList.toggle( 'is-bad', now - total < 0 );
				if ( now - total < 0 ) {
					warn.textContent = t.notEnough;
					warn.hidden = false;
					confirm.disabled = true;
				}
			}
			if ( pays ) {
				confirm.textContent = 1 === sel.length ? fmt( t.buyHereOne, money( total ) ) : fmt( t.buyHere, sel.length, money( total ) );
			} else if ( 'panel' === j ) {
				confirm.textContent = 1 === sel.length ? t.sendPanelOne : fmt( t.sendPanel, sel.length );
			} else {
				confirm.textContent = fmt( t.createOnly, sel.length );
			}
			dialog.querySelector( '[data-frenet-review-note]' ).textContent = '';
			dialog.returnValue = '';
			dialog.showModal();
		} );

		dialog.addEventListener( 'close', function () {
			if ( 'confirm' !== dialog.returnValue ) {
				return;
			}
			var sel = selection();
			var j = journey();
			btnReview.disabled = true;
			btnCalc.disabled = true;
			status.textContent = t.working;
			var i = 0;
			var next = function () {
				if ( i >= sel.length ) {
					status.textContent = t.done;
					btnCalc.disabled = false;
					refreshWallet();
					return;
				}
				var x = sel[ i++ ];
				var cell = x.row.querySelector( '[data-frenet-result]' );
				cell.textContent = t.working;
				post( 'create', { order_id: x.row.getAttribute( 'data-order' ), service: x.service.code, journey: j } ).then( function ( res ) {
					cell.textContent = '';
					if ( res.success ) {
						cell.appendChild( pill( res.data.status_label, res.data.tone ) );
						cell.appendChild( el( 'span', 'frenet-muted frenet-block', '#' + res.data.shipment_id + ' · ' + money( res.data.price ) ) );
						if ( res.data.message ) {
							cell.appendChild( el( 'span', 'frenet-error frenet-block', res.data.message ) );
						}
						x.row.setAttribute( 'data-done', '1' );
						x.row.querySelector( '[data-frenet-pick]' ).checked = false;
						x.row.querySelector( '[data-frenet-pick]' ).disabled = true;
					} else {
						cell.appendChild( pill( t.failed, 'bad' ) );
						cell.appendChild( el( 'span', 'frenet-error frenet-block', ( res.data && res.data.message ) || t.error ) );
					}
					next();
				} );
			};
			next();
		} );

		create.addEventListener( 'click', function ( e ) {
			var save = e.target.closest( '[data-frenet-nfe-save]' );
			if ( ! save ) {
				return;
			}
			var row = save.closest( '[data-frenet-row]' );
			var input = row.querySelector( '[data-frenet-nfe]' );
			var msg = row.querySelector( '.frenet-field-msg' );
			msg.textContent = '';
			post( 'nfe', { order_id: row.getAttribute( 'data-order' ), key: input.value } ).then( function ( res ) {
				if ( res.success ) {
					row.querySelector( '[data-frenet-nfe-summary]' ).textContent = res.data.summary || '';
					msg.textContent = t.saved;
					input.removeAttribute( 'aria-invalid' );
				} else {
					msg.textContent = ( res.data && res.data.message ) || t.error;
					input.setAttribute( 'aria-invalid', 'true' );
					input.focus();
				}
			} );
		} );
	}

	/* ---------------------------------------------------------------- Etiquetas: cart, print, cancel */

	var labels = document.querySelector( '[data-frenet-labels]' );
	if ( labels ) {
		var lstatus = labels.querySelector( '[data-frenet-status]' );
		var btnPay = labels.querySelector( '[data-frenet-pay]' );
		var btnPrint = labels.querySelector( '[data-frenet-print-selected]' );
		var checked = function ( attr ) {
			return Array.prototype.slice.call( labels.querySelectorAll( '[data-frenet-label]' ) ).filter( function ( r ) {
				var c = r.querySelector( '[data-frenet-pick]' );
				return c && c.checked && '1' === r.getAttribute( attr );
			} );
		};
		var update = function () {
			var unpaid = checked( 'data-unpaid' );
			var paid = checked( 'data-paid' );
			var sum = unpaid.reduce( function ( a, r ) {
				return a + parseFloat( r.getAttribute( 'data-price' ) || 0 );
			}, 0 );
			btnPay.disabled = ! unpaid.length;
			btnPay.textContent = unpaid.length ? fmt( t.confirmPay, unpaid.length, money( sum ) ).replace( /\?$/, '' ) : btnPay.getAttribute( 'data-label' );
			if ( btnPrint ) {
				btnPrint.disabled = ! paid.length;
			}
		};
		btnPay.setAttribute( 'data-label', btnPay.textContent );
		labels.addEventListener( 'change', update );
		btnPay.addEventListener( 'click', function () {
			var unpaid = checked( 'data-unpaid' );
			var sum = unpaid.reduce( function ( a, r ) {
				return a + parseFloat( r.getAttribute( 'data-price' ) || 0 );
			}, 0 );
			if ( ! unpaid.length || ! window.confirm( fmt( t.confirmPay, unpaid.length, money( sum ) ) ) ) {
				return;
			}
			btnPay.disabled = true;
			lstatus.textContent = t.working;
			post( 'pay', { ids: unpaid.map( function ( r ) {
				return r.getAttribute( 'data-frenet-label' );
			} ) } ).then( function ( res ) {
				lstatus.textContent = res.success ? ( res.data.message || t.done ) : ( ( res.data && res.data.message ) || t.error );
				if ( res.success ) {
					window.setTimeout( function () {
						window.location.reload();
					}, 1200 );
				}
			} );
		} );
		if ( btnPrint ) {
			btnPrint.addEventListener( 'click', function () {
				var base = document.querySelector( '[data-frenet-print-base]' );
				var ids = checked( 'data-paid' ).map( function ( r ) {
					return r.getAttribute( 'data-frenet-label' );
				} );
				if ( base && ids.length ) {
					var url = new URL( base.value, window.location.href );
					url.searchParams.set( 'ids', ids.join( ',' ) );
					window.open( url.toString(), '_blank', 'noopener' );
				}
			} );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-frenet-cancel]' );
		if ( ! btn || ! window.confirm( t.confirmCancel ) ) {
			return;
		}
		var scope = btn.closest( '[data-frenet-label], [data-frenet-box]' );
		var line = ( scope && scope.querySelector( '[data-frenet-status]' ) ) || document.querySelector( '[data-frenet-labels] [data-frenet-status]' );
		btn.disabled = true;
		post( 'cancel', { id: btn.getAttribute( 'data-frenet-cancel' ) } ).then( function ( res ) {
			var p = scope && scope.querySelector( '[data-frenet-pill]' );
			if ( res.success ) {
				if ( p ) {
					p.className = 'frenet-pill is-off';
					p.textContent = res.data.status_label;
				}
				if ( line ) {
					line.textContent = res.data.message;
				}
				if ( scope && scope.hasAttribute( 'data-frenet-box' ) ) {
					window.setTimeout( function () {
						window.location.reload();
					}, 1200 );
				}
			} else {
				btn.disabled = false;
				if ( line ) {
					line.textContent = ( res.data && res.data.message ) || t.error;
				}
			}
		} );
	} );

	/* ---------------------------------------------------------------- Rastreios */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-frenet-track]' );
		if ( ! btn ) {
			return;
		}
		var cell = btn.closest( '[data-frenet-track-result]' );
		btn.disabled = true;
		post( 'tracking', { service: btn.getAttribute( 'data-service' ), number: btn.getAttribute( 'data-number' ) } ).then( function ( res ) {
			cell.textContent = '';
			if ( res.success ) {
				cell.appendChild( el( 'strong', 'frenet-block', res.data.description ) );
				cell.appendChild( el( 'span', 'frenet-muted', [ res.data.date, res.data.location ].filter( Boolean ).join( ' · ' ) ) );
			} else {
				cell.appendChild( el( 'span', 'frenet-error', ( res.data && res.data.message ) || t.error ) );
			}
		} );
	} );

	/* ---------------------------------------------------------------- Impressão: select all */

	var all = document.querySelector( '[data-frenet-print-form] [data-frenet-all]' );
	if ( all ) {
		all.addEventListener( 'change', function () {
			document.querySelectorAll( '[data-frenet-print-form] [data-frenet-pick]' ).forEach( function ( c ) {
				c.checked = all.checked;
			} );
		} );
	}
}() );
