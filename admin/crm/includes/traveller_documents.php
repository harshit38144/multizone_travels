<?php
/**
 * Confirm Tour → Traveller Attachments: 6 fixed document slots per traveller,
 * compressed on upload (GD → WEBP for images, Ghostscript for PDFs) and served
 * only through an authenticated endpoint.
 */

const TD_MAX_UPLOAD_BYTES = 4 * 1024 * 1024;
const TD_IMAGE_MAX_WIDTH = 1400;
const TD_IMAGE_MAX_HEIGHT = 2000;
const TD_TARGET_MAX_BYTES = 350 * 1024;
const TD_THUMB_WIDTH = 240;

function tdDocumentTypes(): array
{
    return [
        'profile_photo' => ['label' => 'Profile Photo', 'icon' => 'fa-user-circle', 'pdf' => false],
        'aadhaar' => ['label' => 'Aadhaar Card', 'icon' => 'fa-id-card', 'pdf' => true],
        'pan' => ['label' => 'PAN Card', 'icon' => 'fa-address-card', 'pdf' => true],
        'passport' => ['label' => 'Passport', 'icon' => 'fa-passport', 'pdf' => true],
        'visa' => ['label' => 'Visa', 'icon' => 'fa-stamp', 'pdf' => true],
        'other' => ['label' => 'Other Document', 'icon' => 'fa-file-alt', 'pdf' => true],
    ];
}

function tdEnsureTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_traveller_documents` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `traveller_id` VARCHAR(80) NOT NULL,
        `quotation_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `lead_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `document_type` VARCHAR(30) NOT NULL,
        `file_name` VARCHAR(190) NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `thumb_path` VARCHAR(255) DEFAULT NULL,
        `mime_type` VARCHAR(80) NOT NULL DEFAULT '',
        `original_size_kb` INT UNSIGNED NOT NULL DEFAULT 0,
        `file_size_kb` INT UNSIGNED NOT NULL DEFAULT 0,
        `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_traveller_doc` (`traveller_id`, `document_type`),
        KEY `idx_td_quotation` (`quotation_id`),
        KEY `idx_td_lead` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Contact-linked travellers (primary_{lead}, family_{id}) keep their documents across tours;
 * ad-hoc travellers are scoped to the quotation.
 */
function tdTravellerKey(int $quotationId, string $travellerId): string
{
    $travellerId = trim($travellerId);
    if (preg_match('/^(primary|family)_\d+$/', $travellerId)) {
        return $travellerId;
    }
    $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $travellerId);
    return 'q' . $quotationId . '_' . substr($clean, 0, 60);
}

/** Private storage — blocked from direct web access by .htaccess; served via view_traveller_document.php. */
function tdStorageDir(): string
{
    $dir = dirname(__DIR__, 2) . '/uploads/private_traveller_docs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\nOptions -Indexes\n");
    }
    if (!is_file($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function tdAbsolutePath(string $relative): string
{
    return tdStorageDir() . '/' . basename($relative);
}

function tdDeleteFiles(?array $row): void
{
    if (!$row) {
        return;
    }
    foreach (['file_path', 'thumb_path'] as $col) {
        $rel = trim((string) ($row[$col] ?? ''));
        if ($rel !== '') {
            $abs = tdAbsolutePath($rel);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }
}

function tdSanitizeBaseName(string $name): string
{
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $base);
    $base = trim((string) $base, '_-');
    return $base !== '' ? substr($base, 0, 80) : 'document';
}

/**
 * @return array{ok: bool, message?: string, ext?: string, mime?: string}
 */
function tdValidateUpload(array $file, string $type): array
{
    $types = tdDocumentTypes();
    if (!isset($types[$type])) {
        return ['ok' => false, 'message' => 'Invalid document type.'];
    }
    if (empty($file['tmp_name']) || !empty($file['error']) || !is_uploaded_file($file['tmp_name'])) {
        $err = (int) ($file['error'] ?? 0);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'message' => 'File size exceeds 4 MB. Please upload a smaller file.'];
        }
        return ['ok' => false, 'message' => 'Please choose a file to upload.'];
    }
    if ((int) $file['size'] > TD_MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'message' => 'File size exceeds 4 MB. Please upload a smaller file.'];
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    if ($types[$type]['pdf']) {
        $allowedExt[] = 'pdf';
    }
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'message' => $types[$type]['pdf']
            ? 'Only JPG, JPEG, PNG, WEBP or PDF files are allowed.'
            : 'Only JPG, JPEG, PNG or WEBP images are allowed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    $mimeByExt = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
    ];
    if (!in_array($mime, $mimeByExt[$ext], true)) {
        return ['ok' => false, 'message' => 'File content does not match its extension.'];
    }
    if ($mime === 'application/pdf') {
        $head = (string) @file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-') {
            return ['ok' => false, 'message' => 'Invalid PDF file.'];
        }
    } elseif (@getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'message' => 'Invalid image file.'];
    }
    return ['ok' => true, 'ext' => $ext, 'mime' => $mime];
}

function tdLoadImage(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($path);
            if ($img && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                $orientation = (int) ($exif['Orientation'] ?? 1);
                $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
                if ($angle !== 0) {
                    $rotated = imagerotate($img, $angle, 0);
                    if ($rotated) {
                        imagedestroy($img);
                        $img = $rotated;
                    }
                }
            }
            return $img;
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    return false;
}

function tdResizeImage($img, int $maxW, int $maxH)
{
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxW / $w, $maxH / $h);
    if ($scale >= 1) {
        return $img;
    }
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    return $dst;
}

function tdImageHasAlpha($img): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    $step = max(1, (int) floor(min($w, $h) / 40));
    for ($x = 0; $x < $w; $x += $step) {
        for ($y = 0; $y < $h; $y += $step) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                return true;
            }
        }
    }
    return false;
}

function tdFlattenOnWhite($img)
{
    $w = imagesx($img);
    $h = imagesy($img);
    $bg = imagecreatetruecolor($w, $h);
    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
    imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);
    imagedestroy($img);
    return $bg;
}

/**
 * Re-encode an image (also strips EXIF/metadata and any embedded payload).
 * WEBP q70 when available (JPEG q75 fallback, lossless PNG when transparency must be kept),
 * lowering quality and then dimensions until the output is under ~350 KB.
 *
 * @return array{ok: bool, message?: string, path?: string, ext?: string, mime?: string, bytes?: int, width?: int}
 */
function tdCompressImage(string $src, string $mime, string $destNoExt, int $maxW = TD_IMAGE_MAX_WIDTH, int $maxH = TD_IMAGE_MAX_HEIGHT, int $targetBytes = TD_TARGET_MAX_BYTES): array
{
    if (!function_exists('imagecreatetruecolor')) {
        return ['ok' => false, 'message' => 'Image processing (GD) is not available on the server.'];
    }
    $img = tdLoadImage($src, $mime);
    if (!$img) {
        return ['ok' => false, 'message' => 'Could not read the image.'];
    }
    if (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $img = tdResizeImage($img, $maxW, $maxH);

    $webp = function_exists('imagewebp');
    $hasAlpha = $mime !== 'image/jpeg' && tdImageHasAlpha($img);

    if ($webp) {
        $ext = 'webp';
        $outMime = 'image/webp';
        $qualities = [70, 62, 55, 48, 40];
    } elseif ($hasAlpha) {
        $ext = 'png';
        $outMime = 'image/png';
        $qualities = [9];
    } else {
        $ext = 'jpg';
        $outMime = 'image/jpeg';
        $qualities = [75, 68, 60, 52, 45];
        $img = tdFlattenOnWhite($img);
    }
    $dest = $destNoExt . '.' . $ext;

    for ($pass = 0; $pass < 4; $pass++) {
        foreach ($qualities as $q) {
            if ($ext === 'webp') {
                imagewebp($img, $dest, $q);
            } elseif ($ext === 'png') {
                imagepng($img, $dest, $q);
            } else {
                imageinterlace($img, true);
                imagejpeg($img, $dest, $q);
            }
            clearstatcache(true, $dest);
            $bytes = (int) @filesize($dest);
            if ($bytes > 0 && $bytes <= $targetBytes) {
                break 2;
            }
        }
        $w = imagesx($img);
        if ($w <= 480) {
            break;
        }
        $img = tdResizeImage($img, (int) round($w * 0.8), PHP_INT_MAX);
    }
    $width = imagesx($img);
    imagedestroy($img);

    clearstatcache(true, $dest);
    $bytes = (int) @filesize($dest);
    if ($bytes <= 0) {
        @unlink($dest);
        return ['ok' => false, 'message' => 'Could not compress the image.'];
    }
    return ['ok' => true, 'path' => $dest, 'ext' => $ext, 'mime' => $outMime, 'bytes' => $bytes, 'width' => $width];
}

function tdGhostscriptBinary(): string
{
    static $bin = null;
    if ($bin !== null) {
        return $bin;
    }
    $bin = '';
    if (!function_exists('exec')) {
        return $bin;
    }
    $candidates = [];
    $env = getenv('GHOSTSCRIPT_BIN');
    if ($env) {
        $candidates[] = $env;
    }
    if (DIRECTORY_SEPARATOR === '\\') {
        foreach (['C:/Program Files/gs/*/bin/gswin64c.exe', 'C:/Program Files (x86)/gs/*/bin/gswin32c.exe'] as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                $candidates[] = $path;
            }
        }
        $candidates[] = 'gswin64c';
    } else {
        $candidates = array_merge($candidates, ['/usr/bin/gs', '/usr/local/bin/gs', 'gs']);
    }
    foreach ($candidates as $candidate) {
        $out = [];
        $code = 1;
        @exec(escapeshellarg($candidate) . ' --version 2>&1', $out, $code);
        if ($code === 0 && !empty($out) && preg_match('/^\d+\.\d+/', trim((string) $out[0]))) {
            $bin = $candidate;
            break;
        }
    }
    return $bin;
}

/**
 * Rewrites the PDF with Ghostscript (/ebook: images downsampled to 150 dpi, fonts subset,
 * duplicate images merged, document metadata dropped). Falls back to the original bytes
 * when Ghostscript is unavailable or would not make the file smaller.
 *
 * @return array{ok: bool, message?: string, path?: string, bytes?: int, compressed?: bool}
 */
function tdCompressPdf(string $src, string $destNoExt): array
{
    $dest = $destNoExt . '.pdf';
    $srcBytes = (int) filesize($src);
    $gs = tdGhostscriptBinary();
    if ($gs !== '') {
        $tmp = $destNoExt . '.gs.pdf';
        $cmd = escapeshellarg($gs)
            . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook'
            . ' -dNOPAUSE -dQUIET -dBATCH -dSAFER'
            . ' -dDetectDuplicateImages=true -dCompressFonts=true -dSubsetFonts=true'
            . ' -dDownsampleColorImages=true -dColorImageResolution=150'
            . ' -dDownsampleGrayImages=true -dGrayImageResolution=150'
            . ' -dDownsampleMonoImages=true -dMonoImageResolution=300'
            . ' -sOutputFile=' . escapeshellarg($tmp) . ' ' . escapeshellarg($src) . ' 2>&1';
        $out = [];
        $code = 1;
        @exec($cmd, $out, $code);
        clearstatcache(true, $tmp);
        $tmpBytes = is_file($tmp) ? (int) filesize($tmp) : 0;
        if ($code === 0 && $tmpBytes > 0 && $tmpBytes < $srcBytes) {
            rename($tmp, $dest);
            return ['ok' => true, 'path' => $dest, 'bytes' => $tmpBytes, 'compressed' => true];
        }
        if (is_file($tmp)) {
            @unlink($tmp);
        }
    }
    if (!@copy($src, $dest)) {
        return ['ok' => false, 'message' => 'Could not store the PDF.'];
    }
    return ['ok' => true, 'path' => $dest, 'bytes' => $srcBytes, 'compressed' => false];
}

function tdGetDocument(mysqli $conn, string $travellerKey, string $type): ?array
{
    $stmt = $conn->prepare('SELECT * FROM `crm_traveller_documents` WHERE `traveller_id` = ? AND `document_type` = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $travellerKey, $type);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function tdListDocuments(mysqli $conn, string $travellerKey): array
{
    $rows = [];
    $stmt = $conn->prepare('SELECT * FROM `crm_traveller_documents` WHERE `traveller_id` = ?');
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('s', $travellerKey);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    $order = array_flip(array_keys(tdDocumentTypes()));
    usort($rows, function ($a, $b) use ($order) {
        return ($order[$a['document_type']] ?? 99) <=> ($order[$b['document_type']] ?? 99);
    });
    return $rows;
}

function tdPublicDocument(array $row): array
{
    $types = tdDocumentTypes();
    $v = strtotime((string) $row['uploaded_at']) ?: time();
    $url = 'crm/ajax/view_traveller_document.php?id=' . (int) $row['id'] . '&v=' . $v;
    return [
        'id' => (int) $row['id'],
        'document_type' => (string) $row['document_type'],
        'label' => $types[$row['document_type']]['label'] ?? (string) $row['document_type'],
        'file_name' => (string) $row['file_name'],
        'file_size_kb' => (int) $row['file_size_kb'],
        'original_size_kb' => (int) $row['original_size_kb'],
        'mime_type' => (string) $row['mime_type'],
        'is_image' => strpos((string) $row['mime_type'], 'image/') === 0,
        'uploaded_at' => (string) $row['uploaded_at'],
        'url' => $url,
        'thumb_url' => trim((string) ($row['thumb_path'] ?? '')) !== '' ? $url . '&thumb=1' : null,
    ];
}

/** Traveller `documents` summary stored in tour_confirm_json (drives the "N files" chip). */
function tdDocumentsSummary(mysqli $conn, string $travellerKey): array
{
    $out = [];
    foreach (tdListDocuments($conn, $travellerKey) as $row) {
        $pub = tdPublicDocument($row);
        $out[] = ['name' => $pub['label'] . ' — ' . $pub['file_name'], 'path' => $pub['url'], 'type' => $pub['document_type']];
    }
    return $out;
}

/**
 * Compress + store a validated upload in private storage and upsert it into the traveller's slot
 * (replacing any previous file of the same type).
 *
 * @param array{ok: bool, ext?: string, mime?: string} $check result of tdValidateUpload()
 * @return array{ok: bool, message?: string, document?: array|null, pdf_compressed?: bool|null}
 */
function tdStoreUpload(mysqli $conn, string $travellerKey, int $quotationId, int $leadId, string $type, array $file, array $check): array
{
    $dir = tdStorageDir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return ['ok' => false, 'message' => 'Upload folder is not writable.'];
    }
    $token = $type . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
    $destNoExt = $dir . '/' . $token;
    $originalKb = (int) max(1, ceil(((int) $file['size']) / 1024));

    $thumbRel = null;
    if ($check['mime'] === 'application/pdf') {
        $result = tdCompressPdf($file['tmp_name'], $destNoExt);
        $outExt = 'pdf';
        $outMime = 'application/pdf';
    } else {
        $result = tdCompressImage($file['tmp_name'], $check['mime'], $destNoExt);
        $outExt = $result['ext'] ?? 'webp';
        $outMime = $result['mime'] ?? 'image/webp';
        if ($result['ok'] && $type === 'profile_photo') {
            $thumb = tdCompressImage($result['path'], $outMime, $destNoExt . '_thumb', TD_THUMB_WIDTH, TD_THUMB_WIDTH, 40 * 1024);
            if ($thumb['ok']) {
                $thumbRel = basename($thumb['path']);
            }
        }
    }
    if (!$result['ok']) {
        return ['ok' => false, 'message' => $result['message'] ?? 'Could not process the file.'];
    }

    $fileRel = basename($result['path']);
    $sizeKb = (int) max(1, ceil($result['bytes'] / 1024));
    $displayName = tdSanitizeBaseName((string) $file['name']) . '.' . $outExt;

    $previous = tdGetDocument($conn, $travellerKey, $type);
    $stmt = $conn->prepare(
        'INSERT INTO `crm_traveller_documents`
            (`traveller_id`, `quotation_id`, `lead_id`, `document_type`, `file_name`, `file_path`, `thumb_path`,
             `mime_type`, `original_size_kb`, `file_size_kb`, `uploaded_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            `quotation_id` = VALUES(`quotation_id`), `lead_id` = VALUES(`lead_id`),
            `file_name` = VALUES(`file_name`), `file_path` = VALUES(`file_path`), `thumb_path` = VALUES(`thumb_path`),
            `mime_type` = VALUES(`mime_type`), `original_size_kb` = VALUES(`original_size_kb`),
            `file_size_kb` = VALUES(`file_size_kb`), `uploaded_at` = NOW()'
    );
    if (!$stmt) {
        tdDeleteFiles(['file_path' => $fileRel, 'thumb_path' => $thumbRel]);
        return ['ok' => false, 'message' => 'Could not save document.'];
    }
    $stmt->bind_param(
        'siisssssii',
        $travellerKey, $quotationId, $leadId, $type, $displayName, $fileRel, $thumbRel, $outMime, $originalKb, $sizeKb
    );
    if (!$stmt->execute()) {
        $stmt->close();
        tdDeleteFiles(['file_path' => $fileRel, 'thumb_path' => $thumbRel]);
        return ['ok' => false, 'message' => 'Could not save document.'];
    }
    $stmt->close();

    // Replace: remove the previous files only after the new record is stored.
    if ($previous && $previous['file_path'] !== $fileRel) {
        tdDeleteFiles($previous);
    }

    $saved = tdGetDocument($conn, $travellerKey, $type);
    return [
        'ok' => true,
        'document' => $saved ? tdPublicDocument($saved) : null,
        'pdf_compressed' => $outMime === 'application/pdf' ? (bool) ($result['compressed'] ?? false) : null,
    ];
}

