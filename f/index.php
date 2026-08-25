<?php
/**
 * Short intake form router: /f/{code}
 * Falls back to ?token= for safety; supports PATH_INFO and REQUEST_URI parsing.
 */

$code = trim((string) ($_GET['token'] ?? $_GET['c'] ?? ''));

if ($code === '' && !empty($_SERVER['PATH_INFO'])) {
    $parts = array_values(array_filter(explode('/', trim((string) $_SERVER['PATH_INFO'], '/'))));
    $code = $parts[0] ?? '';
}

if ($code === '') {
    $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    $uri = strtok($uri, '?') ?: $uri;
    if (preg_match('#/f/([A-Za-z0-9_-]+)(?:/thanks)?/?$#', $uri, $m)) {
        $code = $m[1];
    }
}

$code = trim($code);
if ($code === '' || !preg_match('/^[A-Za-z0-9_-]{6,64}$/', $code)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or missing form link.';
    exit;
}

$_GET['token'] = $code;
require __DIR__ . '/../public/lead_intake.php';
