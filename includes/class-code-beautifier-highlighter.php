<?php
/**
 * Regex based tokenizer for the supported languages.
 *
 * Every grammar is a list of rules. All rules of a language are joined into one
 * pattern with named alternatives, so a single preg_match_all() call finds the
 * leftmost token at each position; text between tokens stays plain. Rules whose
 * type starts with "@" hand the matched text to a method for a finer split
 * (HTML tags, strings with variables, embedded scripts).
 *
 * @package Code_Beautifier_Block
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns source code into a list of typed tokens.
 */
final class Code_Beautifier_Highlighter {

	/**
	 * Compiled patterns per language.
	 *
	 * @var array
	 */
	private static $compiled = array();

	/**
	 * Languages offered in the editor: slug => label.
	 *
	 * @return array
	 */
	public static function languages() {
		return array(
			'auto'       => __( 'Detect automatically', 'code-beautifier-block' ),
			'plain'      => __( 'Plain text', 'code-beautifier-block' ),
			'apache'     => 'Apache',
			'bash'       => 'Bash / Shell',
			'clike'      => 'C / C++ / Java / Go',
			'css'        => 'CSS',
			'diff'       => 'Diff',
			'dockerfile' => 'Dockerfile',
			'html'       => 'HTML / XML',
			'http'       => 'HTTP',
			'ini'        => 'INI / TOML / .env',
			'javascript' => 'JavaScript',
			'json'       => 'JSON',
			'markdown'   => 'Markdown',
			'nginx'      => 'nginx',
			'php'        => 'PHP',
			'powershell' => 'PowerShell',
			'python'     => 'Python',
			'sql'        => 'SQL',
			'typescript' => 'TypeScript',
			'yaml'       => 'YAML',
		);
	}

	/**
	 * Short label shown above the code.
	 *
	 * @param string $lang Normalized language slug.
	 * @return string
	 */
	public static function label( $lang ) {
		$labels = array(
			'plain'      => __( 'Text', 'code-beautifier-block' ),
			'apache'     => 'Apache',
			'bash'       => 'Shell',
			'clike'      => __( 'Code', 'code-beautifier-block' ),
			'css'        => 'CSS',
			'diff'       => 'Diff',
			'dockerfile' => 'Dockerfile',
			'html'       => 'HTML',
			'http'       => 'HTTP',
			'ini'        => __( 'Config', 'code-beautifier-block' ),
			'javascript' => 'JavaScript',
			'json'       => 'JSON',
			'markdown'   => 'Markdown',
			'nginx'      => 'nginx',
			'php'        => 'PHP',
			'powershell' => 'PowerShell',
			'python'     => 'Python',
			'sql'        => 'SQL',
			'typescript' => 'TypeScript',
			'yaml'       => 'YAML',
		);
		return isset( $labels[ $lang ] ) ? $labels[ $lang ] : $labels['plain'];
	}

	/**
	 * Maps aliases and class names (language-xyz) to a supported slug.
	 *
	 * @param string $lang Raw language name.
	 * @return string Supported slug, "auto" or "plain".
	 */
	public static function normalize( $lang ) {
		$lang  = strtolower( trim( (string) $lang ) );
		$lang  = preg_replace( '/^(?:language|lang)-/', '', $lang );
		$alias = array(
			'sh'         => 'bash',
			'shell'      => 'bash',
			'zsh'        => 'bash',
			'console'    => 'bash',
			'shellscript' => 'bash',
			'terminal'   => 'bash',
			'cmd'        => 'bash',
			'ps'         => 'powershell',
			'ps1'        => 'powershell',
			'pwsh'       => 'powershell',
			'js'         => 'javascript',
			'jsx'        => 'javascript',
			'mjs'        => 'javascript',
			'node'       => 'javascript',
			'ts'         => 'typescript',
			'tsx'        => 'typescript',
			'py'         => 'python',
			'python3'    => 'python',
			'yml'        => 'yaml',
			'xml'        => 'html',
			'svg'        => 'html',
			'xhtml'      => 'html',
			'vue'        => 'html',
			'markup'     => 'html',
			'scss'       => 'css',
			'less'       => 'css',
			'toml'       => 'ini',
			'env'        => 'ini',
			'dotenv'     => 'ini',
			'conf'       => 'ini',
			'properties' => 'ini',
			'systemd'    => 'ini',
			'cfg'        => 'ini',
			'jsonc'      => 'json',
			'json5'      => 'json',
			'mysql'      => 'sql',
			'postgresql' => 'sql',
			'postgres'   => 'sql',
			'sqlite'     => 'sql',
			'bigquery'   => 'sql',
			'plsql'      => 'sql',
			'md'         => 'markdown',
			'docker'     => 'dockerfile',
			'patch'      => 'diff',
			'htaccess'   => 'apache',
			'apacheconf' => 'apache',
			'c'          => 'clike',
			'cpp'        => 'clike',
			'c++'        => 'clike',
			'java'       => 'clike',
			'go'         => 'clike',
			'golang'     => 'clike',
			'rust'       => 'clike',
			'kotlin'     => 'clike',
			'csharp'     => 'clike',
			'cs'         => 'clike',
			'swift'      => 'clike',
			'dart'       => 'clike',
			'text'       => 'plain',
			'txt'        => 'plain',
			'plaintext'  => 'plain',
			'output'     => 'plain',
			'log'        => 'plain',
			'none'       => 'plain',
		);
		if ( isset( $alias[ $lang ] ) ) {
			return $alias[ $lang ];
		}
		$known = self::languages();
		return isset( $known[ $lang ] ) ? $lang : 'plain';
	}

