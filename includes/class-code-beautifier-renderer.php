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
	 * Number of lines from which "Collapse long code" shortens a block.
	 */
	const COLLAPSE_FROM = 25;

	/**
	 * Block render callback.
	 *
	 * @param array         $attributes Block attributes.
	 * @param string        $content    Saved block markup.
	 * @param WP_Block|null $block      Block instance, gives the ID of the post.
	 * @return string
	 */
	public static function render( $attributes, $content, $block = null ) {
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
		// 1.2.0: long code can be shown shortened (opt-in per block). Only a class and
		// data attributes are added; the code itself is always complete in the page.
		$collapse = ! empty( $attributes['collapse'] ) && $count >= self::COLLAPSE_FROM;
		if ( $collapse ) {
			$classes[] = 'cbb--collapsible';
		}
		$digits  = strlen( (string) ( $start + $count - 1 ) );
		$texts   = self::interface_texts( $block );
		$extra   = array(
			'class'     => implode( ' ', $classes ),
			'style'     => '--cbb-digits:' . absint( $digits ) . ';',
			'data-lang' => $lang,
		);
		if ( $collapse ) {
			$extra['data-cbb-lines'] = (string) $count;
			$extra['data-cbb-more']  = sprintf( $texts['more'], $count );
			$extra['data-cbb-less']  = $texts['less'];
		}
		$wrapper = get_block_wrapper_attributes( $extra );

		$label = Code_Beautifier_Highlighter::label( $lang );
		$head  = '<figcaption class="cbb-head">'
			. '<span class="cbb-lang">' . esc_html( $label ) . '</span>'
			. ( '' !== $title ? '<span class="cbb-title"><svg class="cbb-title-icon" aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 16 16"><path d="M4 1.75h5.25L12.5 5v9.25H4z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 2v3.25h3.25" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>' . esc_html( $title ) . '</span>' : '' )
			. '<span class="cbb-actions">'
			. '<button type="button" class="cbb-button cbb-button--wrap" data-cbb-action="wrap" aria-pressed="' . ( $wrap ? 'true' : 'false' ) . '" title="' . esc_attr( $texts['wrap'] ) . '">'
			. '<svg class="cbb-icon-wrap" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><path d="M2 4h12M2 8h9.5a2.5 2.5 0 0 1 0 5H8m0 0 1.5-1.5M8 13l1.5 1.5M2 12h3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '<span class="cbb-sr">' . esc_html( $texts['wrap'] ) . '</span>'
			. '</button>'
			. '<button type="button" class="cbb-button cbb-button--copy" data-cbb-action="copy" aria-label="' . esc_attr( $texts['copy_name'] ) . '" data-cbb-status-done="' . esc_attr( $texts['status_done'] ) . '" data-cbb-status-failed="' . esc_attr( $texts['status_failed'] ) . '" data-cbb-status-noselect="' . esc_attr( $texts['status_noselect'] ) . '" data-cbb-status-line="' . esc_attr( $texts['status_line'] ) . '">'
			. '<svg class="cbb-icon-copy" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><rect x="5.5" y="5.5" width="8" height="8.5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 3.5V3A1.5 1.5 0 0 0 9 1.5H4A1.5 1.5 0 0 0 2.5 3v6.5A1.5 1.5 0 0 0 4 11h.5" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
			. '<svg class="cbb-icon-done" aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 16 16"><path d="M3 8.5 6.5 12 13 4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '<span class="cbb-button-label">' . esc_html( $texts['copy'] ) . '</span>'
			. '</button>'
			. '</span>'
			. '</figcaption>';

		// The visible labels never change; the result of copying is announced in the separate status line.
		return '<figure ' . $wrapper . '>' . $head
			. '<pre class="cbb-pre" tabindex="0" role="region" aria-label="' . esc_attr( sprintf( $texts['region'], '' !== $title ? $title : $label ) ) . '"><code class="cbb-code language-' . esc_attr( $lang ) . '" style="counter-reset:cbb-line ' . esc_attr( (string) ( $start - 1 ) ) . ';">'
			. $body
			. '</code></pre><span class="cbb-status" role="status"></span></figure>';
	}

	/**
	 * Texts of the buttons, the code region and the status line.
	 *
	 * They are in the language of the post the block belongs to. On a normal
	 * page view that is the language WordPress already uses. A REST response can
	 * run in another language (that of the user); then the plugin's translation
	 * file for the language of the post is read directly, without switching the
	 * language of the rest of the request. The post language comes from Polylang
	 * when it is active, and can be set with the filter
	 * "code_beautifier_block_locale".
	 *
	 * @param WP_Block|null $block Block instance.
	 * @return array<string, string>
	 */
	private static function interface_texts( $block ) {
		$texts = array(
			'copy'            => __( 'Copy', 'code-beautifier-block' ),
			'copy_name'       => __( 'Copy code', 'code-beautifier-block' ),
			'wrap'            => __( 'Wrap long lines', 'code-beautifier-block' ),
			/* translators: %s: title of the code block, or the name of its language. */
			'region'          => __( 'Code example: %s', 'code-beautifier-block' ),
			'status_done'     => __( 'Code copied to the clipboard.', 'code-beautifier-block' ),
			'status_failed'   => __( 'Copying failed. The code is selected for copying with Ctrl+C or ⌘+C.', 'code-beautifier-block' ),
			'status_noselect' => __( 'Copying failed.', 'code-beautifier-block' ),
			/* translators: %d: line number */
			'status_line'     => __( 'Line %d selected. Copy it with Ctrl+C or ⌘+C.', 'code-beautifier-block' ),
			/* translators: %d: number of lines of the code sample */
			'more'            => __( 'Show all lines (%d)', 'code-beautifier-block' ),
			'less'            => __( 'Show fewer lines', 'code-beautifier-block' ),
		);

		$post_id = ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) ? absint( $block->context['postId'] ) : absint( get_the_ID() );
		$locale  = ( $post_id && function_exists( 'pll_get_post_language' ) ) ? (string) pll_get_post_language( $post_id, 'locale' ) : '';

		/**
		 * Filters the language of the button texts of one block.
		 *
		 * @param string $locale  Locale such as "de_DE", or "" for the current language.
		 * @param int    $post_id ID of the post the block belongs to, 0 if unknown.
		 */
		$locale = (string) apply_filters( 'code_beautifier_block_locale', $locale, $post_id );
		if ( ! preg_match( '/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})*$/', $locale ) || determine_locale() === $locale ) {
			return $texts;
		}

		// The same texts in English, as they are written in the translation files.
		$source = array(
			'copy'            => 'Copy',
			'copy_name'       => 'Copy code',
			'wrap'            => 'Wrap long lines',
			'region'          => 'Code example: %s',
			'status_done'     => 'Code copied to the clipboard.',
			'status_failed'   => 'Copying failed. The code is selected for copying with Ctrl+C or ⌘+C.',
			'status_noselect' => 'Copying failed.',
			'status_line'     => 'Line %d selected. Copy it with Ctrl+C or ⌘+C.',
			'more'            => 'Show all lines (%d)',
			'less'            => 'Show fewer lines',
		);
		if ( ! self::load_translation( $locale ) ) {
			return $source;
		}
		$controller = WP_Translation_Controller::get_instance();
		foreach ( $source as $key => $text ) {
			$translated    = $controller->translate( $text, '', 'code-beautifier-block', $locale );
			$texts[ $key ] = ( is_string( $translated ) && '' !== $translated ) ? $translated : $text;
		}
		return $texts;
	}

	/**
	 * Loads the translation file of this plugin for one locale, next to the
	 * translations of the current request. Files are looked up where WordPress
	 * looks for them (wp-content/languages/plugins), PHP format first.
	 *
	 * @param string $locale Locale.
	 * @return bool Whether a translation for the locale is available.
	 */
	private static function load_translation( $locale ) {
		global $wp_textdomain_registry;
		static $loaded = array();
		if ( isset( $loaded[ $locale ] ) ) {
			return $loaded[ $locale ];
		}
		$loaded[ $locale ] = false;
		if ( ! class_exists( 'WP_Translation_Controller' ) || ! is_object( $wp_textdomain_registry ) ) {
			return false;
		}
		$path = $wp_textdomain_registry->get( 'code-beautifier-block', $locale );
		if ( ! $path ) {
			return false;
		}
		$controller = WP_Translation_Controller::get_instance();
		foreach ( array( '.l10n.php', '.mo' ) as $extension ) {
			$file = trailingslashit( $path ) . 'code-beautifier-block-' . $locale . $extension;
			if ( is_readable( $file ) && $controller->load_file( $file, 'code-beautifier-block', $locale ) ) {
				$loaded[ $locale ] = true;
				break;
			}
		}
		return $loaded[ $locale ];
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
	 * Why not esc_html(): it does not encode an ampersand that already starts an
	 * entity, so a code sample that shows "&amp;" or "&lt;" would be displayed
	 * as "&" or "<", which changes the code. Here every ampersand is encoded, and
	 * so are <, > and invalid UTF-8 (ENT_SUBSTITUTE). Quotes stay as they are,
	 * because the result is only ever used as text between tags, never inside an
	 * attribute. Everything render() returns around this text is fixed markup;
	 * its dynamic parts go through esc_attr(), esc_html() and
	 * get_block_wrapper_attributes().
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
