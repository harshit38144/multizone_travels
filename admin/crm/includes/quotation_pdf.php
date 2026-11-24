<?php

/**
 * Quotation PDF storage + Chrome headless generation helpers.
 * Files live in private storage and are served only via authorized download.
 */

require_once __DIR__ . '/quotation_db.php';

const CRM_QUOTATION_PDF_MAX_BYTES = 30 * 1024 * 1024;
/** Bump when print/PDF markup rendering changes (forces stale PDFs to regenerate). */
const CRM_QUOTATION_PDF_RENDER_REV = 'icons-svg-v1';

function crmEnsureQuotationPdfTables(mysqli $conn): void
{
    crmEnsureQuotationTables($conn);

    $conn->query("CREATE TABLE IF NOT EXISTS `crm_quotation_pdfs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `version` INT UNSIGNED NOT NULL DEFAULT 1,
        `file_path` VARCHAR(255) NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `sha256` VARCHAR(64) DEFAULT NULL,
        `content_token` VARCHAR(64) NOT NULL,
        `byte_size` INT UNSIGNED NOT NULL DEFAULT 0,
        `generated_by` INT DEFAULT NULL,
        `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_qpdf_qv` (`quotation_id`, `version`),
        KEY `idx_qpdf_quotation` (`quotation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function crmQuotationPdfStorageDir(): string
{
    $dir = dirname(__DIR__, 2) . '/uploads/private_quotation_pdfs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents(
            $ht,
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
            . "Options -Indexes\n"
        );
    }
    if (!is_file($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function crmQuotationPdfAbsolutePath(string $relative): string
{
    $base = basename(str_replace(['\\', "\0"], ['/', ''], $relative));
    return crmQuotationPdfStorageDir() . '/' . $base;
}

function crmQuotationPdfSanitizeFileName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        $name = 'Quotation.pdf';
    }
    if (!preg_match('/\.pdf$/i', $name)) {
        $name .= '.pdf';
    }
    $name = preg_replace('/[^\w\s.\-()]+/u', '', $name);
    $name = preg_replace('/\s+/', ' ', (string) $name);
    $name = trim((string) $name, " .\t\n\r\0\x0B");
    if ($name === '' || strtolower($name) === '.pdf') {
        $name = 'Quotation.pdf';
    }
    return substr($name, 0, 180);
}

/**
 * Fingerprint of quotation content used to detect stale PDFs.
 */
function crmQuotationPdfContentToken(array $row): string
{
    $parts = [
        CRM_QUOTATION_PDF_RENDER_REV,
        (string) ($row['id'] ?? ''),
        (string) ($row['version'] ?? '1'),
        (string) ($row['updated_at'] ?? ''),
        (string) ($row['guest_name'] ?? ''),
        (string) ($row['destination'] ?? ''),
        (string) ($row['quotation_total'] ?? ''),
        (string) ($row['package_total'] ?? ''),
        substr((string) ($row['itinerary_json'] ?? ''), 0, 2000),
        substr((string) ($row['hotels_json'] ?? ''), 0, 1200),
        substr((string) ($row['flights_json'] ?? ''), 0, 800),
        substr((string) ($row['cost_sheet_json'] ?? ''), 0, 800),
        substr((string) ($row['inclusion'] ?? ''), 0, 400),
        substr((string) ($row['exclusion'] ?? ''), 0, 400),
    ];
    return hash('sha256', implode('|', $parts));
}

function crmQuotationPdfFindChrome(): ?string
{
    static $cached = false;
    static $path = null;
    if ($cached) {
        return $path;
    }
    $cached = true;

    $env = getenv('CHROME_PATH');
    if (is_string($env) && $env !== '' && is_file($env)) {
        $path = $env;
        return $path;
    }

    $candidates = [];
    if (stripos(PHP_OS, 'WIN') === 0) {
        $pf = getenv('ProgramFiles') ?: 'C:\\Program Files';
        $pf86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';
        $local = getenv('LOCALAPPDATA') ?: '';
        $candidates = [
            $pf . '\\Google\\Chrome\\Application\\chrome.exe',
            $pf86 . '\\Google\\Chrome\\Application\\chrome.exe',
            $local . '\\Google\\Chrome\\Application\\chrome.exe',
            $pf . '\\Microsoft\\Edge\\Application\\msedge.exe',
            $pf86 . '\\Microsoft\\Edge\\Application\\msedge.exe',
        ];
    } else {
        $candidates = [
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/snap/bin/chromium',
            '/usr/bin/microsoft-edge',
            '/usr/bin/microsoft-edge-stable',
        ];
    }

    foreach ($candidates as $candidate) {
        if ($candidate && is_file($candidate)) {
            $path = $candidate;
            return $path;
        }
    }

    $path = null;
    return null;
}

function crmQuotationPdfChromeAvailable(): bool
{
    return crmQuotationPdfFindChrome() !== null;
}

/**
 * Render HTML to PDF with Chrome/Edge headless (matches browser Print → Save as PDF).
 */
function crmQuotationPdfRenderWithChrome(string $html, string $outPdfAbs): array
{
    $chrome = crmQuotationPdfFindChrome();
    if (!$chrome) {
        return ['ok' => false, 'message' => 'Chrome/Edge is not available on this server.'];
    }

    $dir = crmQuotationPdfStorageDir();
    $tmpHtml = $dir . '/_tmp_' . bin2hex(random_bytes(8)) . '.html';
    $tmpPdf = $dir . '/_tmp_' . bin2hex(random_bytes(8)) . '.pdf';

    // Prefer absolute http(s) asset URLs already present in print HTML.
    if (@file_put_contents($tmpHtml, $html) === false) {
        return ['ok' => false, 'message' => 'Could not write temporary HTML for PDF generation.'];
    }

    $htmlUrl = 'file:///' . str_replace('\\', '/', $tmpHtml);
    if (stripos(PHP_OS, 'WIN') === 0) {
        $htmlUrl = 'file:///' . str_replace('\\', '/', $tmpHtml);
    }

    $cmd = [
        $chrome,
        '--headless=new',
        '--disable-gpu',
        '--allow-file-access-from-files',
        '--hide-scrollbars',
        '--no-pdf-header-footer',
        '--run-all-compositor-stages-before-draw',
        '--virtual-time-budget=8000',
        '--print-to-pdf=' . $tmpPdf,
        $htmlUrl,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = @proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    $stderr = '';
    $exitCode = 1;
    if (is_resource($proc)) {
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 90);
        stream_set_timeout($pipes[2], 90);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        unset($stdout);
    } else {
        // Fallback via shell on hosts that block proc_open arrays.
        $q = static function ($s) {
            return '"' . str_replace('"', '\\"', $s) . '"';
        };
        $line = $q($chrome)
            . ' --headless=new --disable-gpu --allow-file-access-from-files --no-pdf-header-footer '
            . '--print-to-pdf=' . $q($tmpPdf) . ' ' . $q($htmlUrl);
        @exec($line . ' 2>&1', $out, $exitCode);
        $stderr = is_array($out) ? implode("\n", $out) : '';
    }

    @unlink($tmpHtml);

    if (!is_file($tmpPdf) || filesize($tmpPdf) < 50) {
        @unlink($tmpPdf);
        return [
            'ok' => false,
            'message' => 'Chrome PDF render failed.' . ($stderr !== '' ? ' ' . substr($stderr, 0, 240) : ''),
            'exit_code' => $exitCode,
        ];
    }

    $head = (string) @file_get_contents($tmpPdf, false, null, 0, 5);
    if (strpos($head, '%PDF') !== 0) {
        @unlink($tmpPdf);
        return ['ok' => false, 'message' => 'Chrome produced an invalid PDF.'];
    }

    if (!@rename($tmpPdf, $outPdfAbs)) {
        if (!@copy($tmpPdf, $outPdfAbs)) {
            @unlink($tmpPdf);
            return ['ok' => false, 'message' => 'Could not store generated PDF.'];
        }
        @unlink($tmpPdf);
    }

    return ['ok' => true, 'path' => $outPdfAbs, 'bytes' => (int) filesize($outPdfAbs)];
}

/**
 * Validate and store an uploaded PDF binary as fallback when Chrome is unavailable.
 */
function crmQuotationPdfStoreBinary(string $binary, string $outPdfAbs): array
{
    if (strlen($binary) < 50 || strlen($binary) > CRM_QUOTATION_PDF_MAX_BYTES) {
        return ['ok' => false, 'message' => 'PDF size is invalid.'];
    }
    if (strpos($binary, '%PDF') !== 0) {
        return ['ok' => false, 'message' => 'Uploaded file is not a valid PDF.'];
    }
    if (@file_put_contents($outPdfAbs, $binary) === false) {
        return ['ok' => false, 'message' => 'Could not store uploaded PDF.'];
    }
    return ['ok' => true, 'path' => $outPdfAbs, 'bytes' => (int) filesize($outPdfAbs)];
}

function crmQuotationPdfLoadRow(mysqli $conn, int $quotationId, int $version): ?array
{
    if ($quotationId <= 0 || $version <= 0) {
        return null;
    }
    crmEnsureQuotationPdfTables($conn);
    $stmt = $conn->prepare(
        'SELECT * FROM `crm_quotation_pdfs` WHERE `quotation_id` = ? AND `version` = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $quotationId, $version);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function crmQuotationPdfDeleteFile(?array $pdfRow): void
{
    if (!$pdfRow) {
        return;
    }
    $rel = trim((string) ($pdfRow['file_path'] ?? ''));
    if ($rel === '') {
        return;
    }
    $abs = crmQuotationPdfAbsolutePath($rel);
    if (is_file($abs)) {
        @unlink($abs);
    }
}

function crmQuotationPdfUpsert(
    mysqli $conn,
    int $quotationId,
    int $version,
    string $relativePath,
    string $fileName,
    string $contentToken,
    int $byteSize,
    ?string $sha256,
    ?int $generatedBy
): bool {
    crmEnsureQuotationPdfTables($conn);
    $existing = crmQuotationPdfLoadRow($conn, $quotationId, $version);
    if ($existing && (($existing['file_path'] ?? '') !== $relativePath)) {
        crmQuotationPdfDeleteFile($existing);
    }

    $sql = 'INSERT INTO `crm_quotation_pdfs`
        (`quotation_id`, `version`, `file_path`, `file_name`, `sha256`, `content_token`, `byte_size`, `generated_by`, `generated_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            `file_path` = VALUES(`file_path`),
            `file_name` = VALUES(`file_name`),
            `sha256` = VALUES(`sha256`),
            `content_token` = VALUES(`content_token`),
            `byte_size` = VALUES(`byte_size`),
            `generated_by` = VALUES(`generated_by`),
            `generated_at` = NOW()';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $sha = $sha256 !== null && $sha256 !== '' ? $sha256 : '';
    $genBy = $generatedBy !== null ? (int) $generatedBy : 0;
    $stmt->bind_param(
        'iissssii',
        $quotationId,
        $version,
        $relativePath,
        $fileName,
        $sha,
        $contentToken,
        $byteSize,
        $genBy
    );
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}

/**
 * Resolve quotation data for a specific version (current row or archived snapshot).
 *
 * @return array{ok:bool,message?:string,row?:array,version?:int,is_archived?:bool}
 */
function crmQuotationPdfResolveQuotation(mysqli $conn, int $quotationId, int $version = 0): array
{
    crmEnsureQuotationTables($conn);
    if ($quotationId <= 0) {
        return ['ok' => false, 'message' => 'Quotation id is required.'];
    }

    $stmt = $conn->prepare('SELECT * FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Could not load quotation.'];
    }
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $res = $stmt->get_result();
    $current = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$current) {
        return ['ok' => false, 'message' => 'Quotation not found.'];
    }

    $currentVersion = max(1, (int) ($current['version'] ?? 1));
    if ($version <= 0) {
        $version = $currentVersion;
    }

    if ($version === $currentVersion) {
        return [
            'ok' => true,
            'row' => $current,
            'version' => $version,
            'is_archived' => false,
        ];
    }

    if ($version > $currentVersion) {
        return ['ok' => false, 'message' => 'Requested version does not exist.'];
    }

    $snap = crmQuotationLoadArchivedVersion($conn, $quotationId, $version);
    if (!$snap) {
        return ['ok' => false, 'message' => 'Archived version not found.'];
    }
    $snap['id'] = $quotationId;
    $snap['version'] = $version;
    return [
        'ok' => true,
        'row' => $snap,
        'version' => $version,
        'is_archived' => true,
    ];
}

