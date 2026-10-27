<?php
/**
 * Hotel Details image OCR. Returns rows for the user to review before they are applied.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_ocr.php';

header('Content-Type: application/json; charset=utf-8');

function hocrJson(bool $ok, string $msg, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hocrJson(false, 'Invalid request method.');
}

$file = $_FILES['image'] ?? null;
if (!is_array($file) || !isset($file['tmp_name'])) {
    hocrJson(false, 'Paste or choose a hotel quotation image.');
}

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE => 'The image is larger than the server upload limit.',
    UPLOAD_ERR_FORM_SIZE => 'The image is too large.',
    UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
    UPLOAD_ERR_NO_FILE => 'Paste or choose a hotel quotation image.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is missing.',
    UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded image.',
];
$errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errCode !== UPLOAD_ERR_OK) {
    hocrJson(false, $uploadErrors[$errCode] ?? 'Upload failed. Please try again.');
}
if (!is_uploaded_file((string) $file['tmp_name'])) {
    hocrJson(false, 'Upload failed. Please try again.');
}

$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if ($ext === '' || $ext === 'blob') {
    $ext = 'png';
}
if (!in_array($ext, hocrAllowedExtensions(), true)) {
    hocrJson(false, 'Only JPG, PNG, or WEBP images can be read.');
}
$size = (int) ($file['size'] ?? 0);
if ($size <= 0) {
    hocrJson(false, 'The image is empty.');
}
if ($size > hocrMaxBytes()) {
    hocrJson(false, 'Image size exceeds ' . (int) (hocrMaxBytes() / (1024 * 1024)) . ' MB. Please use a smaller screenshot.');
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = (string) finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
}
if ($mime === 'image/jpg') {
    $mime = 'image/jpeg';
}
if (!in_array($mime, hocrAllowedMimes(), true)) {
    hocrJson(false, 'That file is not a supported image. Use JPG, PNG, or WEBP.');
}

@ini_set('memory_limit', '256M');
@set_time_limit(150);

$ocr = hocrExtract((string) $file['tmp_name'], $mime);
if (empty($ocr['ok'])) {
    hocrJson(false, (string) ($ocr['error'] ?? 'The hotel image could not be read.'));
}

$result = $ocr['result'];
if (empty($result['is_hotel_quotation']) || empty($result['hotels'])) {
    hocrJson(true, 'No hotel lines could be read from this image. The fields were left empty so you can fill them manually.', [
        'hotels' => [],
    ]);
}

hocrJson(true, 'Hotel details were read. Review and edit them before adding to the quotation.', [
    'hotels' => $result['hotels'],
]);
