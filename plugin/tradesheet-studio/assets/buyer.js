/**
 * TradeSheet Studio — buyer portal progressive enhancement.
 * Scoped to .tssbp pages; the portal remains fully usable without JS.
 * Vanilla JS only: no theme or framework dependency.
 */
( function () {
	'use strict';

	var form = document.querySelector( 'form input[name="tss_action"][value="submit_quote"]' );
	if ( ! form ) {
		form = null;
	}

	var qtyInputs = document.querySelectorAll( 'input[type="number"][data-price]' );
	var totalEl = document.getElementById( 'tss-total' );
	var filterInput = document.getElementById( 'tss-filter' );

	function parseMoney( value ) {
		var parsed = parseFloat( value );
		return isNaN( parsed ) ? 0 : parsed;
	}

	function effectivePrice( base, breaks, qty ) {
		var best = null;
		if ( ! Array.isArray( breaks ) || qty < 2 ) {
			return parseMoney( base );
		}
		for ( var i = 0; i < breaks.length; i++ ) {
			var breakQty = parseInt( breaks[ i ].qty, 10 );
			if ( qty >= breakQty ) {
				best = parseMoney( breaks[ i ].price );
			}
		}
		return null === best ? parseMoney( base ) : best;
	}

	function formatMoney( value ) {
		return value.toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
	}

	function recompute() {
		var total = 0;

		qtyInputs.forEach( function ( input ) {
			var qty = parseInt( input.value, 10 );
			var row = input.closest ? input.closest( 'tr' ) : null;
			var estimateCell = row ? row.querySelector( '[data-line-estimate]' ) : null;

			if ( isNaN( qty ) || qty <= 0 ) {
				if ( estimateCell ) {
					estimateCell.textContent = '—';
				}
				return;
			}

			var breaks = [];
			try {
				breaks = JSON.parse( input.getAttribute( 'data-breaks' ) || '[]' );
			} catch ( e ) {
				breaks = [];
			}

			var unit = effectivePrice( input.getAttribute( 'data-price' ), breaks, qty );
			var lineTotal = unit * qty;

			total += lineTotal;

			if ( estimateCell ) {
				estimateCell.textContent = formatMoney( lineTotal );
			}

			// Soft validation hint.
			var min = parseInt( input.getAttribute( 'data-min' ), 10 ) || 1;
			var step = parseInt( input.getAttribute( 'data-step' ), 10 ) || 1;
			var invalid = qty > 0 && ( qty < min || qty % step !== 0 );
			input.setAttribute( 'aria-invalid', invalid ? 'true' : 'false' );
		} );

		if ( totalEl ) {
			totalEl.textContent = formatMoney( total );
		}
	}

	if ( qtyInputs.length > 0 ) {
		qtyInputs.forEach( function ( input ) {
			input.addEventListener( 'input', recompute );
		} );
		recompute();
	}

	if ( filterInput ) {
		filterInput.addEventListener( 'input', function () {
			var needle = filterInput.value.trim().toLowerCase();

			document.querySelectorAll( '.tss-row' ).forEach( function ( row ) {
				var haystack = row.getAttribute( 'data-search' ) || '';
				row.style.display = '' === needle || haystack.indexOf( needle ) !== -1 ? '' : 'none';
			} );
		} );
	}
} )();