	/**
	 * Splits code into tokens.
	 *
	 * @param string $code Source code with \n line ends.
	 * @param string $lang Supported slug (not "auto").
	 * @return array List of array( type|null, text ).
	 */
	public static function tokenize( $code, $lang ) {
		if ( 'php' === $lang ) {
			return self::php_document( $code );
		}
		return self::run( $code, $lang );
	}

	/**
	 * Applies one grammar to a piece of text.
	 *
	 * @param string $text Text.
	 * @param string $lang Grammar name.
	 * @return array Tokens.
	 */
	private static function run( $text, $lang ) {
		if ( '' === $text ) {
			return array();
		}
		$grammar = self::compile( $lang );
		if ( null === $grammar ) {
			return array( array( null, $text ) );
		}
		list( $pattern, $types ) = $grammar;
		$found = preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL );
		if ( false === $found ) {
			// Invalid UTF-8 or a backtracking limit: show the code unhighlighted rather than not at all.
			return array( array( null, $text ) );
		}
		$tokens = array();
		$pos    = 0;
		foreach ( $matches as $m ) {
			$start = $m[0][1];
			$whole = $m[0][0];
			if ( '' === $whole ) {
				continue;
			}
			if ( $start > $pos ) {
				$tokens[] = array( null, substr( $text, $pos, $start - $pos ) );
			}
			$type = null;
			foreach ( $types as $group => $rule_type ) {
				if ( null !== $m[ $group ][0] ) {
					$type = $rule_type;
					break;
				}
			}
			if ( null !== $type && '@' === $type[0] ) {
				foreach ( self::callback( substr( $type, 1 ), $whole ) as $t ) {
					$tokens[] = $t;
				}
			} else {
				$tokens[] = array( $type, $whole );
			}
			$pos = $start + strlen( $whole );
		}
		if ( $pos < strlen( $text ) ) {
			$tokens[] = array( null, substr( $text, $pos ) );
		}
		return $tokens;
	}

	/**
	 * Finer split for rules marked with "@".
	 *
	 * @param string $name Callback name.
	 * @param string $text Matched text.
	 * @return array Tokens.
	 */
	private static function callback( $name, $text ) {
		switch ( $name ) {
			case 'tag':
				return self::html_tag( $text );
			case 'script':
				return self::embedded( $text, 'javascript' );
			case 'style':
				return self::embedded( $text, 'css' );
			case 'interp':
				return self::interpolated( $text );
			case 'php':
				return self::php_region( $text );
		}
		return array( array( null, $text ) );
	}

	/**
	 * PHP files may mix HTML and PHP. Without an opening tag the whole snippet is PHP.
	 *
	 * @param string $code Code.
	 * @return array Tokens.
	 */
	private static function php_document( $code ) {
		if ( false === strpos( $code, '<' . '?' ) ) {
			return self::run( $code, 'php' );
		}
		return self::run( $code, 'phphtml' );
	}

	/**
	 * A <?php ... ?> region inside HTML.
	 *
	 * @param string $text Region including the tags.
	 * @return array Tokens.
	 */
	private static function php_region( $text ) {
		return self::run( $text, 'php' );
	}

	/**
	 * Splits an HTML tag into bracket, name, attributes and values.
	 *
	 * @param string $tag Complete tag.
	 * @return array Tokens.
	 */
	private static function html_tag( $tag ) {
		if ( ! preg_match( '~^(</?)([^\s/>]+)~', $tag, $m ) ) {
			return array( array( 'tag', $tag ) );
		}
		$tokens = array(
			array( 'punct', $m[1] ),
			array( 'tag', $m[2] ),
		);
		$rest   = substr( $tag, strlen( $m[0] ) );
		$found  = preg_match_all( '~([^\s=/>"\']+)(?:(\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s>]+))?~', $rest, $attrs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$pos    = 0;
		if ( $found ) {
			foreach ( $attrs as $a ) {
				if ( $a[0][1] > $pos ) {
					$tokens[] = array( null, substr( $rest, $pos, $a[0][1] - $pos ) );
				}
				$tokens[] = array( 'attr', $a[1][0] );
				if ( isset( $a[2] ) && '' !== $a[2][0] ) {
					$tokens[] = array( 'punct', $a[2][0] );
					$tokens[] = array( 'string', $a[3][0] );
				}
				$pos = $a[0][1] + strlen( $a[0][0] );
			}
		}
		$tail = substr( $rest, $pos );
		if ( '' !== $tail ) {
			$tokens[] = array( 'punct', $tail );
		}
		return $tokens;
	}

	/**
	 * Embedded script and style elements: tags as HTML, content in the embedded language.
	 *
	 * @param string $text Element.
	 * @param string $lang Language of the content.
	 * @return array Tokens.
	 */
	private static function embedded( $text, $lang ) {
		$open_end  = strpos( $text, '>' ) + 1;
		$close_pos = strrpos( $text, '</' );
		if ( false === $close_pos || $close_pos < $open_end ) {
			return self::html_tag( $text );
		}
		return array_merge(
			self::html_tag( substr( $text, 0, $open_end ) ),
			self::run( substr( $text, $open_end, $close_pos - $open_end ), $lang ),
			self::html_tag( substr( $text, $close_pos ) )
		);
	}

	/**
	 * Double quoted strings in shell and PHP: variables inside stay visible.
	 *
	 * @param string $text String literal.
	 * @return array Tokens.
	 */
	private static function interpolated( $text ) {
		$found = preg_match_all( '~(?<!\\\\)\$(?:\{[^}\n]*\}|[A-Za-z_]\w*(?:->\w+|\[[^\]\n]*\])?|[0-9@#?$!*])~', $text, $vars, PREG_OFFSET_CAPTURE );
		if ( ! $found ) {
			return array( array( 'string', $text ) );
		}
		$tokens = array();
		$pos    = 0;
		foreach ( $vars[0] as $v ) {
			if ( $v[1] > $pos ) {
				$tokens[] = array( 'string', substr( $text, $pos, $v[1] - $pos ) );
			}
			$tokens[] = array( 'variable', $v[0] );
			$pos      = $v[1] + strlen( $v[0] );
		}
		if ( $pos < strlen( $text ) ) {
			$tokens[] = array( 'string', substr( $text, $pos ) );
		}
		return $tokens;
	}

	/**
	 * Builds (once) the combined pattern of a grammar.
	 *
	 * @param string $lang Grammar name.
	 * @return array|null array( pattern, group => type ) or null for plain text.
	 */
	private static function compile( $lang ) {
		if ( array_key_exists( $lang, self::$compiled ) ) {
			return self::$compiled[ $lang ];
		}
		$rules = self::rules( $lang );
		if ( empty( $rules ) ) {
			self::$compiled[ $lang ] = null;
			return null;
		}
		$parts = array();
		$types = array();
		foreach ( $rules as $i => $rule ) {
			$parts[]             = '(?<r' . $i . '>' . $rule[1] . ')';
			$types[ 'r' . $i ] = $rule[0];
		}
		self::$compiled[ $lang ] = array( '~' . implode( '|', $parts ) . '~mu', $types );
		return self::$compiled[ $lang ];
	}

	/**
	 * Word list as alternation with word boundaries.
	 *
	 * @param string $words Space separated words.
	 * @param bool   $ci    Case insensitive.
	 * @return string
	 */
	private static function words( $words, $ci = false ) {
		$list = implode( '|', array_map( 'preg_quote', preg_split( '/\s+/', trim( $words ) ) ) );
		return ( $ci ? '(?i:' : '(?:' ) . '(?<![\w$-])(?:' . $list . ')(?![\w-]))';
	}

	/**
	 * Grammar rules: list of array( type, regex ).
	 *
	 * @param string $lang Grammar name.
	 * @return array
	 */
	private static function rules( $lang ) {
		$dq        = '"(?:[^"\\\\]|\\\\[\s\S])*+"';
		$sq        = '\'(?:[^\'\\\\]|\\\\[\s\S])*+\'';
		$dq_line   = '"(?:[^"\\\\\n]|\\\\.)*+"';
		$sq_line   = '\'(?:[^\'\\\\\n]|\\\\.)*+\'';
		$c_block   = '/\*[\s\S]*?(?:\*/|\z)';
		$c_line    = '//[^\n]*';
		$number    = '(?<![\w.$#-])-?(?:0[xX][\da-fA-F_]+|0[bB][01_]+|\d[\d_]*(?:\.\d+)?(?:[eE][+-]?\d+)?|\.\d+)(?![\w.])';
		$call      = '(?<![\w$])[A-Za-z_]\w*(?=\s*\()';
		$class     = '(?<![\w$])[A-Z][a-z][A-Za-z0-9_]*(?![\w$])';
		$constants = '(?<![\w$])[A-Z][A-Z0-9_]{2,}(?![\w$])';

		switch ( $lang ) {
			case 'bash':
				return array(
					array( 'comment', '(?<![^\s;|&(])#[^\n]*' ),
					array( '@interp', $dq ),
					array( 'string', '\'[^\']*+\'' ),
					array( 'variable', '\$(?:\{[^}\n]*\}|\(\(?|[A-Za-z_]\w*|[0-9@#?$!*-])' ),
					array( 'keyword', self::words( 'if then else elif fi for while until do done case esac in function select return exit export local readonly declare source alias unset shift break continue set trap eval exec' ) ),
					array( 'function', self::words( 'sudo apt apt-get dpkg snap systemctl service journalctl docker docker-compose podman git curl wget cd ls ll cat less tail head echo printf grep egrep sed awk cut sort uniq wc tr xargs tee find locate chmod chown chgrp mkdir rmdir rm cp mv ln touch ssh scp sftp rsync tar gzip gunzip zip unzip openssl dig nslookup host ping traceroute ip ifconfig ss netstat nc ufw iptables nft certbot nginx apache2ctl a2enmod a2ensite a2dismod a2dissite php python python3 pip pip3 node npm npx yarn pnpm composer wp mysql mysqldump mariadb psql redis-cli crontab kill killall pkill ps top htop df du free mount umount lsblk blkid fdisk parted mkfs useradd usermod passwd su whoami id hostnamectl timedatectl uname uptime watch nano vim vi make cmake gcc test read sleep date env which type nohup setsid flock gh jq yq ffmpeg ffprobe sox convert rclone restic borg nvidia-smi vllm ollama uv pm2 supervisorctl kubectl helm terraform ansible' ) ),
					array( 'attr', '(?<=\s)--?[A-Za-z][\w-]*' ),
					array( 'number', $number ),
					array( 'operator', '&&|\|\||[|;]|>>?|2>&1' ),
				);

			case 'powershell':
				return array(
					array( 'comment', '<#[\s\S]*?(?:#>|\z)|#[^\n]*' ),
					array( '@interp', $dq ),
					array( 'string', '\'(?:[^\']|\'\')*+\'' ),
					array( 'variable', '\$(?:\{[^}\n]*\}|[\w:]+)' ),
					array( 'keyword', self::words( 'if else elseif switch foreach for while do until break continue return function param begin process end try catch finally throw trap filter in exit class enum using', true ) ),
					array( 'function', '(?<![\w-])[A-Za-z]+-[A-Za-z]+(?![\w-])' ),
					array( 'attr', '(?<=\s)-[A-Za-z]\w*' ),
					array( 'type', '\[[A-Za-z][\w.]*(?:\[\])?\]' ),
					array( 'number', $number ),
				);

			case 'sql':
				return array(
					array( 'comment', '--[^\n]*|' . $c_block . '|(?<![^\s])#[^\n]*' ),
					array( 'string', '\'(?:[^\'\\\\]|\\\\[\s\S]|\'\')*+\'|' . $dq ),
					array( 'variable', '`[^`\n]*`|(?<![\w:]):[A-Za-z_]\w*|@@?[A-Za-z_]\w*|\$\d+' ),
					array( 'constant', self::words( 'true false null', true ) ),
					array( 'keyword', self::words( 'select from where and or not in is like ilike between exists join inner left right full outer cross on using group by order having limit offset fetch first next rows only union all distinct as case when then else end insert into values update set delete create alter drop table view index unique primary key foreign references constraint default check database schema if replace truncate with recursive returning grant revoke to begin commit rollback transaction show describe explain analyze use partition over window qualify unnest struct array interval cast safe_cast extract date_trunc asc desc nulls lateral pivot unpivot merge matched temporary temp materialized trigger procedure function returns language declare cluster engine charset collate auto_increment', true ) ),
					array( 'type', self::words( 'int integer bigint smallint tinyint float float64 int64 numeric decimal double real varchar char text string bool boolean date datetime timestamp time json jsonb blob bytes uuid serial', true ) ),
					array( 'function', $call ),
					array( 'number', $number ),
				);

			case 'python':
				return array(
					array( 'comment', '#[^\n]*' ),
					array( 'string', '(?i:[rbuf]{0,2})(?:"""[\s\S]*?(?:"""|\z)|\'\'\'[\s\S]*?(?:\'\'\'|\z)|' . $dq_line . '|' . $sq_line . ')' ),
					array( 'meta', '^[ \t]*@[\w.]+' ),
					array( 'function', '(?<=\bdef )\w+' ),
					array( 'type', '(?<=\bclass )\w+' ),
					array( 'constant', self::words( 'True False None' ) ),
					array( 'keyword', self::words( 'and as assert async await break class continue def del elif else except finally for from global if import in is lambda nonlocal not or pass raise return try while with yield match case' ) ),
					array( 'variable', self::words( 'self cls' ) ),
					array( 'function', $call ),
					array( 'number', $number ),
				);

			case 'javascript':
			case 'typescript':
				$kw = 'break case catch class const continue debugger default delete do else export extends finally for function if import in instanceof let new of return static super switch throw try typeof var void while with yield async await from get set';
				if ( 'typescript' === $lang ) {
					$kw .= ' interface type enum implements declare namespace abstract private protected public readonly as keyof infer is';
				}
				$rules = array(
					array( 'comment', $c_block . '|' . $c_line ),
					array( 'string', '`(?:[^`\\\\]|\\\\[\s\S])*+`|' . $dq_line . '|' . $sq_line ),
					array( 'constant', self::words( 'true false null undefined NaN Infinity this' ) ),
					array( 'keyword', self::words( $kw ) ),
				);
				if ( 'typescript' === $lang ) {
					$rules[] = array( 'type', self::words( 'string number boolean any unknown never void object' ) );
				}
				$rules[] = array( 'function', $call );
				$rules[] = array( 'type', $class );
				$rules[] = array( 'number', $number );
				$rules[] = array( 'operator', '=>' );
				return $rules;

			case 'phphtml':
				return array(
					array( '@php', '<\?(?:php|=)?[\s\S]*?(?:\?>|\z)' ),
					array( 'comment', '<!--[\s\S]*?(?:-->|\z)' ),
					array( '@script', '<script\b[^>]*>[\s\S]*?</script\s*>' ),
					array( '@style', '<style\b[^>]*>[\s\S]*?</style\s*>' ),
					array( 'meta', '<![A-Za-z][^>]*>' ),
					array( '@tag', '</?[A-Za-z][^\s/>]*(?:[^>"\'<]|"[^"]*"|\'[^\']*\')*>' ),
					array( 'constant', '&#?\w+;' ),
				);

			case 'php':
				return array(
					array( 'meta', '<\?(?:php|=)?|\?>' ),
					array( 'comment', $c_block . '|' . $c_line . '|#(?!\[)[^\n]*' ),
					array( 'meta', '#\[[^\]\n]*\]' ),
					array( 'string', '<<<[ \t]*(?<hq>["\']?)(?<hd>[A-Za-z_]\w*)\k<hq>\n[\s\S]*?\n[ \t]*\k<hd>\b' ),
					array( '@interp', $dq ),
					array( 'string', $sq ),
					array( 'variable', '\$+[A-Za-z_]\w*' ),
					array( 'type', '\((?:int|integer|string|bool|boolean|float|double|array|object)\)' ),
					array( 'constant', self::words( 'true false null', true ) ),
					array( 'keyword', self::words( 'abstract and array as break callable case catch class clone const continue declare default do echo else elseif empty enddeclare endfor endforeach endif endswitch endwhile enum extends final finally fn for foreach function global goto if implements include include_once instanceof insteadof interface isset list match namespace new or print private protected public readonly require require_once return static switch throw trait try unset use var while yield', true ) ),
					array( 'type', '(?<=\bnew |\bclass |\bextends |\binterface |\btrait |\bimplements |\binstanceof )\\\\?[A-Za-z_][\w\\\\]*' ),
					array( 'type', '\\\\?[A-Z][\w\\\\]*(?=::)' ),
					array( 'function', $call ),
					array( 'constant', $constants ),
					array( 'number', $number ),
					array( 'operator', '=>|->|::' ),
				);

			case 'html':
				return array(
					array( 'comment', '<!--[\s\S]*?(?:-->|\z)' ),
					array( 'meta', '<!\[CDATA\[[\s\S]*?(?:\]\]>|\z)|<![A-Za-z][^>]*>|<\?[\s\S]*?(?:\?>|\z)' ),
					array( '@script', '<script\b[^>]*>[\s\S]*?</script\s*>' ),
					array( '@style', '<style\b[^>]*>[\s\S]*?</style\s*>' ),
					array( '@tag', '</?[A-Za-z][^\s/>]*(?:[^>"\'<]|"[^"]*"|\'[^\']*\')*>' ),
					array( 'constant', '&#?\w+;' ),
				);

			case 'css':
				return array(
					array( 'comment', $c_block ),
					array( 'string', $dq_line . '|' . $sq_line ),
					array( 'keyword', '@[\w-]+|!important' ),
					array( 'variable', '--[\w-]+' ),
					array( 'attr', '(?<![\w-])-?[a-zA-Z][\w-]*(?=\s*:[^;{}]*(?:[;}]|$))' ),
					array( 'selector', '[^\s{};][^{};]*?(?=\s*\{)' ),
					array( 'number', '#[\da-fA-F]{3,8}(?![\w-])|(?<![\w-])-?(?:\d+\.?\d*|\.\d+)(?:%|[a-zA-Z]+)?' ),
					array( 'function', '(?<![\w-])[\w-]+(?=\()' ),
				);

			case 'json':
				return array(
					array( 'comment', $c_block . '|' . $c_line ),
					array( 'attr', $dq_line . '(?=\s*:)' ),
					array( 'string', $dq_line ),
					array( 'constant', self::words( 'true false null' ) ),
					array( 'number', '-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?' ),
				);

			case 'yaml':
				$end = '(?=[ \t]*(?:$|[,\]}#]))';
				return array(
					array( 'comment', '(?<![^\s])#[^\n]*' ),
					array( 'meta', '^(?:---|\.\.\.)(?=\s|$)|(?<=[:\-] )[|>][-+]?\d?(?=[ \t]*$)' ),
					array( 'variable', '\{\{[\s\S]*?\}\}|\$\{\{[\s\S]*?\}\}|(?<![^\s])[&*][\w-]+' ),
					array( 'attr', '(?<![^\s\-{,?])(?:"[^"\n]*"|\'[^\'\n]*\'|[^\s#:\'"\[\]{},\-][^:\n#]*?|-[^\s:\n#][^:\n#]*?)(?=:(?:[ \t]|$))' ),
					array( 'string', $dq_line . '|' . $sq_line ),
					array( 'constant', '(?<![^\s\[{,])(?i:true|false|yes|no|on|off|null|\~)' . $end ),
					array( 'number', '(?<![^\s\[{,])-?\d[\d_]*(?:\.\d+)?(?:e[+-]?\d+)?' . $end ),
					array( 'operator', '(?<![^\s])-(?=\s)' ),
				);

			case 'ini':
				return array(
					array( 'comment', '^[ \t]*[;#][^\n]*' ),
					array( 'type', '^[ \t]*\[\[?[^\]\n]*\]\]?' ),
					array( 'attr', '^[ \t]*(?:export[ \t]+)?[\w.\-@$/"\']+(?=[ \t]*[=:])' ),
					array( 'string', $dq_line . '|' . $sq_line ),
					array( 'variable', '\$\{[^}\n]*\}|\$[A-Za-z_]\w*|%[\w]+%' ),
					array( 'constant', '(?<=[=:])[ \t]*(?i:true|false|yes|no|on|off|null)(?=[ \t]*$)' ),
					array( 'number', '(?<=[=:])[ \t]*-?\d+(?:\.\d+)?[a-zA-Z%]*(?=[ \t]*$)' ),
				);

			case 'nginx':
				return array(
					array( 'comment', '#[^\n]*' ),
					array( 'string', $dq_line . '|' . $sq_line ),
					array( 'variable', '\$\w+' ),
					array( 'keyword', '(?:^|(?<=[;{}]))[ \t]*[A-Za-z_]\w*(?=[\s;{])' ),
					array( 'constant', self::words( 'on off' ) ),
					array( 'operator', '(?<=\s)(?:=|\~\*?|\^\~)(?=\s)' ),
					array( 'number', '(?<![\w.:/-])\d+(?:\.\d+)?[kKmMgGsdhwy]?(?![\w.:/-])' ),
				);

			case 'apache':
				return array(
					array( 'comment', '^[ \t]*#[^\n]*' ),
					array( '@tag', '</?[A-Za-z]\w*[^>\n]*>' ),
					array( 'keyword', '^[ \t]*[A-Za-z]\w*' ),
					array( 'string', $dq_line ),
					array( 'variable', '%\{[^}\n]*\}|\$\d' ),
					array( 'meta', '\[[A-Z][A-Z0-9,=_ ]*\](?=[ \t]*$)' ),
					array( 'constant', self::words( 'on off all none', true ) ),
				);

			case 'http':
				return array(
					array( 'keyword', '^(?:GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS|CONNECT|TRACE)(?= )' ),
					array( 'meta', 'HTTP/\d(?:\.\d)?' ),
					array( 'number', '(?<=HTTP/\d\.\d |HTTP/\d )\d{3}\b' ),
					array( 'attr', '^[\w-]+(?=:)' ),
				);

			case 'diff':
				return array(
					array( 'meta', '^(?:@@[^\n]*|diff [^\n]*|index [^\n]*|--- [^\n]*|\+\+\+ [^\n]*)' ),
					array( 'inserted', '^\+[^\n]*' ),
					array( 'deleted', '^-[^\n]*' ),
				);

			case 'dockerfile':
				return array(
					array( 'comment', '^[ \t]*#[^\n]*' ),
					array( 'keyword', '^[ \t]*(?i:FROM|RUN|CMD|LABEL|EXPOSE|ENV|ADD|COPY|ENTRYPOINT|VOLUME|USER|WORKDIR|ARG|ONBUILD|STOPSIGNAL|HEALTHCHECK|SHELL|MAINTAINER)\b|(?<=\s)AS(?=\s)' ),
					array( 'string', $dq_line . '|' . $sq_line ),
					array( 'variable', '\$\{[^}\n]*\}|\$[A-Za-z_]\w*' ),
					array( 'attr', '(?<=\s)--[\w-]+' ),
					array( 'number', $number ),
				);

			case 'markdown':
				return array(
					array( 'string', '^```[\s\S]*?(?:^```|\z)|`[^`\n]+`' ),
					array( 'keyword', '^#{1,6}[ \t][^\n]*' ),
					array( 'meta', '^[ \t]*(?:[-*+]|\d+\.)(?=[ \t])|^>[^\n]*' ),
					array( 'attr', '\[[^\]\n]*\]\([^)\n]*\)' ),
					array( 'type', '\*\*[^*\n]+\*\*|__[^_\n]+__' ),
				);

			case 'clike':
				return array(
					array( 'comment', $c_block . '|' . $c_line ),
					array( 'meta', '^[ \t]*#[ \t]*\w+[^\n]*' ),
					array( 'string', '`[^`]*`|' . $dq_line . '|\'(?:[^\'\\\\\n]|\\\\.){1,8}\'' ),
					array( 'constant', self::words( 'true false null nil nullptr None this self' ) ),
					array( 'keyword', self::words( 'abstract async await auto bool boolean break byte case catch char chan class const continue default defer delete do double else enum explicit export extends extern final finally float fn for func go goto if impl implements import in inline instanceof int interface let long loop map match mod mut namespace new override package private protected pub public range return select short signed sizeof static struct super switch synchronized template throw throws trait try type typedef typename union unsafe unsigned use using var virtual void volatile where while' ) ),
					array( 'function', $call ),
					array( 'type', $class ),
					array( 'number', $number ),
				);
		}
		return array();
	}
}