/**
 * @return array{ok:bool,fresh?:bool,exists?:bool,message?:string,file_name?:string,download_url?:string,chrome?:bool}
 */
function crmQuotationPdfStatus(mysqli $conn, int $quotationId, int $version = 0): array
{
    $resolved = crmQuotationPdfResolveQuotation($conn, $quotationId, $version);
    if (!$resolved['ok']) {
        return $resolved;
    }
    /** @var array $row */
    $row = $resolved['row'];
    $version = (int) $resolved['version'];
    $token = crmQuotationPdfContentToken($row);
    $pdf = crmQuotationPdfLoadRow($conn, $quotationId, $version);
    if (!$pdf) {
        return [
            'ok' => true,
            'exists' => false,
            'fresh' => false,
            'version' => $version,
            'chrome' => crmQuotationPdfChromeAvailable(),
        ];
    }

    $abs = crmQuotationPdfAbsolutePath((string) $pdf['file_path']);
    $exists = is_file($abs) && filesize($abs) > 50;
    $fresh = $exists && hash_equals((string) $pdf['content_token'], $token);

    return [
        'ok' => true,
        'exists' => $exists,
        'fresh' => $fresh,
        'version' => $version,
        'file_name' => (string) ($pdf['file_name'] ?? 'Quotation.pdf'),
        'generated_at' => (string) ($pdf['generated_at'] ?? ''),
        'chrome' => crmQuotationPdfChromeAvailable(),
    ];
}

