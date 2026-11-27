/**
 * Code Beautifier Block - editor.
 *
 * Plain JavaScript without a build step. The block saves a plain <pre><code>
 * copy of the code; the highlighted markup is rendered on the server, and the
 * "Preview" button shows exactly that rendering inside the editor.
 *
 * 1.2.0: while editing, line numbers stand next to the code and highlighted
 * lines are marked; a click on a number (Shift+click for a range, or arrow
 * keys and Space) switches the line in "Highlight lines". The head shows the
 * longest line. None of this changes what the block saves.
 */
( function ( blocks, element, blockEditor, components, i18n, ServerSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var __ = i18n.__;
	var _n = i18n._n;
	var sprintf = i18n.sprintf;
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

	// Same reading of "2, 5-7" as Code_Beautifier_Renderer::marked_lines(), for the hint below the field.
	function parseLines( spec ) {
		var count = 0;
		var bad = [];
		String( spec || '' ).split( ',' ).forEach( function ( part ) {
			var m;
			part = part.trim();
			if ( '' === part ) {
				return;
			}
			m = /^(\d+)\s*-\s*(\d+)$/.exec( part );
			if ( m ) {
				count += Math.max( 0, Math.min( parseInt( m[ 2 ], 10 ), parseInt( m[ 1 ], 10 ) + 5000 ) - parseInt( m[ 1 ], 10 ) + 1 );
			} else if ( /^\d+$/.test( part ) ) {
				count += 1;
			} else {
				bad.push( part );
			}
		} );
		return { count: count, bad: bad };
	}

	// The set of highlighted line numbers, as Code_Beautifier_Renderer::marked_lines() reads it.
	function markedSet( spec ) {
		var set = {};
		String( spec || '' ).split( ',' ).forEach( function ( part ) {
			var m;
			var i;
			part = part.trim();
			m = /^(\d+)\s*-\s*(\d+)$/.exec( part );
			if ( m ) {
				for ( i = parseInt( m[ 1 ], 10 ); i <= Math.min( parseInt( m[ 2 ], 10 ), parseInt( m[ 1 ], 10 ) + 5000 ); i++ ) {
					set[ i ] = true;
				}
			} else if ( /^\d+$/.test( part ) ) {
				set[ parseInt( part, 10 ) ] = true;
			}
		} );
		return set;
	}

	// A set of line numbers as a short list: 2, 5-7.
	function setToSpec( set ) {
		var nums = Object.keys( set ).map( function ( n ) {
			return parseInt( n, 10 );
		} ).sort( function ( a, b ) {
			return a - b;
		} );
		var out = [];
		var i = 0;
		while ( i < nums.length ) {
			var j = i;
			while ( j + 1 < nums.length && nums[ j + 1 ] === nums[ j ] + 1 ) {
				j++;
			}
			out.push( j > i ? nums[ i ] + '-' + nums[ j ] : String( nums[ i ] ) );
			i = j + 1;
		}
		return out.join( ', ' );
	}

	function linesHelp( spec ) {
		var parsed = parseLines( spec );
		if ( parsed.bad.length ) {
			/* translators: %s: parts of the line list that are not understood */
			return sprintf( __( 'Not understood: %s. Use numbers and ranges, for example 2, 5-7.', 'code-beautifier-block' ), parsed.bad.join( ', ' ) );
		}
		if ( parsed.count ) {
			/* translators: %d: number of highlighted lines */
			return sprintf( _n( '%d line will be highlighted.', '%d lines will be highlighted.', parsed.count, 'code-beautifier-block' ), parsed.count );
		}
		return __( 'For example 2, 5-7', 'code-beautifier-block' );
	}

	function Edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var preview = useState( false );
		var isPreview = preview[ 0 ];
		var setPreview = preview[ 1 ];
		var focusState = useState( 0 );
		var focusLine = focusState[ 0 ];
		var setFocusLine = focusState[ 1 ];
		var anchorState = useState( null );
		var anchorLine = anchorState[ 0 ];
		var setAnchorLine = anchorState[ 1 ];
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
					help: linesHelp( attributes.highlightLines ),
					className: parseLines( attributes.highlightLines ).bad.length ? 'cbb-editor__invalid' : undefined,
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
				} ),
				el( components.ToggleControl, {
					label: __( 'Collapse long code', 'code-beautifier-block' ),
					help: __( 'From 25 lines on, readers first see 15 lines and a button that shows all. The whole code stays in the page and is copied in full.', 'code-beautifier-block' ),
					checked: !! attributes.collapse,
					onChange: function ( value ) {
						setAttributes( { collapse: value } );
					},
					__nextHasNoMarginBottom: true,
				} )
			)
		);

		var lineCount = ( attributes.code || '' ).replace( /\s+$/, '' ).split( '\n' ).length;
		var codeLines = ( attributes.code || '' ).split( '\n' );
		var longest = codeLines.reduce( function ( max, line ) {
			return Math.max( max, line.replace( /\t/g, '    ' ).length );
		}, 0 );
		var start = typeof attributes.startLine === 'number' ? attributes.startLine : 1;
		var marked = markedSet( attributes.highlightLines );

		// Switch one line (or, with Shift, the range from the last clicked line) in "Highlight lines".
		function toggleLine( number, range ) {
			var set = markedSet( attributes.highlightLines );
			var on = ! set[ number ];
			var from = number;
			var to = number;
			var n;
			if ( range && null !== anchorLine ) {
				from = Math.min( anchorLine, number );
				to = Math.max( anchorLine, number );
				on = true;
			}
			for ( n = from; n <= to; n++ ) {
				if ( on ) {
					set[ n ] = true;
				} else {
					delete set[ n ];
				}
			}
			setAnchorLine( number );
			setAttributes( { highlightLines: setToSpec( set ) } );
		}

		function onGutterKey( event, index ) {
			var next = index;
			if ( 'ArrowDown' === event.key ) {
				next = Math.min( codeLines.length - 1, index + 1 );
			} else if ( 'ArrowUp' === event.key ) {
				next = Math.max( 0, index - 1 );
			} else if ( 'Home' === event.key ) {
				next = 0;
			} else if ( 'End' === event.key ) {
				next = codeLines.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			setFocusLine( next );
			var target = event.currentTarget.parentNode.children[ next ];
			if ( target ) {
				target.focus();
			}
		}

		var gutter = attributes.code
			? el(
				'div',
				{
					className: 'cbb-editor__gutter',
					role: 'group',
					'aria-label': __( 'Line numbers: switch the highlighting of a line', 'code-beautifier-block' ),
				},
				codeLines.map( function ( line, index ) {
					var number = start + index;
					return el(
						'button',
						{
							key: index,
							type: 'button',
							className: 'cbb-editor__num' + ( marked[ number ] ? ' is-marked' : '' ),
							'aria-pressed': marked[ number ] ? 'true' : 'false',
							tabIndex: index === Math.min( focusLine, codeLines.length - 1 ) ? 0 : -1,
							/* translators: %d: line number */
							'aria-label': sprintf( __( 'Highlight line %d', 'code-beautifier-block' ), number ),
							onClick: function ( event ) {
								setFocusLine( index );
								toggleLine( number, event.shiftKey );
							},
							onKeyDown: function ( event ) {
								onGutterKey( event, index );
							},
						},
						String( number )
					);
				} )
			)
			: null;
		var marks = attributes.code
			? el(
				'div',
				{ className: 'cbb-editor__marks', 'aria-hidden': 'true' },
				codeLines.map( function ( line, index ) {
					return marked[ start + index ]
						? el( 'span', { key: index, style: { top: 'calc(14px + ' + index + ' * 1.65em)' } } )
						: null;
				} )
			)
			: null;
		var languageMenu = components.ToolbarDropdownMenu
			? el( components.ToolbarDropdownMenu, {
				icon: 'editor-code',
				label: __( 'Language', 'code-beautifier-block' ),
				text: current ? current.label : attributes.language,
				controls: languages.map( function ( l ) {
					return {
						title: l.label,
						isActive: l.value === attributes.language,
						onClick: function () {
							setAttributes( { language: l.value } );
						},
					};
				} ),
			} )
			: null;

		var toolbar = el(
			blockEditor.BlockControls,
			null,
			el( components.ToolbarGroup, null, languageMenu ),
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
					el( 'span', { className: 'cbb-editor__lang', 'data-lang': attributes.language }, current ? current.label : attributes.language ),
					attributes.title ? el( 'span', { className: 'cbb-editor__title' }, attributes.title ) : null,
					el(
						'span',
						{ className: 'cbb-editor__meta' },
						attributes.code
							? el(
								'span',
								null,
								/* translators: %d: number of lines of code */
								sprintf( _n( '%d line', '%d lines', lineCount, 'code-beautifier-block' ), lineCount )
							)
							: null,
						el( 'span', { className: 'cbb-editor__chip' + ( attributes.wrap ? ' is-on' : '' ) }, attributes.wrap ? __( 'Wraps', 'code-beautifier-block' ) : __( 'Scrolls', 'code-beautifier-block' ) ),
						longest > 80
							? el(
								'span',
								{
									className: 'cbb-editor__chip' + ( attributes.wrap ? '' : ' is-warn' ),
									title: attributes.wrap
										? __( 'Long lines wrap inside the block.', 'code-beautifier-block' )
										: __( 'Without wrapping, long lines scroll sideways inside the block, especially on phones.', 'code-beautifier-block' ),
								},
								/* translators: %d: number of characters of the longest line */
								sprintf( __( 'Longest line: %d characters', 'code-beautifier-block' ), longest )
							)
							: null,
						attributes.highlightLines && parseLines( attributes.highlightLines ).count
							? el( 'span', { className: 'cbb-editor__chip is-mark' }, __( 'Highlighted lines', 'code-beautifier-block' ) )
							: null
					)
				),
				el(
					'div',
					{ className: 'cbb-editor__body' + ( gutter ? ' has-gutter' : '' ) },
					gutter,
					el(
						'div',
						{ className: 'cbb-editor__field' },
						marks,
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
					)
				)
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
