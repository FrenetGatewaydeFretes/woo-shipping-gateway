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

	// Carrier logos: the bundled official files (see WC_Frenet_Labels_Carriers); a monogram in the brand colour otherwise.
	var brand = cfg.carriers || { logos: {}, colors: {}, names: {}, palette: [ '#0c005a' ] };

	function carrierCode( code, name ) {
		code = String( code || '' ).toUpperCase().trim();
		if ( code && brand.logos[ code ] ) {
			return code;
		}
		var lower = ( String( name || '' ) + ' ' + code ).toLowerCase();
		var k;
		for ( k in brand.names ) {
			if ( Object.prototype.hasOwnProperty.call( brand.names, k ) && lower.indexOf( k ) !== -1 ) {
				return brand.names[ k ];
			}
		}
		return code;
	}

	function carrierColor( code ) {
		if ( brand.colors[ code ] ) {
			return brand.colors[ code ];
		}
		var sum = 0;
		var i;
		for ( i = 0; i < code.length; i++ ) {
			sum += code.charCodeAt( i );
		}
		return brand.palette[ sum % brand.palette.length ];
	}

	function carrierInitials( name, code ) {
		var words = String( name || '' ).trim().split( /[\s\-_]+/ ).filter( function ( w ) {
			return /[\p{L}\p{N}]/u.test( w );
		} );
		if ( words.length >= 2 ) {
			return ( words[ 0 ].charAt( 0 ) + words[ 1 ].charAt( 0 ) ).toUpperCase();
		}
		return String( name || code ).replace( /[^\p{L}\p{N}]/gu, '' ).slice( 0, 2 ).toUpperCase();
	}

	function carrierNode( code, name ) {
		var known = carrierCode( code, name );
		var label = String( name || '' ).trim() || known;
		var wrap = el( 'span', 'frenet-carrier' );
		if ( brand.logos[ known ] ) {
			var img = el( 'img', 'frenet-carrier__logo' );
			img.src = brand.logos[ known ];
			img.alt = label;
			img.height = 20;
			img.loading = 'lazy';
			img.decoding = 'async';
			wrap.appendChild( img );
			return wrap;
		}
		var mark = el( 'span', 'frenet-carrier__mark', carrierInitials( label, known ) );
		mark.style.background = carrierColor( known );
		mark.setAttribute( 'aria-hidden', 'true' );
		wrap.appendChild( mark );
		wrap.appendChild( el( 'span', 'frenet-carrier__name', label ) );
		return wrap;
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

	/* ---------------------------------------------------------------- Configurações: automação */

	var autoOn = document.querySelector( '[data-frenet-auto-on]' );
	var autoConsent = document.querySelector( '[data-frenet-auto-consent]' );
	var autoDialog = document.querySelector( '[data-frenet-auto-dialog]' );
	if ( autoOn && autoConsent && autoDialog && autoDialog.showModal ) {
		var autoRule = function () {
			var picked = document.querySelector( 'input[name="frenet_labels[auto_create_rule]"]:checked' );
			return picked && picked.parentNode ? picked.parentNode.textContent.trim() : '';
		};
		// Turning the automation on is a money decision: the warning comes first, the authorisation box follows it.
		autoOn.addEventListener( 'change', function () {
			if ( ! autoOn.checked || autoConsent.checked ) {
				return;
			}
			autoDialog.querySelector( '[data-frenet-auto-rule]' ).textContent = autoRule();
			autoDialog.returnValue = '';
			autoDialog.showModal();
		} );
		autoDialog.addEventListener( 'close', function () {
			if ( 'confirm' === autoDialog.returnValue ) {
				autoConsent.checked = true;
			} else {
				autoOn.checked = false;
			}
		} );
		// Taking the authorisation back switches the automation off (the server does the same when saving).
		autoConsent.addEventListener( 'change', function () {
			if ( ! autoConsent.checked && autoOn.checked ) {
				autoOn.checked = false;
			}
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

		// Labels are always bought from the store (there is no "send to the Frenet panel" choice any more).
		var journey = function () {
			return 'here';
		};
		var picked = function () {
			return rows.filter( function ( r ) {
				return r.querySelector( '[data-frenet-pick]' ).checked && ! r.hasAttribute( 'data-done' );
			} );
		};
		// Sticky bar: how many orders are selected and, once quoted, what the chosen services add up to.
		var barCount = create.querySelector( '[data-frenet-bar-count]' );
		var barTotalWrap = create.querySelector( '[data-frenet-bar-total-wrap]' );
		var barTotal = create.querySelector( '[data-frenet-bar-total]' );
		var barAfterWrap = create.querySelector( '[data-frenet-bar-after-wrap]' );
		var barAfter = create.querySelector( '[data-frenet-bar-after]' );
		var barAfterLabel = create.querySelector( '[data-frenet-bar-after-label]' );
		var barAfterText = barAfterLabel ? barAfterLabel.textContent : '';
		var updateBar = function () {
			if ( ! barCount ) {
				return;
			}
			var list = picked();
			barCount.textContent = String( list.length );
			var sel = typeof selection === 'function' ? selection() : [];
			var priced = sel.filter( function ( x ) {
				return x.service;
			} );
			if ( ! priced.length ) {
				barTotalWrap.hidden = true;
				barAfterWrap.hidden = true;
				return;
			}
			var total = priced.reduce( function ( a, x ) {
				return a + ( parseFloat( x.service.price ) || 0 );
			}, 0 );
			barTotalWrap.hidden = false;
			barTotal.textContent = money( total );
			var now = balanceNow();
			var pays = 'here' === journey() && cfg.wallet;
			// No balance for this total: say it instead of a negative number, and hold "Criar envio" until it fits.
			var short = pays && now !== null && now - total < 0;
			barAfterWrap.hidden = ! ( pays && now !== null );
			if ( pays && now !== null ) {
				barAfter.textContent = short ? fmt( t.missing, money( total - now ) ) : money( now - total );
				barAfter.classList.toggle( 'frenet-error', short );
				if ( barAfterLabel ) {
					barAfterLabel.textContent = short ? t.noBalance : barAfterText;
					barAfterLabel.classList.toggle( 'frenet-error', short );
				}
			}
			if ( short ) {
				btnReview.disabled = true;
			}
			btnReview.title = short ? t.notEnough : '';
		};
		create.addEventListener( 'change', updateBar );

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

		// Two lines per service: "Carrier · Service" and "price · days · tags".
		var optionText = function ( data, s, row ) {
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
			return {
				name: s.carrier + ' · ' + s.name,
				sub: money( s.price ) + ' · ' + fmt( 1 === Number( s.days ) ? t.day : t.days, s.days ) + ( tags.length ? ' · ' + tags.join( ' · ' ) : '' )
			};
		};

		// Service picker: a listbox with the carrier logo in every option, backed by the native select the rest of
		// the script reads ([data-frenet-choice]). The list is fixed-positioned so the table never clips it.
		var closeBoxes = function ( except ) {
			document.querySelectorAll( '[data-frenet-choice-box].is-open' ).forEach( function ( b ) {
				if ( b === except ) {
					return;
				}
				b.classList.remove( 'is-open' );
				b.querySelector( '.frenet-choice-box__btn' ).setAttribute( 'aria-expanded', 'false' );
				b.querySelector( '[role="listbox"]' ).hidden = true;
			} );
		};
		var setActive = function ( list, li ) {
			list.querySelectorAll( '.is-active' ).forEach( function ( x ) {
				x.classList.remove( 'is-active' );
			} );
			if ( li ) {
				li.classList.add( 'is-active' );
				list.setAttribute( 'aria-activedescendant', li.id );
				// Scroll the list only, never the page (a page scroll would close the list).
				if ( li.offsetTop < list.scrollTop ) {
					list.scrollTop = li.offsetTop;
				} else if ( li.offsetTop + li.offsetHeight > list.scrollTop + list.clientHeight ) {
					list.scrollTop = li.offsetTop + li.offsetHeight - list.clientHeight;
				}
			}
		};
		var openBox = function ( box ) {
			var btn = box.querySelector( '.frenet-choice-box__btn' );
			var list = box.querySelector( '[role="listbox"]' );
			var r = btn.getBoundingClientRect();
			var width = Math.max( r.width, 340 );
			var below = window.innerHeight - r.bottom;
			closeBoxes( box );
			list.hidden = false;
			list.style.width = width + 'px';
			list.style.left = Math.max( 8, Math.min( r.left, window.innerWidth - width - 8 ) ) + 'px';
			if ( below >= 240 || below >= r.top ) {
				list.style.top = ( r.bottom + 4 ) + 'px';
				list.style.bottom = 'auto';
				list.style.maxHeight = Math.max( 160, Math.min( 380, below - 16 ) ) + 'px';
			} else {
				list.style.top = 'auto';
				list.style.bottom = ( window.innerHeight - r.top + 4 ) + 'px';
				list.style.maxHeight = Math.max( 160, Math.min( 380, r.top - 16 ) ) + 'px';
			}
			box.classList.add( 'is-open' );
			btn.setAttribute( 'aria-expanded', 'true' );
			setActive( list, list.querySelector( '[aria-selected="true"]' ) || list.firstElementChild );
			list.focus( { preventScroll: true } );
		};
		var choose = function ( box, code ) {
			var row = box.closest( '[data-frenet-row]' );
			var select = row.querySelector( '[data-frenet-choice]' );
			closeBoxes();
			if ( select.value !== code ) {
				select.value = code;
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			paintChoice( row );
			box.querySelector( '.frenet-choice-box__btn' ).focus();
		};
		var choiceBox = function ( row, data ) {
			var box = el( 'div', 'frenet-choice-box' );
			var btn = el( 'button', 'frenet-choice-box__btn' );
			var list = el( 'ul', 'frenet-choice-box__list' );
			var id = 'frenet-choice-' + row.getAttribute( 'data-order' );
			box.setAttribute( 'data-frenet-choice-box', '' );
			btn.type = 'button';
			btn.setAttribute( 'aria-haspopup', 'listbox' );
			btn.setAttribute( 'aria-expanded', 'false' );
			btn.appendChild( el( 'span', 'frenet-choice-box__logo' ) );
			btn.appendChild( el( 'span', 'frenet-choice-box__text' ) );
			btn.appendChild( el( 'span', 'frenet-choice-box__caret' ) ).setAttribute( 'aria-hidden', 'true' );
			list.setAttribute( 'role', 'listbox' );
			list.id = id;
			list.tabIndex = -1;
			list.hidden = true;
			data.services.forEach( function ( s, i ) {
				var li = el( 'li', 'frenet-choice-box__item' );
				var o = optionText( data, s, row );
				var tx = el( 'span', 'frenet-choice-box__text' );
				li.id = id + '-' + i;
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'data-code', s.code );
				li.setAttribute( 'aria-selected', 'false' );
				li.appendChild( el( 'span', 'frenet-choice-box__logo' ) ).appendChild( carrierNode( s.carrier_code, s.carrier ) );
				tx.appendChild( el( 'strong', '', o.name ) );
				tx.appendChild( el( 'span', 'frenet-cell-sub', o.sub ) );
				li.appendChild( tx );
				li.addEventListener( 'click', function () {
					choose( box, s.code );
				} );
				li.addEventListener( 'mousemove', function () {
					if ( ! li.classList.contains( 'is-active' ) ) {
						setActive( list, li );
					}
				} );
				list.appendChild( li );
			} );
			btn.addEventListener( 'click', function () {
				if ( box.classList.contains( 'is-open' ) ) {
					closeBoxes();
				} else {
					openBox( box );
				}
			} );
			btn.addEventListener( 'keydown', function ( e ) {
				if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
					e.preventDefault();
					openBox( box );
				}
			} );
			list.addEventListener( 'keydown', function ( e ) {
				var items = Array.prototype.slice.call( list.children );
				var i = items.indexOf( list.querySelector( '.is-active' ) );
				if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key || 'Home' === e.key || 'End' === e.key ) {
					e.preventDefault();
					if ( 'ArrowDown' === e.key ) {
						i = Math.min( items.length - 1, i + 1 );
					} else if ( 'ArrowUp' === e.key ) {
						i = Math.max( 0, i - 1 );
					} else {
						i = 'Home' === e.key ? 0 : items.length - 1;
					}
					setActive( list, items[ i ] );
				} else if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					if ( items[ i ] ) {
						choose( box, items[ i ].getAttribute( 'data-code' ) );
					}
				} else if ( 'Escape' === e.key ) {
					e.preventDefault();
					closeBoxes();
					btn.focus();
				} else if ( 'Tab' === e.key ) {
					closeBoxes();
				}
			} );
			box.appendChild( btn );
			box.appendChild( list );
			return box;
		};
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest || ! e.target.closest( '[data-frenet-choice-box]' ) ) {
				closeBoxes();
			}
		} );
		window.addEventListener( 'resize', function () {
			closeBoxes();
		} );
		document.addEventListener( 'scroll', function ( e ) {
			if ( ! e.target.closest || ! e.target.closest( '[role="listbox"]' ) ) {
				closeBoxes();
			}
		}, true );

		// The picker shows the chosen service; repainted whenever the choice changes, by hand or by the summary.
		var paintChoice = function ( row ) {
			var q = quotes[ row.getAttribute( 'data-order' ) ];
			var select = row.querySelector( '[data-frenet-choice]' );
			var box = row.querySelector( '[data-frenet-choice-box]' );
			if ( ! q || ! q.ok || ! select || ! box ) {
				return;
			}
			var s = q.services.filter( function ( x ) {
				return x.code === select.value;
			} )[ 0 ];
			var logo = box.querySelector( '.frenet-choice-box__btn .frenet-choice-box__logo' );
			var text = box.querySelector( '.frenet-choice-box__btn .frenet-choice-box__text' );
			logo.textContent = '';
			text.textContent = '';
			if ( s ) {
				var o = optionText( q, s, row );
				logo.appendChild( carrierNode( s.carrier_code, s.carrier ) );
				text.appendChild( el( 'strong', '', o.name ) );
				text.appendChild( el( 'span', 'frenet-cell-sub', o.sub ) );
			}
			box.querySelectorAll( '[role="option"]' ).forEach( function ( li ) {
				li.setAttribute( 'aria-selected', li.getAttribute( 'data-code' ) === select.value ? 'true' : 'false' );
			} );
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
			select.hidden = true;
			select.setAttribute( 'aria-hidden', 'true' );
			select.tabIndex = -1;
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
			cell.appendChild( choiceBox( row, data ) );
			paintChoice( row );
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
			updateBar();
			rows.forEach( paintChoice );
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
					var c = carriers[ name ] = carriers[ name ] || { name: name, code: by[ name ][ 0 ].carrier_code, available: 0, price: 0, time: 0, priceDays: 0, timeDays: 0 };
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
				var tdCarrier = el( 'td' );
				tdCarrier.appendChild( carrierNode( c.code, c.name ) );
				tr.appendChild( tdCarrier );
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
			updateBar();
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
					updateBar();
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
			updateBar();
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
				by[ name ] = by[ name ] || { n: 0, sum: 0, code: x.service.carrier_code };
				by[ name ].n++;
				by[ name ].sum += x.service.price;
				total += x.service.price;
			} );
			var body = dialog.querySelector( '[data-frenet-review-rows]' );
			body.textContent = '';
			Object.keys( by ).forEach( function ( name ) {
				var tr = el( 'tr' );
				var tdName = el( 'td' );
				tdName.appendChild( carrierNode( by[ name ].code, name ) );
				tr.appendChild( tdName );
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
		// The rows come checked from the server: the bar must say so before the first click.
		updateBar();
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
		var lCount = labels.querySelector( '[data-frenet-bar-count]' );
		var lTotalWrap = labels.querySelector( '[data-frenet-bar-total-wrap]' );
		var lTotal = labels.querySelector( '[data-frenet-bar-total]' );
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
			if ( lCount ) {
				lCount.textContent = String( unpaid.length + paid.length );
				lTotalWrap.hidden = ! unpaid.length;
				lTotal.textContent = money( sum );
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

	var printForm = document.querySelector( '[data-frenet-print-form]' );
	if ( printForm ) {
		var all = printForm.querySelector( '[data-frenet-all]' );
		var printCount = printForm.querySelector( '[data-frenet-bar-count]' );
		var printSubmit = printForm.querySelector( '[data-frenet-print-submit]' );
		var countPrint = function () {
			var n = printForm.querySelectorAll( '[data-frenet-pick]:checked' ).length;
			if ( printCount ) {
				printCount.textContent = String( n );
			}
			if ( printSubmit ) {
				printSubmit.disabled = ! n;
			}
		};
		if ( all ) {
			all.addEventListener( 'change', function () {
				printForm.querySelectorAll( '[data-frenet-pick]' ).forEach( function ( c ) {
					c.checked = all.checked;
				} );
				countPrint();
			} );
		}
		printForm.addEventListener( 'change', countPrint );
		countPrint();
	}
}() );
