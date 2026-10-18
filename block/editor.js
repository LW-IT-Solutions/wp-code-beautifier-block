/**
 * Code Beautifier Block - editor.
 *
 * Plain JavaScript without a build step. The block saves a plain <pre><code>
 * copy of the code; the highlighted markup is rendered on the server, and the
 * "Preview" button shows exactly that rendering inside the editor.
 */
( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var __ = i18n.__;
	var languages = window.codeBeautifierLanguages || [ { value: 'auto', label: 'Auto' } ];

	// Must match Code_Beautifier_Renderer::escape_saved() character for character.
	function escapeCode( code ) {
		return String( code )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /\[/g, '&#91;' )
			.replace( /\]/g, '&#93;' );
	}

	// Rich text of core blocks (may contain <br> and inline tags) to plain text.
	function htmlToText( html ) {
		var doc = new window.DOMParser().parseFromString(
			'<pre>' + String( html || '' ).replace( /<br\s*\/?>/gi, '\n' ) + '</pre>',
			'text/html'
		);
		return doc.body.textContent || '';
	}

	function languageFromClass( className ) {
		var m = /(?:^|\s)(?:language|lang)-([\w+#-]+)/.exec( className || '' );
		return m ? m[ 1 ] : 'auto';
	}

	function Edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var preview = useState( false );
		var isPreview = preview[ 0 ];
		var setPreview = preview[ 1 ];
		var blockProps = blockEditor.useBlockProps( { className: 'cbb-editor' } );
		var current = languages.filter( function ( l ) {
			return l.value === attributes.language;
		} )[ 0 ];

		var inspector = el(
			blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __( 'Code', 'code-beautifier-block' ) },
				el( components.SelectControl, {
					label: __( 'Language', 'code-beautifier-block' ),
					value: attributes.language,
					options: languages,
					onChange: function ( value ) {
						setAttributes( { language: value } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} ),
				el( components.TextControl, {
					label: __( 'File name or caption', 'code-beautifier-block' ),
					value: attributes.title,
					onChange: function ( value ) {
						setAttributes( { title: value } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} ),
				el( components.SelectControl, {
					label: __( 'Line numbers', 'code-beautifier-block' ),
					value: attributes.lineNumbers,
					options: [
						{ value: 'auto', label: __( 'From three lines on', 'code-beautifier-block' ) },
						{ value: 'on', label: __( 'Always', 'code-beautifier-block' ) },
						{ value: 'off', label: __( 'Never', 'code-beautifier-block' ) },
					],
					onChange: function ( value ) {
						setAttributes( { lineNumbers: value } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} ),
				el( components.TextControl, {
					label: __( 'First line number', 'code-beautifier-block' ),
					type: 'number',
					min: 0,
					value: String( attributes.startLine ),
					onChange: function ( value ) {
						var n = parseInt( value, 10 );
						setAttributes( { startLine: isNaN( n ) || n < 0 ? 1 : n } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} ),
				el( components.TextControl, {
					label: __( 'Highlight lines', 'code-beautifier-block' ),
					help: __( 'For example 2, 5-7', 'code-beautifier-block' ),
					value: attributes.highlightLines,
					onChange: function ( value ) {
						setAttributes( { highlightLines: value } );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} ),
				el( components.ToggleControl, {
					label: __( 'Wrap long lines', 'code-beautifier-block' ),
					help: __( 'Readers can switch this with the button above the code.', 'code-beautifier-block' ),
					checked: attributes.wrap,
					onChange: function ( value ) {
						setAttributes( { wrap: value } );
					},
					__nextHasNoMarginBottom: true,
				} )
			)
		);

		var toolbar = el(
			blockEditor.BlockControls,
			null,
			el(
				components.ToolbarGroup,
				null,
				el( components.ToolbarButton, {
					icon: isPreview ? 'edit' : 'visibility',
					label: isPreview ? __( 'Edit code', 'code-beautifier-block' ) : __( 'Preview', 'code-beautifier-block' ),
					isPressed: isPreview,
					onClick: function () {
						setPreview( ! isPreview );
					},
				} )
			)
		);

		var body = isPreview
			? el( ServerSideRender, {
				block: props.name,
				attributes: attributes,
			} )
			: el(
				'div',
				{ className: 'cbb-editor__frame' },
				el(
					'div',
					{ className: 'cbb-editor__head' },
					el( 'span', null, current ? current.label : attributes.language ),
					attributes.title ? el( 'span', { className: 'cbb-editor__title' }, attributes.title ) : null
				),
				el( blockEditor.PlainText, {
					className: 'cbb-editor__input',
					value: attributes.code || '',
					onChange: function ( value ) {
						setAttributes( { code: value } );
					},
					placeholder: __( 'Paste or write code…', 'code-beautifier-block' ),
					'aria-label': __( 'Code', 'code-beautifier-block' ),
					spellCheck: false,
					autoCapitalize: 'off',
					autoComplete: 'off',
					autoCorrect: 'off',
				} )
			);

		return el( Fragment, null, toolbar, inspector, el( 'div', blockProps, body ) );
	}

	blocks.registerBlockType( 'code-beautifier-block/code', {
		edit: Edit,
		save: function ( props ) {
			return el(
				'pre',
				blockEditor.useBlockProps.save(),
				el( 'code', null, el( element.RawHTML, null, escapeCode( props.attributes.code || '' ) ) )
			);
		},
		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'core/code' ],
					transform: function ( attrs ) {
						return blocks.createBlock( 'code-beautifier-block/code', {
							code: htmlToText( attrs.content ),
							language: languageFromClass( attrs.className ),
						} );
					},
				},
				{
					type: 'block',
					blocks: [ 'core/preformatted' ],
					transform: function ( attrs ) {
						return blocks.createBlock( 'code-beautifier-block/code', {
							code: htmlToText( attrs.content ),
						} );
					},
				},
				{
					type: 'block',
					blocks: [ 'kevinbatdorf/code-block-pro' ],
					transform: function ( attrs ) {
						return blocks.createBlock( 'code-beautifier-block/code', {
							code: attrs.code || '',
							language: attrs.language || 'auto',
						} );
					},
				},
			],
			to: [
				{
					type: 'block',
					blocks: [ 'core/code' ],
					transform: function ( attrs ) {
						return blocks.createBlock( 'core/code', {
							content: escapeCode( attrs.code || '' ).replace( /\n/g, '<br>' ),
						} );
					},
				},
			],
		},
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
) );
