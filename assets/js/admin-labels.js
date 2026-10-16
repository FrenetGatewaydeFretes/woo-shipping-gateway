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

	// "Atualizar saldo": balance is added in the Frenet panel; back here, read it again.
	document.querySelectorAll( '[data-frenet-wallet-refresh]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			btn.disabled = true;
			btn.textContent = t.updating;
			post( 'wallet' ).then( function ( res ) {
				btn.disabled = false;
				btn.textContent = res.success ? t.balanceOk : ( ( res.data && res.data.message ) || t.error );
				if ( res.success ) {
					document.querySelectorAll( '[data-frenet-wallet] [data-balance]' ).forEach( function ( b ) {
						b.setAttribute( 'data-balance', res.data.balance );
						b.textContent = money( res.data.balance );
					} );
				}
			} );
		} );
	} );

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

		var hasNfe = function ( row ) {
			return '1' === row.querySelector( '.frenet-nfe-cell' ).getAttribute( 'data-nfe' );
		};
		// Services an order can really ship with: without an NF-e key, the carriers that require one are left out
		// (all of them when every carrier requires it, so the screen never offers nothing).
		var usable = function ( row, services ) {
			if ( hasNfe( row ) ) {
				return services;
			}
			var ok = services.filter( function ( s ) {
				return ! s.needs_nfe;
			} );
			return ok.length ? ok : services;
		};

		var renderService = function ( row, data, keep ) {
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
				if ( s.needs_nfe && ! hasNfe( row ) ) {
					tags.push( t.needsNfe );
				}
				var o = el( 'option', '', s.carrier + ' · ' + s.name + ' — ' + money( s.price ) + ' · ' + fmt( 1 === Number( s.days ) ? t.day : t.days, s.days ) + ( tags.length ? ' (' + tags.join( ', ' ) + ')' : '' ) );
				o.value = s.code;
				o.selected = s.code === ( keep || data[ 'default' ] );
				select.appendChild( o );
			} );
			select.addEventListener( 'change', function () {
				applied = null;
				status.textContent = '';
				renderSummary();
			} );
			cell.appendChild( select );
		};

		// What the summary applied to every order: { carrier: name } | { best: 'best_price' | 'best_time' } | null.
		var applied = null;

		var carrierOf = function ( s ) {
			return s.carrier || s.carrier_code;
		};
		var cheapest = function ( list ) {
			return list.slice().sort( function ( a, b ) {
				return a.price - b.price || a.days - b.days;
			} )[ 0 ];
		};
		var fastest = function ( list ) {
			return list.slice().sort( function ( a, b ) {
				return ( a.days || 999 ) - ( b.days || 999 ) || a.price - b.price;
			} )[ 0 ];
		};
		var quoted = function () {
			return picked().filter( function ( r ) {
				var q = quotes[ r.getAttribute( 'data-order' ) ];
				return q && q.ok && r.querySelector( '[data-frenet-choice]' );
			} );
		};

		// Sets each picked order's service from the summary choice; returns how many orders lack it.
		var applyChoice = function () {
			var missing = 0;
			if ( ! applied ) {
				return 0;
			}
			quoted().forEach( function ( r ) {
				var q = quotes[ r.getAttribute( 'data-order' ) ];
				var s = null;
				if ( applied.carrier ) {
					var mine = q.services.filter( function ( x ) {
						return carrierOf( x ) === applied.carrier;
					} );
					s = mine.length ? cheapest( mine ) : null;
				} else {
					s = 'best_time' === applied.best ? fastest( usable( r, q.services ) ) : cheapest( usable( r, q.services ) );
				}
				if ( s ) {
					r.querySelector( '[data-frenet-choice]' ).value = s.code;
				} else {
					missing++;
				}
			} );
			return missing;
		};

		var choose = function ( pick, label ) {
			applied = pick;
			var missing = applyChoice();
			renderSummary();
			var n = quoted().length;
			status.textContent = missing
				? fmt( t.appliedPartly, label, n - missing, missing )
				: fmt( 1 === n ? t.appliedOne : t.applied, label, n );
			var blocked = blockedBy( selection() ).length;
			if ( blocked ) {
				status.textContent += ' ' + fmt( 1 === blocked ? t.needsNfeOne : t.needsNfeSome, blocked );
			}
		};

		var rerender = function ( row ) {
			var q = quotes[ row.getAttribute( 'data-order' ) ];
			var sel = row.querySelector( '[data-frenet-choice]' );
			if ( q && q.ok && sel ) {
				renderService( row, q, sel.value );
			}
		};
		var markNfeCarrier = function ( code ) {
			Object.keys( quotes ).forEach( function ( id ) {
				( quotes[ id ].services || [] ).forEach( function ( s ) {
					if ( s.carrier_code === code ) {
						s.needs_nfe = true;
					}
				} );
			} );
			rows.forEach( rerender );
		};

		var pickButton = function ( pick, label, active ) {
			var b = el( 'button', 'button button-small' + ( active ? ' button-primary' : '' ), active ? t.selected : t.select );
			b.type = 'button';
			b.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
			b.setAttribute( 'aria-label', fmt( t.selectFor, label ) );
			b.addEventListener( 'click', function () {
				choose( pick, label );
			} );
			return b;
		};

		// Price cell of the summary with the slowest delivery of those orders below ("up to N business days").
		var priceCell = function ( price, days ) {
			var td = el( 'td', 'is-num' );
			if ( null === price ) {
				return td;
			}
			td.appendChild( el( 'span', 'frenet-block', money( price ) ) );
			if ( days > 0 ) {
				td.appendChild( el( 'span', 'frenet-muted frenet-small', fmt( 1 === days ? t.upToDay : t.upToDays, days ) ) );
			}
			return td;
		};

		var renderSummary = function () {
			var shown = quoted();
			var list = shown.map( function ( r ) {
				return quotes[ r.getAttribute( 'data-order' ) ].services;
			} );
			if ( ! list.length ) {
				summary.hidden = true;
				btnReview.disabled = true;
				if ( applied ) {
					status.textContent = '';
				}
				return;
			}
			applyChoice();
			var carriers = {};
			var bestPrice = 0;
			var bestTime = 0;
			var bestPriceDays = 0;
			var bestTimeDays = 0;
			list.forEach( function ( services, k ) {
				var pool = usable( shown[ k ], services );
				bestPrice += cheapest( pool ).price;
				bestTime += fastest( pool ).price;
				bestPriceDays = Math.max( bestPriceDays, cheapest( pool ).days || 0 );
				bestTimeDays = Math.max( bestTimeDays, fastest( pool ).days || 0 );
				var by = {};
				services.forEach( function ( s ) {
					( by[ carrierOf( s ) ] = by[ carrierOf( s ) ] || [] ).push( s );
				} );
				Object.keys( by ).forEach( function ( name ) {
					var c = carriers[ name ] = carriers[ name ] || { name: name, available: 0, price: 0, time: 0, priceDays: 0, timeDays: 0 };
					c.available++;
					c.price += cheapest( by[ name ] ).price;
					c.time += fastest( by[ name ] ).price;
					c.priceDays = Math.max( c.priceDays, cheapest( by[ name ] ).days || 0 );
					c.timeDays = Math.max( c.timeDays, fastest( by[ name ] ).days || 0 );
				} );
			} );
			var body = summary.querySelector( '[data-frenet-summary-rows]' );
			body.textContent = '';
			Object.keys( carriers ).map( function ( k ) {
				return carriers[ k ];
			} ).sort( function ( a, b ) {
				return b.available - a.available || a.price - b.price;
			} ).forEach( function ( c ) {
				var active = !! ( applied && applied.carrier === c.name );
				var tr = el( 'tr', active ? 'is-applied' : '' );
				tr.appendChild( el( 'td', '', c.name ) );
				tr.appendChild( el( 'td', '', c.available + ' / ' + list.length ) );
				tr.appendChild( priceCell( c.price, c.priceDays ) );
				tr.appendChild( priceCell( c.time, c.timeDays ) );
				var td = el( 'td', 'is-action' );
				td.appendChild( pickButton( { carrier: c.name }, c.name, active ) );
				tr.appendChild( td );
				body.appendChild( tr );
			} );
			var best = summary.querySelector( '[data-frenet-summary-best]' );
			best.textContent = '';
			[ [ 'best_price', t.bestPriceAll, bestPrice, bestPriceDays ], [ 'best_time', t.bestTimeAll, bestTime, bestTimeDays ] ].forEach( function ( b ) {
				var active = !! ( applied && applied.best === b[ 0 ] );
				var tr = el( 'tr', active ? 'is-applied' : '' );
				tr.appendChild( el( 'th', '', b[ 1 ] ) );
				tr.firstChild.scope = 'row';
				tr.appendChild( el( 'td', '', list.length + ' / ' + list.length ) );
				tr.appendChild( priceCell( 'best_price' === b[ 0 ] ? b[ 2 ] : null, b[ 3 ] ) );
				tr.appendChild( priceCell( 'best_time' === b[ 0 ] ? b[ 2 ] : null, b[ 3 ] ) );
				var td = el( 'td', 'is-action' );
				td.appendChild( pickButton( { best: b[ 0 ] }, b[ 1 ], active ) );
				tr.appendChild( td );
				best.appendChild( tr );
			} );
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
			applied = null;
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

		// Selected orders without an NF-e key going with a carrier that requires one: Frenet refuses them.
		var blockedBy = function ( sel ) {
			return sel.filter( function ( x ) {
				return x.service && x.service.needs_nfe && ! hasNfe( x.row );
			} );
		};

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
			// Carriers that require an NF-e key refuse orders without one: say it before anything is sent.
			var blocked = blockedBy( sel );
			var nfeWarn = dialog.querySelector( '[data-frenet-review-nfe]' );
			nfeWarn.hidden = ! blocked.length;
			nfeWarn.textContent = blocked.length ? fmt( 1 === blocked.length ? t.refusedOne : t.refusedSome, blocked.length, blocked.map( function ( x ) {
				return carrierOf( x.service );
			} ).filter( function ( v, k, a ) {
				return a.indexOf( v ) === k;
			} ).join( ', ' ) ) : '';
			// No NF-e key: Frenet issues a DC-e. Only an informative note (v1); stores required to issue NF-e go back.
			var noNfe = sel.filter( function ( x ) {
				return ! hasNfe( x.row ) && blocked.indexOf( x ) === -1;
			} ).length;
			var dce = dialog.querySelector( '[data-frenet-review-dce]' );
			dce.hidden = ! noNfe;
			dialog.querySelector( '[data-frenet-review-dce-title]' ).textContent = noNfe === sel.length
				? ( 1 === noNfe ? t.dceOne : fmt( t.dceAll, noNfe ) )
				: fmt( 1 === noNfe ? t.dceSomeOne : t.dceSome, noNfe, sel.length );
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
			var failed = 0;
			var next = function () {
				if ( i >= sel.length ) {
					status.textContent = failed ? fmt( t.doneFailed, sel.length - failed, failed ) : fmt( 1 === sel.length ? t.doneAllOne : t.doneAll, sel.length );
					if ( failed ) {
						var first = create.querySelector( 'tr.is-failed' );
						if ( first ) {
							first.scrollIntoView( { behavior: 'smooth', block: 'center' } );
						}
					}
					btnCalc.disabled = false;
					refreshWallet();
					return;
				}
				var x = sel[ i++ ];
				var cell = x.row.querySelector( '[data-frenet-result]' );
				cell.textContent = t.working;
				post( 'create', { order_id: x.row.getAttribute( 'data-order' ), service: x.service.code, journey: j } ).then( function ( res ) {
					cell.textContent = '';
					// "Observações": the reason stays on the order (saved by the server) and the row turns red.
					var issue = res.data && res.data.issue ? res.data.issue : null;
					if ( ! res.success && ! issue ) {
						issue = { message: ( res.data && res.data.message ) || t.error, when: '' };
					}
					var note = x.row.querySelector( '[data-frenet-issue]' );
					note.textContent = '';
					if ( issue ) {
						note.appendChild( el( 'span', 'frenet-error frenet-block', issue.message ) );
						if ( issue.when ) {
							note.appendChild( el( 'span', 'frenet-muted frenet-block', issue.when ) );
						}
					} else {
						note.appendChild( el( 'span', 'frenet-muted', '—' ) );
					}
					x.row.classList.toggle( 'is-failed', !! issue );
					failed += issue ? 1 : 0;
					if ( res.success ) {
						cell.appendChild( pill( res.data.status_label, res.data.tone ) );
						cell.appendChild( el( 'span', 'frenet-muted frenet-block', '#' + res.data.shipment_id + ' · ' + money( res.data.price ) ) );
						x.row.setAttribute( 'data-done', '1' );
						x.row.querySelector( '[data-frenet-pick]' ).checked = false;
						x.row.querySelector( '[data-frenet-pick]' ).disabled = true;
					} else {
						cell.appendChild( pill( t.failed, 'bad' ) );
						// Frenet refused this carrier without an NF-e key: flag it in every quote of the screen too.
						if ( res.data && res.data.nfe_carrier ) {
							markNfeCarrier( res.data.nfe_carrier );
						}
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
					row.querySelector( '[data-frenet-nfe-summary]' ).textContent = res.data.summary || t.optional;
					row.querySelector( '.frenet-nfe-cell' ).setAttribute( 'data-nfe', res.data.summary ? '1' : '0' );
					rerender( row );
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

	/* ---------------------------------------------------------------- Ponto de postagem */

	var dropoff = document.querySelector( '[data-frenet-dropoff]' );
	if ( dropoff ) {
		var dEditor = dropoff.querySelector( '[data-frenet-dropoff-editor]' );
		var dCep = dropoff.querySelector( '[data-frenet-dropoff-cep]' );
		var dFilter = dropoff.querySelector( '[data-frenet-dropoff-filter]' );
		var dCount = dropoff.querySelector( '[data-frenet-dropoff-count]' );
		var dBranch = dropoff.querySelector( '[data-frenet-dropoff-branch]' );
		var dAddress = dropoff.querySelector( '[data-frenet-dropoff-address]' );
		var dSave = dropoff.querySelector( '[data-frenet-dropoff-save]' );
		var dStatus = dEditor.querySelector( '[data-frenet-status]' );
		var dRow = null;
		var points = [];
		var defaults = {};

		var label = function ( b ) {
			return b.name + ( b.initials ? ' (' + b.initials + ')' : '' );
		};
		var describe = function ( b ) {
			return b ? b.name + ( b.address ? ' — ' + b.address : '' ) : t.noDefault;
		};
		// Overview cell: point name in bold, address below; or "no default point".
		var paintRow = function ( row, b ) {
			var cell = row.querySelector( '[data-frenet-dropoff-default]' );
			cell.textContent = '';
			if ( b ) {
				cell.appendChild( el( 'strong', 'frenet-block', label( b ) ) );
				cell.appendChild( el( 'span', 'frenet-muted', b.address ) );
			} else {
				cell.appendChild( el( 'span', 'frenet-muted', t.noDefault ) );
			}
			var edit = row.querySelector( '[data-frenet-dropoff-edit]' );
			edit.disabled = false;
			edit.textContent = b ? t.change : t.choose;
		};

		dropoff.querySelectorAll( '[data-frenet-dropoff-row]' ).forEach( function ( row ) {
			var code = row.getAttribute( 'data-frenet-dropoff-row' );
			post( 'dropoff_default', { carrier: code } ).then( function ( res ) {
				if ( res.success ) {
					defaults[ code ] = res.data[ 'default' ];
					paintRow( row, defaults[ code ] );
				} else {
					var cell = row.querySelector( '[data-frenet-dropoff-default]' );
					cell.textContent = '';
					cell.appendChild( el( 'span', 'frenet-error', ( res.data && res.data.message ) || t.error ) );
					row.querySelector( '[data-frenet-dropoff-edit]' ).disabled = false;
				}
			} );
		} );

		var current = function () {
			return dRow ? defaults[ dRow.getAttribute( 'data-frenet-dropoff-row' ) ] || null : null;
		};
		var syncSave = function () {
			var p = points.filter( function ( x ) {
				return x.id === dBranch.value;
			} )[ 0 ];
			dAddress.textContent = p ? p.address : '';
			dSave.disabled = ! p || !! ( current() && current().id === p.id );
		};
		// The list can have hundreds of points: the filter keeps only the matching ones (accents ignored).
		var plain = function ( v ) {
			return String( v || '' ).normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase();
		};
		var renderPoints = function () {
			var q = plain( dFilter.value ).trim();
			var keep = dBranch.value;
			var shown = points.filter( function ( b ) {
				return ! q || plain( b.name + ' ' + b.initials + ' ' + b.address ).indexOf( q ) !== -1;
			} );
			dBranch.textContent = '';
			shown.forEach( function ( b ) {
				var o = el( 'option', '', label( b ) + ( b.address ? ' — ' + b.address : '' ) );
				o.value = b.id;
				o.selected = b.id === keep;
				dBranch.appendChild( o );
			} );
			dCount.textContent = q ? fmt( t.pointsShown, shown.length, points.length ) : fmt( 1 === points.length ? t.pointFound : t.pointsFound, points.length );
			syncSave();
		};
		var search = function () {
			points = [];
			dBranch.textContent = '';
			dBranch.disabled = true;
			dFilter.disabled = true;
			dSave.disabled = true;
			dAddress.textContent = '';
			dStatus.textContent = '';
			dCount.textContent = t.loadingPoints;
			post( 'branches', { carrier: dRow.getAttribute( 'data-frenet-dropoff-row' ), cep: dCep.value } ).then( function ( res ) {
				if ( ! res.success ) {
					dCount.textContent = ( res.data && res.data.message ) || t.error;
					return;
				}
				points = res.data.branches || [];
				var now = current();
				// The saved point may be far from this CEP: keep it first so the list never hides it.
				if ( now && ! points.some( function ( b ) {
					return b.id === now.id;
				} ) ) {
					points.unshift( now );
				}
				if ( ! points.length ) {
					dCount.textContent = t.noPoints;
					return;
				}
				dBranch.disabled = false;
				dFilter.disabled = false;
				renderPoints();
				if ( now ) {
					dBranch.value = now.id;
					syncSave();
				}
				dFilter.focus();
			} );
		};
		var open = function ( row ) {
			dRow = row;
			dFilter.value = '';
			dEditor.querySelector( '[data-frenet-dropoff-title]' ).textContent = fmt( t.chooseFor, row.getAttribute( 'data-name' ) );
			dEditor.querySelector( '[data-frenet-dropoff-current]' ).textContent = t.currentPoint + ' ' + describe( current() );
			dropoff.querySelectorAll( '[data-frenet-dropoff-row]' ).forEach( function ( r ) {
				r.classList.toggle( 'is-applied', r === row );
			} );
			dEditor.hidden = false;
			dEditor.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			search();
		};
		var close = function () {
			dEditor.hidden = true;
			if ( dRow ) {
				dRow.classList.remove( 'is-applied' );
				dRow.querySelector( '[data-frenet-dropoff-edit]' ).focus();
			}
			dRow = null;
		};

		dropoff.addEventListener( 'click', function ( e ) {
			var edit = e.target.closest( '[data-frenet-dropoff-edit]' );
			if ( edit ) {
				open( edit.closest( '[data-frenet-dropoff-row]' ) );
			}
		} );
		dropoff.querySelector( '[data-frenet-dropoff-search]' ).addEventListener( 'click', search );
		dCep.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				search();
			}
		} );
		dFilter.addEventListener( 'input', renderPoints );
		dBranch.addEventListener( 'change', syncSave );
		dropoff.querySelector( '[data-frenet-dropoff-close]' ).addEventListener( 'click', close );
		dEditor.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				close();
			}
		} );
		dSave.addEventListener( 'click', function () {
			var code = dRow.getAttribute( 'data-frenet-dropoff-row' );
			dSave.disabled = true;
			dStatus.textContent = t.working;
			post( 'default_branch', { carrier: code, id: dBranch.value } ).then( function ( res ) {
				if ( res.success ) {
					defaults[ code ] = res.data[ 'default' ];
					paintRow( dRow, defaults[ code ] );
					dEditor.querySelector( '[data-frenet-dropoff-current]' ).textContent = t.currentPoint + ' ' + describe( defaults[ code ] );
					dStatus.textContent = res.data.message;
				} else {
					dStatus.textContent = '';
					dStatus.appendChild( el( 'span', 'frenet-error', ( res.data && res.data.message ) || t.error ) );
					if ( res.data && res.data.panel ) {
						var link = el( 'a', 'frenet-block', t.openPanel );
						link.href = res.data.panel;
						link.target = '_blank';
						link.rel = 'noopener';
						dStatus.appendChild( link );
					}
				}
				syncSave();
			} );
		} );
	}

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
