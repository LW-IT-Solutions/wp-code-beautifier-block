=== Code Beautifier Block ===
Contributors: lwitsolutions
Tags: code, syntax highlighting, code block, snippet, block
Requires at least: 6.5
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A code block with server-side syntax highlighting that stays inside your layout, also with long lines and unbroken strings.

== Description ==

Code Beautifier Block adds one block for code. It was written for a site where every other code plugin failed in one of three ways: it broke the theme's layout, it formatted code badly, or it could not cope with long text without spaces such as URLs, hashes or base64 strings.

What it does differently:

* **Stays in its column.** The block never becomes wider than the content area, not even in flex or grid layouts. Long lines either wrap inside the block or scroll inside the block, never the page.
* **Breaks unbroken strings.** In wrap mode a 300-character URL or token breaks at any character instead of pushing the layout sideways.
* **Highlights on the server.** No highlighting library is loaded in the browser and nothing flickers after loading. The page contains finished markup.
* **Readable fallback.** The post content keeps a plain `<pre><code>` copy of the code. If the plugin is ever deactivated, the code is still shown.
* **Copy and wrap buttons.** Readers can copy the code (line numbers are never copied) and switch line wrapping per block. The result of copying appears in a status line below the code, which screen readers announce; the button keeps its label and the keyboard focus.
* **Light and dark.** Colours follow the visitor's system setting, or a `data-theme="light|dark"` attribute or `dark` class on the page. All colours are CSS custom properties, so a theme can adjust them.
* **Line numbers, start number and highlighted lines.** Line numbers appear automatically from three lines on and can be switched on or off per block.

Supported languages: Apache, Bash/Shell, C-like languages (C, C++, Java, Go, Rust), CSS, Diff, Dockerfile, HTML/XML, HTTP, INI/TOML/.env, JavaScript, JSON, Markdown, nginx, PHP (also mixed with HTML), PowerShell, Python, SQL, TypeScript and YAML. "Detect automatically" picks one of them or falls back to plain text.

Existing Code blocks, Preformatted blocks and Code Block Pro blocks can be converted with the block's "Transform" menu.

The plugin stores no options, sets no cookies and makes no external requests.

= Adjusting colours =

Set any of the custom properties on `.cbb` in your theme, for example:

`.cbb { --cbb-bg: #101416; --cbb-border: #2d3748; --cbb-radius: 4px; }`

Available: `--cbb-bg`, `--cbb-fg`, `--cbb-head-bg`, `--cbb-border`, `--cbb-muted`, `--cbb-gutter`, `--cbb-mark`, `--cbb-keyword`, `--cbb-string`, `--cbb-number`, `--cbb-function`, `--cbb-variable`, `--cbb-type`, `--cbb-attr`, `--cbb-tag`, `--cbb-constant`, `--cbb-comment`, `--cbb-meta`, `--cbb-font`, `--cbb-font-size`, `--cbb-radius`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the plugin directory.
2. Activate it.
3. In the block editor, add the block "Code Beautifier" and paste your code.

== Frequently Asked Questions ==

= Does it load JavaScript on every page? =

No. The script for the copy and wrap buttons (under 5 KB, no dependencies) is loaded only on pages that contain the block. Whether the stylesheet is limited to those pages as well depends on WordPress and the theme: block themes load it only where the block is used, while classic themes may load the stylesheets of all blocks, this one included, on every page.

= What happens with shortcodes inside a code sample? =

They are shown, not executed. Square brackets are written as character references in the output.

= In which language are the button texts? =

In the language of the post. On a normal page view that is the language of the site. With Polylang, the language of the post is used also when the post is rendered in another language, for example in a REST response for a user with a different admin language; only the translation of this plugin is read for it, nothing else changes language. The preview in the editor uses the language of the user. Other multilingual plugins can set it with the filter `code_beautifier_block_locale` (arguments: locale, post ID).

= Can I turn off wrapping by default? =

Yes, per block in the block settings ("Wrap long lines"). Readers can still switch it with the button above the code.

== Screenshots ==

1. A PHP sample in the dark colour scheme with a file name, line numbers and two highlighted lines.
2. Light mode: a long URL without spaces wraps inside the block instead of widening the page.

== Changelog ==

= 1.0.2 =
* The copy button keeps its label; the result is shown and announced in a status line below the code. If copying fails, the code is selected and keyboard focus stays on the button.
* The code area is a named region for screen readers ("Code example: …").
* Button texts follow the language of the post, with Polylang also in REST responses. New filter `code_beautifier_block_locale`.

= 1.0.1 =
* The readme says exactly when the script and the stylesheet are loaded.
* Code comments explain the escaping of the code text. The block output is unchanged.

= 1.0.0 =
* First release.
