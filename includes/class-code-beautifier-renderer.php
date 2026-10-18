<?php
/**
 * Front-end markup of the block.
 *
 * The saved post content keeps a plain <pre><code> copy of the code. On output
 * that copy is replaced by highlighted markup, built here on the server: no
 * highlighting library in the browser, no flash of unstyled code, and the page
 * still shows readable code if the plugin is ever switched off.
 *
 * @package Code_Beautifier_Block
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the code block.
 */
final class Code_Beautifier_Renderer {

	/**
	 * Block render callback.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Saved block markup.
	 * @return string
	 */
	public static function render( $attributes, $content ) {
		// Filled only by the editor preview; on the front end the code comes from the saved markup.
		$code = ( isset( $attributes['code'] ) && is_string( $attributes['code'] ) && '' !== $attributes['code'] ) ? $attributes['code'] : self::code_from_markup( $content );
		$code = self::clean( $code );
		if ( '' === trim( $code ) ) {
			return '';
		}

		$lang = Code_Beautifier_Highlighter::normalize( isset( $attributes['language'] ) ? $attributes['language'] : 'auto' );
		if ( 'auto' === $lang ) {
			$lang = Code_Beautifier_Detector::guess( $code );
		}

		$lines   = self::lines( Code_Beautifier_Highlighter::tokenize( $code, $lang ) );
		$count   = count( $lines );
		$start   = isset( $attributes['startLine'] ) ? max( 0, (int) $attributes['startLine'] ) : 1;
		$numbers = isset( $attributes['lineNumbers'] ) ? (string) $attributes['lineNumbers'] : 'auto';
		$numbers = ( 'on' === $numbers ) || ( 'auto' === $numbers && $count >= 3 );
		$wrap    = ! isset( $attributes['wrap'] ) || (bool) $attributes['wrap'];
		$marked  = self::marked_lines( isset( $attributes['highlightLines'] ) ? (string) $attributes['highlightLines'] : '', $start );
		$title   = isset( $attributes['title'] ) ? trim( (string) $attributes['title'] ) : '';

		$body = '';
		foreach ( $lines as $i => $html ) {
			$class = isset( $marked[ $start + $i ] ) ? 'cbb-line cbb-line--marked' : 'cbb-line';
			// $html is already escaped token by token in lines().
			$body .= '<span class="' . esc_attr( $class ) . '">' . $html . ( $i < $count - 1 ? "\n" : '' ) . '</span>';
		}

		$classes = array( 'cbb' );
		if ( $wrap ) {
			$classes[] = 'cbb--wrap';
		}
		if ( $numbers ) {
			$classes[] = 'cbb--numbers';
		}
		$digits  = strlen( (string) ( $start + $count - 1 ) );
		$wrapper = get_block_wrapper_attributes(
			array(
				'class'     => implode( ' ', $classes ),
				'style'     => '--cbb-digits:' . absint( $digits ) . ';',
				'data-lang' => $lang,
			)
		);

		$label = Code_Beautifier_Highlighter::label( $lang );
		$head  = '<figcaption class="cbb-head">'
			. '<span class="cbb-lang">' . esc_html( $label ) . '</span>'
			. ( '' !== $title ? '<span class="cbb-title">' . esc_html( $title ) . '</span>' : '' )
			. '<span class="cbb-actions">'
			. '<button type="button" class="cbb-button cbb-button--wrap" data-cbb-action="wrap" aria-pressed="' . ( $wrap ? 'true' : 'false' ) . '" title="' . esc_attr__( 'Wrap long lines', 'code-beautifier-block' ) . '">'
			. '<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><path d="M2 4h12M2 8h9.5a2.5 2.5 0 0 1 0 5H8m0 0 1.5-1.5M8 13l1.5 1.5M2 12h3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '<span class="cbb-sr">' . esc_html__( 'Wrap long lines', 'code-beautifier-block' ) . '</span>'
			. '</button>'
			. '<button type="button" class="cbb-button cbb-button--copy" data-cbb-action="copy" data-cbb-done="' . esc_attr__( 'Copied', 'code-beautifier-block' ) . '" data-cbb-failed="' . esc_attr__( 'Press Ctrl+C to copy', 'code-beautifier-block' ) . '">'
			. '<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><rect x="5.5" y="5.5" width="8" height="8.5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 3.5V3A1.5 1.5 0 0 0 9 1.5H4A1.5 1.5 0 0 0 2.5 3v6.5A1.5 1.5 0 0 0 4 11h.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
			. '<span class="cbb-button-label" aria-live="polite">' . esc_html__( 'Copy', 'code-beautifier-block' ) . '</span>'
			. '</button>'
			. '</span>'
			. '</figcaption>';

		return '<figure ' . $wrapper . '>' . $head
			. '<pre class="cbb-pre" tabindex="0"><code class="cbb-code language-' . esc_attr( $lang ) . '" style="counter-reset:cbb-line ' . esc_attr( (string) ( $start - 1 ) ) . ';">'
			. $body
			. '</code></pre></figure>';
	}

