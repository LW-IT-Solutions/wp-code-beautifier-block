/**
 * Code Beautifier Block - copy and wrap buttons.
 *
 * One delegated listener on the document, so blocks that arrive later (themes
 * that swap page content without a reload) work without re-initialisation.
 */
( function () {
	'use strict';

	// Timers of the copy buttons, kept here instead of as a property on the element.
	var timers = new WeakMap();

	document.documentElement.classList.add( 'cbb-js' );

	function codeText( block ) {
		var code = block.querySelector( '.cbb-code' );
		return code ? code.textContent : '';
	}

	function fallbackCopy( text ) {
		var area = document.createElement( 'textarea' );
		var ok = false;
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.top = '0';
		area.style.left = '-9999px';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( area );
		return ok;
	}

	function copy( text ) {
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
		return Promise.resolve( fallbackCopy( text ) );
	}

	function selectCode( block ) {
		var code = block.querySelector( '.cbb-code' );
		var range;
		if ( ! code || ! window.getSelection ) {
			return;
		}
		range = document.createRange();
		range.selectNodeContents( code );
		window.getSelection().removeAllRanges();
		window.getSelection().addRange( range );
	}

	function onCopy( button, block ) {
		var label = button.querySelector( '.cbb-button-label' );
		if ( ! label ) {
			return;
		}
		if ( ! button.hasAttribute( 'data-cbb-label' ) ) {
			button.setAttribute( 'data-cbb-label', label.textContent );
		}
		copy( codeText( block ) ).then( function ( ok ) {
			if ( ! ok ) {
				selectCode( block );
			}
			label.textContent = button.getAttribute( ok ? 'data-cbb-done' : 'data-cbb-failed' );
			button.classList.toggle( 'is-done', ok );
			window.clearTimeout( timers.get( button ) );
			timers.set( button, window.setTimeout( function () {
				label.textContent = button.getAttribute( 'data-cbb-label' );
				button.classList.remove( 'is-done' );
			}, 2000 ) );
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
