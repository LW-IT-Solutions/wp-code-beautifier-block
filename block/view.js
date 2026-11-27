/**
 * Code Beautifier Block - copy and wrap buttons, a short flash over the code
 * after copying, selecting one line with a click on its number, and the
 * "Show all lines" button of blocks with "Collapse long code".
 *
 * One delegated listener on the document, so blocks that arrive later (themes
 * that swap page content without a reload) work without re-initialisation.
 *
 * The visible label and the accessible name of the copy button never change.
 * The result of copying is written to the separate status line of the block
 * (role="status"), so screen readers announce it once and keyboard focus stays
 * on the button.
 */
( function () {
	'use strict';

	// Per button: number of the latest copy attempt and its reset timer.
	var attempts = new WeakMap();
	var timers = new WeakMap();

	document.documentElement.classList.add( 'cbb-js' );

	function focusWithoutScroll( element ) {
		if ( ! element || ! element.focus || ! element.isConnected ) {
			return;
		}
		try {
			element.focus( { preventScroll: true } );
		} catch ( e ) {
			element.focus();
		}
	}

	function fallbackCopy( text ) {
		var previous = document.activeElement;
		var area = document.createElement( 'textarea' );
		var ok = false;
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.setAttribute( 'aria-hidden', 'true' );
		area.style.position = 'fixed';
		area.style.top = '0';
		area.style.left = '-9999px';
		area.style.opacity = '0';
		document.body.appendChild( area );
		try {
			area.select();
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( area );
		focusWithoutScroll( previous );
		return ok;
	}

	function copy( text ) {
		try {
			if ( navigator.clipboard && window.isSecureContext ) {
				return navigator.clipboard.writeText( text ).then(
					function () {
						return true;
					},
					function () {
						return fallbackCopy( text );
					}
				);
			}
		} catch ( e ) {
			return Promise.resolve( fallbackCopy( text ) );
		}
		return Promise.resolve( fallbackCopy( text ) );
	}

	function selectCode( code ) {
		var selection;
		var range;
		if ( ! window.getSelection ) {
			return false;
		}
		try {
			selection = window.getSelection();
			range = document.createRange();
			range.selectNodeContents( code );
			selection.removeAllRanges();
			selection.addRange( range );
			return true;
		} catch ( e ) {
			return false;
		}
	}

	// Status line of the block. Markup from version 1.0.1 and earlier (for example
	// from a page cache) has none; then one is added.
	function statusLine( block ) {
		var line = block.querySelector( '.cbb-status' );
		if ( ! line ) {
			line = document.createElement( 'span' );
			line.className = 'cbb-status';
			line.setAttribute( 'role', 'status' );
			block.appendChild( line );
		}
		return line;
	}

	// Status text from the button. Older markup only has data-cbb-done and data-cbb-failed.
	function message( button, name, legacy ) {
		return button.getAttribute( 'data-cbb-status-' + name ) || button.getAttribute( legacy ) || '';
	}

	function onCopy( button, block ) {
		var code = block.querySelector( '.cbb-code' );
		var line;
		var attempt;
		if ( ! code ) {
			return;
		}
		line = statusLine( block );
		attempt = ( attempts.get( button ) || 0 ) + 1;
		attempts.set( button, attempt );
		window.clearTimeout( timers.get( button ) );
		line.textContent = '';

		copy( code.textContent || '' ).then( function ( ok ) {
			var selected = false;
			if ( attempts.get( button ) !== attempt || ! button.isConnected ) {
				return;
			}
			if ( ! ok ) {
				selected = selectCode( code );
				focusWithoutScroll( button );
			}
			line.classList.toggle( 'is-ok', ok );
			line.classList.toggle( 'is-failed', ! ok );
			if ( ok ) {
				line.textContent = message( button, 'done', 'data-cbb-done' );
			} else if ( selected ) {
				line.textContent = message( button, 'failed', 'data-cbb-failed' );
			} else {
				line.textContent = message( button, 'noselect', 'data-cbb-failed' );
			}
			button.classList.toggle( 'is-done', ok );
			if ( ok ) {
				// A short flash over the code shows what was copied (CSS, only without reduced motion).
				block.classList.remove( 'is-copied' );
				void block.offsetWidth;
				block.classList.add( 'is-copied' );
			}
			timers.set( button, window.setTimeout( function () {
				if ( attempts.get( button ) !== attempt ) {
					return;
				}
				line.textContent = '';
				line.classList.remove( 'is-ok', 'is-failed' );
				button.classList.remove( 'is-done' );
				block.classList.remove( 'is-copied' );
			}, ok ? 2000 : 6000 ) );
		} );
	}

	function onWrap( button, block ) {
		var on = ! block.classList.contains( 'cbb--wrap' );
		block.classList.toggle( 'cbb--wrap', on );
		button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
	}

	// Collapsed blocks: the first lines with a fade, and a button below the code.
	function setCollapsed( block, collapsed ) {
		var more = block.querySelector( '.cbb-more' );
		block.classList.toggle( 'cbb--collapsed', collapsed );
		if ( more ) {
			more.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			more.querySelector( '.cbb-more-label' ).textContent = block.getAttribute( collapsed ? 'data-cbb-more' : 'data-cbb-less' ) || '';
		}
	}

	var made = 0;
	function prepare( root ) {
		var blocks = ( root || document ).querySelectorAll( '.cbb.cbb--collapsible:not([data-cbb-ready])' );
		Array.prototype.forEach.call( blocks, function ( block ) {
			var pre = block.querySelector( '.cbb-pre' );
			if ( ! pre ) {
				return;
			}
			block.setAttribute( 'data-cbb-ready', '1' );
			if ( ! pre.id ) {
				made += 1;
				pre.id = 'cbb-pre-' + made;
			}
			var more = document.createElement( 'button' );
			more.type = 'button';
			more.className = 'cbb-more';
			more.setAttribute( 'data-cbb-action', 'more' );
			more.setAttribute( 'aria-controls', pre.id );
			more.innerHTML = '<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="cbb-more-label"></span>';
			pre.parentNode.insertBefore( more, pre.nextSibling );
			setCollapsed( block, true );
		} );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			prepare();
		} );
	} else {
		prepare();
	}
	// Blocks that arrive later (themes that swap page content without a reload).
	if ( window.MutationObserver ) {
		new MutationObserver( function () {
			if ( document.querySelector( '.cbb.cbb--collapsible:not([data-cbb-ready])' ) ) {
				prepare();
			}
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}

	function onMore( button, block ) {
		var collapse = ! block.classList.contains( 'cbb--collapsed' );
		setCollapsed( block, collapse );
		if ( collapse ) {
			var top = block.getBoundingClientRect().top;
			if ( top < 0 ) {
				block.scrollIntoView( { block: 'start' } );
			}
			focusWithoutScroll( button );
		}
	}

	// A click on a line number selects that line, ready to be copied.
	function onNumber( event ) {
		var lineEl = event.target && event.target.closest ? event.target.closest( '.cbb--numbers .cbb-line' ) : null;
		var block = lineEl ? lineEl.closest( '.cbb' ) : null;
		var pre = block ? block.querySelector( '.cbb-pre' ) : null;
		if ( ! pre || ( window.getSelection && String( window.getSelection() ) ) ) {
			return;
		}
		var gutter = parseFloat( window.getComputedStyle( lineEl, '::before' ).width ) || 0;
		var x = event.clientX - pre.getBoundingClientRect().left;
		if ( x < 0 || x > gutter ) {
			return;
		}
		var range = document.createRange();
		range.selectNodeContents( lineEl );
		// Without the line break at the end of the line.
		var last = lineEl.lastChild;
		if ( last && 3 === last.nodeType && /\n$/.test( last.nodeValue ) ) {
			range.setEnd( last, last.nodeValue.length - 1 );
		}
		var selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange( range );
		var copyButton = block.querySelector( '.cbb-button--copy' );
		var text = copyButton ? copyButton.getAttribute( 'data-cbb-status-line' ) : '';
		if ( text ) {
			var numbers = block.querySelectorAll( '.cbb-line' );
			var index = Array.prototype.indexOf.call( numbers, lineEl );
			var reset = /counter-reset:\s*cbb-line\s+(-?\d+)/.exec( ( block.querySelector( '.cbb-code' ) || {} ).getAttribute ? block.querySelector( '.cbb-code' ).getAttribute( 'style' ) || '' : '' );
			var status = statusLine( block );
			status.classList.remove( 'is-ok', 'is-failed' );
			status.textContent = text.replace( '%d', String( ( reset ? parseInt( reset[ 1 ], 10 ) : 0 ) + index + 1 ) );
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target && event.target.closest ? event.target.closest( '.cbb-button, .cbb-more' ) : null;
		var block = button ? button.closest( '.cbb' ) : null;
		if ( ! block ) {
			onNumber( event );
			return;
		}
		if ( 'more' === button.getAttribute( 'data-cbb-action' ) ) {
			onMore( button, block );
			return;
		}
		if ( 'copy' === button.getAttribute( 'data-cbb-action' ) ) {
			onCopy( button, block );
		} else if ( 'wrap' === button.getAttribute( 'data-cbb-action' ) ) {
			onWrap( button, block );
		}
	} );
}() );
