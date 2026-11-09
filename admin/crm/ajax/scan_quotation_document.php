<?php
/**
 * Quotation Generator: upload itinerary / hotel / package document and extract structured fields.
 */

// Keep warnings out of the JSON body (display_errors otherwise breaks jQuery dataType:json).
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_doc_ocr.php';

function qdocJson(bool $ok, string $msg, array $extra = []): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        qdocJson(false, 'Invalid request method.');
    }

    if (!qdocAvailable()) {
        qdocJson(false, 'AI document reading is not enabled. Configure the Gemini key in crm/includes/ai_config.local.php.');
    }

    $file = $_FILES['document'] ?? ($_FILES['file'] ?? null);
    if (empty($_FILES) || !is_array($file) || !isset($file['tmp_name'])) {
        // Empty $_FILES often means the upload exceeded post_max_size.
        if (empty($_FILES)) {
            $postMax = ini_get('post_max_size');
            qdocJson(false, 'No file was received. The file may exceed the server limit (post_max_size=' . $postMax . '). Try a smaller PDF or image.');
        }
        qdocJson(false, 'Choose an image, PDF, or Word document to upload.');
    }

    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'The file is larger than the server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose an image, PDF, or Word document to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
    ];
    $errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errCode !== UPLOAD_ERR_OK) {
        qdocJson(false, $uploadErrors[$errCode] ?? 'Upload failed. Please try again.');
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        qdocJson(false, 'Upload failed. Please try again.');
    }

    $origName = (string) ($file['name'] ?? 'document');
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '' || $ext === 'blob') {
        $ext = 'pdf';
    }
    if (!in_array($ext, qdocAllowedExtensions(), true)) {
        qdocJson(false, 'Supported files: JPG, JPEG, PNG, PDF, DOC, DOCX.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        qdocJson(false, 'The uploaded file is empty.');
    }
    if ($size > qdocMaxBytes()) {
        qdocJson(false, 'File size exceeds ' . qdocMaxMbLabel() . ' MB (server limit). Please use a smaller file or raise PHP upload_max_filesize/post_max_size.');
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
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }
    if ($mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }
    if (!in_array($mime, qdocAllowedMimes(), true) && !in_array($ext, qdocAllowedExtensions(), true)) {
        qdocJson(false, 'That file type is not supported. Use JPG, PNG, PDF, DOC, or DOCX.');
    }

    @ini_set('memory_limit', '512M');
    @ini_set('max_execution_time', '300');
    @set_time_limit(300);

    $ocr = qdocExtract((string) $file['tmp_name'], $mime, $ext);
    if (empty($ocr['ok'])) {
        $err = trim((string) ($ocr['error'] ?? ''));
        if ($err === '') {
            $err = 'The document could not be read. Please try a clearer PDF or image.';
        }
        qdocJson(false, $err);
    }

    $result = $ocr['result'];
    $hasAny = !empty($result['itinerary'])
        || !empty($result['hotels'])
        || !empty($result['inclusions'])
        || !empty($result['exclusions']);

    if (!$hasAny) {
        qdocJson(true, 'No itinerary, hotel, inclusion, or exclusion details could be read. Fill the sections manually.', [
            'data' => $result,
            'file_name' => $origName,
        ]);
    }

    qdocJson(true, 'Document details were read. Review and edit them before applying to the quotation.', [
        'data' => $result,
        'file_name' => $origName,
        'model' => (string) ($ocr['model'] ?? ''),
    ]);
} catch (Throwable $e) {
    qdocJson(false, 'Document reading failed: ' . $e->getMessage());
}
