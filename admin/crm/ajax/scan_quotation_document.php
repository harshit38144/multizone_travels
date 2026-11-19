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

    $stage = 'extracting';
    $ocr = qdocExtract((string) $file['tmp_name'], $mime, $ext);
    if (empty($ocr['ok'])) {
        $err = trim((string) ($ocr['error'] ?? ''));
        if ($err === '') {
            $err = 'The document could not be read. Please try a clearer PDF or image.';
        }
        qdocJson(false, $err, [
            'stage' => $stage,
            'http_code' => (int) ($ocr['http_code'] ?? 0),
            'elapsed_ms' => (int) ($ocr['elapsed_ms'] ?? 0),
        ]);
    }

    $stage = 'validating';
    $result = is_array($ocr['result'] ?? null) ? $ocr['result'] : [];
    // Local validation/normalize only — extract prompt already polishes language in one AI pass.
    // A second Gemini round-trip was the main cause of the long "90%" wait.
    if (!empty($result['itinerary']) && is_array($result['itinerary'])) {
        foreach ($result['itinerary'] as $i => $day) {
            if (!is_array($day)) {
                continue;
            }
            $result['itinerary'][$i]['description'] = qdocSanitizeDayHtml((string) ($day['description'] ?? ''));
        }
    }

    if (!qdocHasContent($result)) {
        qdocJson(true, 'No itinerary, hotel, inclusion, or exclusion details could be read. Fill the sections manually.', [
            'data' => $result,
            'file_name' => $origName,
            'stage' => 'extracting',
            'rephrased' => false,
            'model' => (string) ($ocr['model'] ?? ''),
            'elapsed_ms' => (int) ($ocr['elapsed_ms'] ?? 0),
        ]);
    }

    // Extract prompt already polishes in one multimodal call (`polished` flag).
    // Keep a lean text-only rephrase only if that flag is missing (older/partial extract path).
    $rephrased = !empty($ocr['polished']);
    $rephraseElapsed = 0;
    $rephraseModel = '';
    if (!$rephrased) {
        $stage = 'rephrasing';
        $rephrase = qdocRephraseExtracted($result);
        $rephraseElapsed = (int) ($rephrase['elapsed_ms'] ?? 0);
        if (!empty($rephrase['rephrased']) && !empty($rephrase['result']) && is_array($rephrase['result']) && qdocHasContent($rephrase['result'])) {
            $result = $rephrase['result'];
            $rephrased = true;
            $rephraseModel = (string) ($rephrase['model'] ?? '');
        }
    }

    $stage = 'ready';
    $msg = $rephrased
        ? 'Document details were extracted and professionally rephrased. Review and edit them before applying.'
        : 'Document details were read. Review and edit them before applying.';

    qdocJson(true, $msg, [
        'data' => $result,
        'file_name' => $origName,
        'model' => (string) ($rephraseModel !== '' ? $rephraseModel : ($ocr['model'] ?? '')),
        'extract_model' => (string) ($ocr['model'] ?? ''),
        'rephrased' => $rephrased,
        'stage' => $stage,
        'elapsed_ms' => (int) ($ocr['elapsed_ms'] ?? 0) + $rephraseElapsed,
    ]);
} catch (Throwable $e) {
    if (function_exists('crmAiLog')) {
        crmAiLog('qdoc_endpoint_exception', ['error' => $e->getMessage()]);
    }
    qdocJson(false, 'Document reading failed. Please try again with a clearer PDF or image.');
}
