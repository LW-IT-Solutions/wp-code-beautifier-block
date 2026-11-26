/**
 * Code Beautifier Block - copy and wrap buttons.
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
			timers.set( button, window.setTimeout( function () {
				if ( attempts.get( button ) !== attempt ) {
					return;
				}
				line.textContent = '';
				line.classList.remove( 'is-ok', 'is-failed' );
				button.classList.remove( 'is-done' );
			}, ok ? 2000 : 6000 ) );
		} );
	}

	function onWrap( button, block ) {
		var on = ! block.classList.contains( 'cbb--wrap' );
		block.classList.toggle( 'cbb--wrap', on );
		button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target && event.target.closest ? event.target.closest( '.cbb-button' ) : null;
		var block = button ? button.closest( '.cbb' ) : null;
		if ( ! block ) {
			return;
		}
		if ( 'copy' === button.getAttribute( 'data-cbb-action' ) ) {
			onCopy( button, block );
		} else if ( 'wrap' === button.getAttribute( 'data-cbb-action' ) ) {
			onWrap( button, block );
		}
	} );
}() );
