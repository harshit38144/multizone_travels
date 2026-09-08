<?php
/**
 * Embed / tab-shell helpers for the admin multi-tab workspace.
 */

if (!function_exists('mz_is_embed_request')) {
	/**
	 * True when a page is loading inside a workspace iframe (chrome stripped).
	 * Uses mz_embed=1 only — do not confuse with lead_add.php?embed=1 modal partials.
	 */
	function mz_is_embed_request()
	{
		if (defined('MZ_EMBED') && MZ_EMBED) {
			return true;
		}
		if (defined('MZ_FORCE_EMBED') && MZ_FORCE_EMBED) {
			return true;
		}
		if (isset($_GET['mz_embed']) && (string) $_GET['mz_embed'] === '1') {
			return true;
		}
		// Tab panes load as iframes; modern browsers send this (same-origin admin only).
		$dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
		if ($dest === 'iframe') {
			$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
			if ($referer !== '' && preg_match('~/app\.php(?:[?#]|$)~', $referer)) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('mz_is_tab_shell')) {
	function mz_is_tab_shell()
	{
		return defined('MZ_TAB_SHELL') && MZ_TAB_SHELL;
	}
}

if (!function_exists('mz_embed_query')) {
	/** Query fragment to append so a URL loads as an embedded tab pane. */
	function mz_embed_query()
	{
		return 'mz_embed=1';
	}
}

// Always recompute from the current request (do not reuse a stale $mzEmbed).
$mzEmbed = mz_is_embed_request();
