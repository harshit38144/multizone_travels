<?php
/**
 * E-Ticket AI auto-fill endpoint: reads an uploaded e-ticket and returns the details
 * for the E-Ticket form to pre-fill. The file is sent to the AI inline and never stored.
 */

require_once __DIR__ . '/../includes/eticket_ocr.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function etsJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['role']) || (string) $_SESSION['role'] !== '1') {
    http_response_code(401);
    etsJson(false, 'Session expired. Please sign in again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    etsJson(false, 'Invalid request method.');
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['tmp_name'])) {
    etsJson(false, 'Please choose an e-ticket file to upload.');
}

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE => 'The file is larger than the server upload limit.',
    UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
    UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
    UPLOAD_ERR_NO_FILE => 'Please choose an e-ticket file to upload.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is missing.',
    UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
];
$errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errCode !== UPLOAD_ERR_OK) {
    etsJson(false, $uploadErrors[$errCode] ?? 'Upload failed. Please try again.');
}
if (!is_uploaded_file((string) $file['tmp_name'])) {
    etsJson(false, 'Upload failed. Please try again.');
}

$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($ext, etocrAllowedExtensions(), true)) {
    etsJson(false, 'Only JPG, JPEG, PNG, WEBP or PDF e-tickets are allowed.');
}
$size = (int) ($file['size'] ?? 0);
if ($size <= 0) {
    etsJson(false, 'The uploaded file is empty.');
}
if ($size > etocrMaxBytes()) {
    etsJson(false, 'File size exceeds ' . (int) (etocrMaxBytes() / (1024 * 1024)) . ' MB. Please upload a smaller file.');
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string) finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mime === '') {
    $mime = $ext === 'pdf' ? 'application/pdf' : ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
}
if ($mime === 'image/jpg') {
    $mime = 'image/jpeg';
}
if (!in_array($mime, etocrAllowedMimes(), true)) {
    etsJson(false, 'That file type is not supported. Upload a JPG, PNG, WEBP or PDF e-ticket.');
}

@ini_set('memory_limit', '256M');
@set_time_limit(150);

$ocr = etocrExtract((string) $file['tmp_name'], $mime);
if (empty($ocr['ok'])) {
    etsJson(false, (string) ($ocr['error'] ?? 'The e-ticket could not be read.'));
}

$result = $ocr['result'];
$extra = ['ticket' => $result, 'model' => (string) ($ocr['model'] ?? '')];

if (!$result['is_eticket']) {
    etsJson(true, 'That file does not look like a flight e-ticket. Please check the file or fill the details manually.', $extra);
}
if (!$result['filled']) {
    etsJson(true, 'No details could be read from this file. Try a clearer image or fill the details manually.', $extra);
}
etsJson(true, 'E-ticket read successfully. Please review the details before saving.', $extra);
