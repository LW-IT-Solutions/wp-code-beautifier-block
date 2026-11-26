<?php
/**
 * Plugin Name:       Code Beautifier Block
 * Description:       A code block with server-side syntax highlighting that stays inside the layout: long lines wrap or scroll within the block, never the page.
 * Version:           1.0.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            LW IT Solutions
 * Author URI:        https://www.lukaswojcik.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       code-beautifier-block
 *
 * @package Code_Beautifier_Block
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-code-beautifier-highlighter.php';
require_once __DIR__ . '/includes/class-code-beautifier-detector.php';
require_once __DIR__ . '/includes/class-code-beautifier-renderer.php';

/**
 * Registers the block and hands the language list to the editor script.
 */
function code_beautifier_block_register() {
	$type = register_block_type(
		__DIR__ . '/block',
		array(
			'render_callback' => array( 'Code_Beautifier_Renderer', 'render' ),
		)
	);
	if ( ! $type || empty( $type->editor_script_handles ) ) {
		return;
	}
	$languages = array();
	foreach ( Code_Beautifier_Highlighter::languages() as $value => $label ) {
		$languages[] = array(
			'value' => $value,
			'label' => $label,
		);
	}
	wp_add_inline_script(
		$type->editor_script_handles[0],
		'window.codeBeautifierLanguages = ' . wp_json_encode( $languages ) . ';',
		'before'
	);
}
add_action( 'init', 'code_beautifier_block_register' );
