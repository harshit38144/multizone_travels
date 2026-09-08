<?php

/**
 * Admin path bootstrap — ADMIN_WEB_ROOT + admin_url() for assets and links.
 * Loaded by connection.php (and may be included directly where DB is not needed).
 */
if (defined('ADMIN_FS_ROOT')) {
	return;
}

define('ADMIN_FS_ROOT', str_replace('\\', '/', dirname(__FILE__)));

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
$docRootReal = $docRoot !== '' ? realpath($docRoot) : false;
$adminReal = realpath(dirname(__FILE__));

$docNorm = $docRootReal ? str_replace('\\', '/', $docRootReal) : '';
$adminPath = $adminReal ? str_replace('\\', '/', $adminReal) : ADMIN_FS_ROOT;

if ($docNorm !== '' && $adminPath !== '' && strpos($adminPath, $docNorm) === 0) {
	$rel = (string) substr($adminPath, strlen($docNorm));
	$rel = rtrim(str_replace('\\', '/', $rel), '/');
	// '' when admin/ IS the vhost document root (e.g. admin.multizonetravels.com)
	define('ADMIN_WEB_ROOT', $rel === '' ? '' : ('/' . ltrim($rel, '/')));
} else {
	define('ADMIN_WEB_ROOT', '/admin');
}

if (!function_exists('admin_url')) {
	function admin_url($path = '')
	{
		$base = defined('ADMIN_WEB_ROOT') ? ADMIN_WEB_ROOT : '/admin';
		$path = ltrim((string) $path, '/');
		if ($path === '') {
			return $base === '' ? '/' : $base;
		}
		if ($base === '' || $base === '/') {
			return '/' . $path;
		}
		return $base . '/' . $path;
	}
}
