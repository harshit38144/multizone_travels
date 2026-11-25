<?php
/**
 * One-time HTML render endpoint for Chrome headless print-to-PDF.
 * No session cookie required — access is gated by a short-lived file token.
 * Does not expose private PDFs; only serves temporary print HTML.
 */
require_once __DIR__ . '/../includes/quotation_pdf.php';

$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['t'] ?? ''));
if (strlen($token) < 16 || strlen($token) > 64) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid token.');
}

$path = crmQuotationPdfRenderHtmlPath($token);
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Render HTML not found or expired.');
}

$mtime = (int) @filemtime($path);
if ($mtime > 0 && (time() - $mtime) > 180) {
    @unlink($path);
    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Render HTML expired.');
}

$html = (string) @file_get_contents($path);
if ($html === '') {
    http_response_code(404);
    exit('Empty render HTML.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $html;
// Keep file until Chrome finishes; cleaned up by generator after print.
exit;