/**
 * Move documents when a traveller gets linked to a contact (t_xxx → family_N, guest → primary_N).
 * A newer upload on the draft replaces the contact's existing file of the same type.
 */
function tdRekeyTraveller(mysqli $conn, string $fromKey, string $toKey): void
{
    if ($fromKey === '' || $toKey === '' || $fromKey === $toKey) {
        return;
    }
    foreach (tdListDocuments($conn, $fromKey) as $doc) {
        $existing = tdGetDocument($conn, $toKey, (string) $doc['document_type']);
        if ($existing) {
            tdDeleteDocumentRow($conn, $existing);
        }
    }
    $stmt = $conn->prepare('UPDATE IGNORE `crm_traveller_documents` SET `traveller_id` = ? WHERE `traveller_id` = ?');
    if ($stmt) {
        $stmt->bind_param('ss', $toKey, $fromKey);
        $stmt->execute();
        $stmt->close();
    }
}

function tdDeleteDocumentRow(mysqli $conn, array $row): void
{
    $del = $conn->prepare('DELETE FROM `crm_traveller_documents` WHERE `id` = ? LIMIT 1');
    if ($del) {
        $docId = (int) $row['id'];
        $del->bind_param('i', $docId);
        $del->execute();
        $del->close();
    }
    tdDeleteFiles($row);
}