	/**
	 * Reads the code out of the saved <pre><code> markup.
	 *
	 * @param string $markup Saved markup.
	 * @return string
	 */
	public static function code_from_markup( $markup ) {
		if ( ! preg_match( '~<code\b[^>]*>([\s\S]*)</code>~', (string) $markup, $m ) ) {
			return '';
		}
		return html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * The escaping used in the saved markup. Must match escapeCode() in editor.js
	 * character for character, otherwise the editor reports the block as changed.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public static function escape_saved( $code ) {
		return strtr(
			$code,
			array(
				'&' => '&amp;',
				'<' => '&lt;',
				'>' => '&gt;',
				'[' => '&#91;',
				']' => '&#93;',
			)
		);
	}

	/**
	 * Unifies line ends and drops empty lines around the code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private static function clean( $code ) {
		$code = str_replace( array( "\r\n", "\r" ), "\n", (string) $code );
		$code = preg_replace( '~^(?:[ \t]*\n)+~', '', $code );
		return rtrim( $code );
	}

	/**
	 * Escapes token text for output. Square brackets become entities, because
	 * WordPress runs shortcodes after the blocks: a [gallery] in a code sample
	 * would otherwise be executed instead of shown.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function esc( $text ) {
		return strtr(
			htmlspecialchars( $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ),
			array(
				'[' => '&#91;',
				']' => '&#93;',
			)
		);
	}

	/**
	 * Distributes tokens over lines; a token that spans lines is closed and
	 * reopened at each line end, so every line is a self-contained element.
	 *
	 * @param array $tokens Tokens.
	 * @return array HTML per line.
	 */
	private static function lines( $tokens ) {
		$lines   = array( '' );
		$current = 0;
		foreach ( $tokens as $token ) {
			list( $type, $text ) = $token;
			$parts               = explode( "\n", $text );
			foreach ( $parts as $n => $part ) {
				if ( $n > 0 ) {
					$lines[] = '';
					++$current;
				}
				if ( '' === $part ) {
					continue;
				}
				$lines[ $current ] .= ( null === $type ) ? self::esc( $part ) : '<span class="' . esc_attr( 'cbb-' . $type ) . '">' . self::esc( $part ) . '</span>';
			}
		}
		return $lines;
	}

	/**
	 * Parses "3, 5-7" into a set of line numbers.
	 *
	 * @param string $spec  Line list.
	 * @param int    $start First line number.
	 * @return array Line number => true.
	 */
	private static function marked_lines( $spec, $start ) {
		$marked = array();
		foreach ( preg_split( '~\s*,\s*~', trim( $spec ), -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
			if ( preg_match( '~^(\d+)\s*-\s*(\d+)$~', $part, $m ) ) {
				$from = (int) $m[1];
				$to   = min( (int) $m[2], $from + 5000 );
				for ( $i = $from; $i <= $to; $i++ ) {
					$marked[ $i ] = true;
				}
			} elseif ( ctype_digit( $part ) ) {
				$marked[ (int) $part ] = true;
			}
		}
		return $marked;
	}
}
