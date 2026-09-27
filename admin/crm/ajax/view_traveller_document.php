<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/traveller_documents.php';
require_once __DIR__ . '/../includes/service_vouchers.php';

$isVoucher = (($_GET['kind'] ?? '') === 'voucher');
if ($isVoucher) {
    svEnsureTable($conn);
} else {
    tdEnsureTable($conn);
}

$id = (int) ($_GET['id'] ?? 0);
$wantThumb = !$isVoucher && !empty($_GET['thumb']);

$row = null;
if ($id > 0) {
    $stmt = $conn->prepare($isVoucher
        ? 'SELECT * FROM `crm_service_vouchers` WHERE `id` = ? LIMIT 1'
        : 'SELECT * FROM `crm_traveller_documents` WHERE `id` = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}
if (!$row) {
    http_response_code(404);
    exit('Document not found.');
}

$rel = $wantThumb && trim((string) ($row['thumb_path'] ?? '')) !== '' ? (string) $row['thumb_path'] : (string) $row['file_path'];
$abs = tdAbsolutePath($rel);
if (!is_file($abs)) {
    http_response_code(404);
    exit('File missing.');
}

$mime = $wantThumb && $rel !== $row['file_path']
    ? ((new finfo(FILEINFO_MIME_TYPE))->file($abs) ?: 'image/webp')
    : ((string) $row['mime_type'] ?: 'application/octet-stream');
$allowedMime = ['image/webp', 'image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($mime, $allowedMime, true)) {
    http_response_code(415);
    exit('Unsupported file.');
}

$etag = '"' . ($isVoucher ? 'sv' : 'td') . (int) $row['id'] . '-' . filemtime($abs) . ($wantThumb ? 't' : '') . (!empty($_GET['download']) ? 'd' : '') . '"';
// session_start() adds no-cache headers; previews are safe to cache per user (URL is versioned).
header_remove('Pragma');
header_remove('Expires');
header('Cache-Control: private, max-age=604800');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

$downloadName = preg_replace('/[^A-Za-z0-9._\-]/', '_', (string) $row['file_name']);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Content-Disposition: ' . (!empty($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $downloadName . '"');
readfile($abs);