/**
 * Generate (or replace) the stored PDF for a quotation version.
 *
 * @return array{ok:bool,message:string,file_name?:string,version?:int,bytes?:int,method?:string}
 */
function crmQuotationPdfGenerateAndSave(
    mysqli $conn,
    int $quotationId,
    int $version,
    string $html,
    string $preferredFileName,
    ?string $uploadedPdfBinary,
    ?int $userId
): array {
    $resolved = crmQuotationPdfResolveQuotation($conn, $quotationId, $version);
    if (!$resolved['ok']) {
        return ['ok' => false, 'message' => (string) ($resolved['message'] ?? 'Quotation not found.')];
    }
    /** @var array $row */
    $row = $resolved['row'];
    $version = (int) $resolved['version'];
    $token = crmQuotationPdfContentToken($row);
    $fileName = crmQuotationPdfSanitizeFileName($preferredFileName);
    $rel = 'q' . $quotationId . '_v' . $version . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $abs = crmQuotationPdfAbsolutePath($rel);

    $result = ['ok' => false, 'message' => 'PDF generation failed.'];
    $method = '';

    $html = trim($html);
    if ($html !== '' && crmQuotationPdfChromeAvailable()) {
        $result = crmQuotationPdfRenderWithChrome($html, $abs);
        $method = 'chrome';
    }

    if (empty($result['ok']) && is_string($uploadedPdfBinary) && $uploadedPdfBinary !== '') {
        $result = crmQuotationPdfStoreBinary($uploadedPdfBinary, $abs);
        $method = 'upload';
    }

    if (empty($result['ok']) && $html !== '' && !crmQuotationPdfChromeAvailable()) {
        return [
            'ok' => false,
            'message' => 'Server PDF engine unavailable. Please retry so the browser can upload a PDF copy.',
            'need_client_pdf' => true,
            'chrome' => false,
        ];
    }

    if (empty($result['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($result['message'] ?? 'Could not generate PDF.'),
            'chrome' => crmQuotationPdfChromeAvailable(),
        ];
    }

    $bytes = (int) ($result['bytes'] ?? filesize($abs));
    $sha = hash_file('sha256', $abs) ?: null;
    if (!crmQuotationPdfUpsert($conn, $quotationId, $version, $rel, $fileName, $token, $bytes, $sha, $userId)) {
        @unlink($abs);
        return ['ok' => false, 'message' => 'PDF was generated but could not be saved to the database.'];
    }

    return [
        'ok' => true,
        'message' => 'PDF saved.',
        'file_name' => $fileName,
        'version' => $version,
        'bytes' => $bytes,
        'method' => $method,
        'fresh' => true,
        'exists' => true,
    ];
}
