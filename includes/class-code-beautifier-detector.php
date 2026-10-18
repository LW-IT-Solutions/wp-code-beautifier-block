<?php
/**
 * Guesses the language of a snippet when the author did not choose one.
 *
 * Deliberately conservative: when nothing is clear, the answer is plain text.
 * A wrong colour scheme reads worse than none.
 *
 * @package Code_Beautifier_Block
 */

defined( 'ABSPATH' ) || exit;

/**
 * Heuristic language detection.
 */
final class Code_Beautifier_Detector {

	/**
	 * Returns a supported language slug.
	 *
	 * @param string $code Code with \n line ends.
	 * @return string
	 */
	public static function guess( $code ) {
		$t     = trim( $code );
		$first = strtok( $t, "\n" );
		if ( '' === $t ) {
			return 'plain';
		}

		if ( preg_match( '~^#!.*\b(?:ba|z|da)?sh\b~', $first ) ) {
			return 'bash';
		}
		if ( preg_match( '~^#!.*\bpython~', $first ) ) {
			return 'python';
		}
		if ( preg_match( '~^#!.*\bnode\b~', $first ) ) {
			return 'javascript';
		}
		if ( false !== strpos( $t, '<?php' ) || preg_match( '~^<\?=~', $t ) ) {
			return 'php';
		}
		if ( preg_match( '~^(?:GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) \S+ HTTP/\d~', $first ) || preg_match( '~^HTTP/\d(?:\.\d)? \d{3}~', $first ) ) {
			return 'http';
		}
		if ( preg_match( '~^(?:diff --git|--- \S|@@ -\d)~m', $t ) && preg_match( '~^[+-]~m', $t ) && preg_match( '~^(?:@@|\+\+\+ )~m', $t ) ) {
			return 'diff';
		}
		if ( ( '{' === $t[0] || '[' === $t[0] ) && null !== json_decode( $t ) ) {
			return 'json';
		}
		if ( preg_match( '~^\s*<(?:\?xml|!DOCTYPE|!--|html|head|body|div|span|script|style|meta|link|a\s|p>|p\s|svg|iframe|noscript|img|button|form|input|section|article|nav|ul|ol|li|table|template|figure|picture|source|video|audio|h[1-6]|br|main|footer|header|label|select|option|textarea|url|urlset|sitemapindex|rss|feed|VirtualHost)\b~i', $t ) ) {
			return preg_match( '~^\s*<(?:VirtualHost|Directory|IfModule|Files|Location)\b~', $t ) ? 'apache' : 'html';
		}
		if ( preg_match( '~^[ \t]*FROM[ \t]+\S+~m', $t ) && preg_match( '~^[ \t]*(?:RUN|COPY|CMD|ENTRYPOINT|WORKDIR|EXPOSE|ENV)\b~m', $t ) ) {
			return 'dockerfile';
		}
		if ( preg_match( '~^[ \t]*(?:<VirtualHost|<Directory|<IfModule|<Files|RewriteEngine|RewriteRule|RewriteCond|Header (?:set|always|unset)|Require (?:all|ip|not)|Options [+-]?\w|AllowOverride|SetEnvIf|ErrorDocument|AddType|ExpiresActive|ExpiresByType|DirectoryIndex|ServerName|DocumentRoot)\b~mi', $t ) && ! preg_match( '~[{};]\s*$~m', $t ) ) {
			return 'apache';
		}
		if ( ! preg_match( '~\bfunction\b|\$[A-Za-z_]\w*\s*(?:->|=[^=\~])~', $t ) && preg_match( '~\b(?:server|location|upstream|http|events)\b[^\n{;]*\{~', $t ) && preg_match( '~;\s*$~m', $t ) || preg_match( '~^[ \t]*(?:proxy_pass|proxy_set_header|server_name|listen|root|fastcgi_pass|add_header|return 30[12]|try_files|ssl_certificate)\b[^\n]*;\s*$~m', $t ) ) {
			return 'nginx';
		}
		$sql = preg_replace( '~\A(?:\s*(?:--[^\n]*|/\*[\s\S]*?\*/))+~', '', $t );
		if ( preg_match( '~^\s*CASE\s+WHEN\b~i', $sql ) ) {
			return 'sql';
		}
		if ( preg_match( '~^\s*(?:SELECT|WITH|INSERT\s+INTO|UPDATE\s+\S+\s+SET|DELETE\s+FROM|CREATE\s+(?:OR\s+REPLACE\s+)?(?:TABLE|VIEW|INDEX|DATABASE|USER|FUNCTION|TEMP|TEMPORARY|UNIQUE|MATERIALIZED)|ALTER\s+TABLE|DROP\s+(?:TABLE|VIEW|INDEX|DATABASE)|GRANT|SHOW\s+\w+|EXPLAIN|TRUNCATE)\b~i', $sql ) && preg_match( '~\b(?:FROM|TABLE|INTO|SET|WHERE|ON|TO|VIEW|INDEX|DATABASE|VALUES)\b~i', $t ) ) {
			return 'sql';
		}
		if ( preg_match( '~(?:^|\n)[ \t]*(?:\$|PS [A-Z]:\\\\[^>]*>)?[ \t]*(?:Get|Set|New|Remove|Invoke|Write|Start|Stop|Test|Add|Import|Export|Select|Where|ForEach)-[A-Z]\w+~', $t ) || preg_match( '~\$env:\w+~', $t ) ) {
			return 'powershell';
		}
		// PHP snippet without opening tag (WordPress hooks, $variables with -> or ;).
		if ( preg_match( '~\b(?:add_action|add_filter|add_shortcode|register_rest_route|do_action|apply_filters)\s*\(|\$wpdb\b|\bglobal\s+\$|\$this->|function\s+\w+\s*\([^)]*\$~', $t )
			|| ( preg_match( '~\$[A-Za-z_]\w*\s*(?:->|\[|=[^=])~', $t ) && preg_match( '~;\s*$~m', $t ) && ! preg_match( '~^\s*(?:\$ |sudo |#!)~m', $t ) && ! preg_match( '~\b(?:const|let|var)\s~', $t ) ) ) {
			return 'php';
		}
		if ( preg_match( '~^\s*"[\w$@.\-]+"\s*:~', $t ) && ! preg_match( '~;\s*$~m', $t ) ) {
			return 'json';
		}
		if ( preg_match( '~^[ \t]*(?:def \w+\(|class \w+[(:]|import \w[\w.]*(?:\s+as\s+\w+)?\s*$|from \w[\w.]* import\b|if __name__ ==|async def |@\w+(?:\.\w+)*\s*$)~m', $t ) ) {
			return 'python';
		}
		if ( preg_match( '~\b(?:const|let|var)\s+[\w${\[]|\bfunction\s*\w*\s*\(|=>|\bdocument\.|\bwindow\.|\bconsole\.|\bdataLayer\b|\bgtag\(|\b_paq\b|\.push\(\[|^[a-z_]\w*\.\w+\(\s*[\'"]|\bfetch\(|\brequire\(\s*[\'"]|\bmodule\.exports\b|\bexport\s+(?:default|const|function)\b|^import\s+.+\s+from\s+[\'"]~m', $t )
			&& ! preg_match( '~^\s*(?:\$ |sudo |apt )~m', $t ) ) {
			return preg_match( '~:\s*(?:string|number|boolean|void|any)\b|\binterface\s+\w+\s*\{|\btype\s+\w+\s*=~', $t ) ? 'typescript' : 'javascript';
		}
		if ( preg_match( '~^[^{}\n]+\{\s*\n?\s*[\w-]+\s*:[^;{}\n]+;~m', $t ) && preg_match( '~^\s*[.#@:\w\[*][^{}\n;]*\{~m', $t ) && ! preg_match( '~\b(?:function|return|if\s*\()~', $t ) ) {
			return 'css';
		}
		if ( preg_match( '~^\s*\[[\w .:"/\-]+\]\s*$~m', $t ) && preg_match( '~^\s*[\w.\-]+\s*=~m', $t ) ) {
			return 'ini';
		}

		$lines = array_values( array_filter( explode( "\n", $t ), 'trim' ) );
		$total = count( $lines );
		$code  = 0;
		$yaml  = 0;
		$keys  = 0;
		$kv    = 0;
		$shell = 0;
		$cmd   = '(?:\$ |sudo |apt(?:-get)? |dpkg |snap |curl |wget |cd |ls |cat |echo |grep |sed |awk |docker(?:-compose)? |git |systemctl |journalctl |npm |npx |pip3? |chmod |chown |mkdir |rm |cp |mv |export |ssh |scp |rsync |tar |wp |php |python3? |node |openssl |dig |nslookup |ping |crontab |ufw |iptables |nft |certbot |nginx |apache2ctl |a2en\w+ |service |kill |ps |tail |head |find |ln |touch |source |\. |mysql |mysqldump |redis-cli |composer |nano |vim? |ip |ss |df |du |free |uname |hostnamectl |useradd |usermod |passwd |su |gh |jq |ffmpeg |nvidia-smi |uv |vllm |ollama |make |kubectl |helm |terraform |ansible |bash |sh |zsh |for \w+ in |while |if \[|\./)';
		$cron    = '[\d*/,\-]+\s+[\d*/,\-]+\s+[\d*/,\-]+\s+[\d*/,\-]+\s+[\d*/,\-]+\s+\S';
		$options = '[a-z][\w.\-]*(?:\s+[^\s=]+)*?\s+--?[a-z][\w\-]*';
		$prev    = '';
		foreach ( $lines as $line ) {
			$comment = (bool) preg_match( '~^\s*(?:#|//)~', $line );
			if ( ! $comment ) {
				++$code;
			}
			if ( preg_match( '~^\s*(?:- )?[\w"\'][\w .\-/"\']*:(?:\s|$)~', $line ) ) {
				++$yaml;
				++$keys;
			} elseif ( preg_match( '~^\s*- \S~', $line ) || $comment ) {
				++$yaml;
			}
			if ( preg_match( '~^\s*(?:export\s+)?[A-Za-z_][\w.\-]*\s*=\s*\S~', $line ) || preg_match( '~^\s*[;#]~', $line ) ) {
				++$kv;
			}
			if ( ! $comment && ( preg_match( '~^\s*' . $cmd . '~', $line ) || preg_match( '~^\s*' . $cron . '~', $line ) || preg_match( '~^\s*' . $options . '~', $line ) || '\\' === substr( rtrim( $prev ), -1 ) ) ) {
				++$shell;
			}
			$prev = $line;
		}
		if ( $shell > 0 && $shell >= $code * 0.5 ) {
			return 'bash';
		}
		$top = preg_replace( '~\A(?:\s*#[^\n]*\n)+~', '', $t );
		if ( ( $total >= 2 && $keys >= 2 && $yaml >= $total * 0.85 && ! preg_match( '~[;{]\s*$~m', $t ) )
			|| ( $keys >= 3 && $yaml >= $total * 0.6 && preg_match( '~\A[\w"\'][\w .\-/"\']*:(?:[ \t]+\S[^\n]*)?\n~', $top ) ) ) {
			return 'yaml';
		}
		if ( $total >= 1 && $kv === $total && preg_match( '~=~', $t ) ) {
			return preg_match( '~\$\(|\becho\b|\bexport\b~', $t ) ? 'bash' : 'ini';
		}
		return 'plain';
	}
}
