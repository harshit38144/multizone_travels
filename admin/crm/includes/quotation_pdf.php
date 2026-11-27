<?php

/**
 * Quotation PDF storage + Chrome headless generation helpers.
 * Files live in private storage and are served only via authorized download.
 */

require_once __DIR__ . '/quotation_db.php';

const CRM_QUOTATION_PDF_MAX_BYTES = 30 * 1024 * 1024;
/** Absolute floor — blank multi-page Chrome shells are typically ~15–25KB. */
const CRM_QUOTATION_PDF_MIN_BYTES = 40000;
/** Bump when print/PDF markup rendering changes (forces stale PDFs to regenerate). */
const CRM_QUOTATION_PDF_RENDER_REV = 'icons-svg-v6-chrome-paint-no-blank';

function crmQuotationPdfRenderHtmlPath(string $token): string
{
    $token = preg_replace('/[^a-f0-9]/i', '', $token);
    return crmQuotationPdfStorageDir() . '/_render_' . $token . '.html';
}

/**
 * Ajax-dir path segment for quotation_pdf_html.php (from the current SCRIPT_NAME).
 */
function crmQuotationPdfAjaxBasePath(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/[^/]+$#', '', $script);
    if ($base === null || $base === '') {
        $base = '/admin/crm/ajax';
    }
    return $base;
}

/**
 * Public absolute HTTP(S) URL Chrome can load (file:// often paints blank on live hosts).
 */
function crmQuotationPdfRenderHtmlUrl(string $token): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    $scheme = $https ? 'https' : 'http';
    $base = crmQuotationPdfAjaxBasePath();
    return $scheme . '://' . $host . $base . '/quotation_pdf_html.php?t=' . rawurlencode($token);
}

/**
 * Loopback URL so headless Chrome on the same machine does not depend on public DNS/hairpin NAT.
 */
function crmQuotationPdfRenderHtmlLoopbackUrl(string $token): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    $base = crmQuotationPdfAjaxBasePath();
    $path = $base . '/quotation_pdf_html.php?t=' . rawurlencode($token);
    if ($https) {
        if ($port <= 0 || $port === 443) {
            return 'https://127.0.0.1' . $path;
        }
        return 'https://127.0.0.1:' . $port . $path;
    }
    if ($port <= 0 || $port === 80) {
        return 'http://127.0.0.1' . $path;
    }
    return 'http://127.0.0.1:' . $port . $path;
}

/**
 * Public origin used as <base href> so relative assets resolve when Chrome loads via loopback/file.
 */
function crmQuotationPdfPublicOriginBase(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    $scheme = $https ? 'https' : 'http';
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    // /admin/crm/ajax/generate_quotation_pdf.php → /admin/
    $adminBase = preg_replace('#/crm/ajax/[^/]*$#', '/', $script);
    if ($adminBase === null || $adminBase === '') {
        $adminBase = '/admin/';
    }
    return $scheme . '://' . $host . $adminBase;
}

/**
 * Ensure print HTML is paint-ready for headless Chrome (visible, with a public base href).
 */
function crmQuotationPdfPrepareHtmlForChrome(string $html): string
{
    $base = htmlspecialchars(crmQuotationPdfPublicOriginBase(), ENT_QUOTES, 'UTF-8');
    $inject = '<base href="' . $base . '">'
        . '<meta charset="utf-8">'
        . '<style id="q-pdf-force-paint">'
        . 'html,body{opacity:1!important;visibility:visible!important;background:#fff!important;}'
        . '#qPreviewPrintArea,.q-preview-doc,.q-preview-doc *{'
        . 'opacity:1!important;visibility:visible!important;'
        . '-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;'
        . '}'
        . '</style>';
    if (preg_match('/<head([^>]*)>/i', $html)) {
        return (string) preg_replace('/<head([^>]*)>/i', '<head$1>' . $inject, $html, 1);
    }
    return '<!DOCTYPE html><html><head>' . $inject . '</head><body>' . $html . '</body></html>';
}

/**
 * Reject empty / near-empty PDFs (blank page shells) before they are stored or served.
 */
function crmQuotationPdfLooksContentful(string $absPath, string $html = ''): bool
{
    if (!is_file($absPath)) {
        return false;
    }
    $size = (int) filesize($absPath);
    if ($size < CRM_QUOTATION_PDF_MIN_BYTES) {
        return false;
    }
    $htmlLen = strlen($html);
    $min = CRM_QUOTATION_PDF_MIN_BYTES;
    if ($htmlLen > 40000) {
        $min = 80000;
    }
    if ($htmlLen > 120000) {
        $min = 150000;
    }
    if ($size < $min) {
        return false;
    }
    $head = (string) @file_get_contents($absPath, false, null, 0, 5);
    if (strpos($head, '%PDF') !== 0) {
        return false;
    }
    $sampleLen = (int) min(1200000, $size);
    $sample = (string) @file_get_contents($absPath, false, null, 0, $sampleLen);
    if ($sample === '') {
        return false;
    }

    // Blank Chrome shells often have many /Page objects but almost no ink (~20KB for 7 pages).
    $pageCount = preg_match_all('/\/Type\s*\/Page(?!\s*s)/', $sample);
    if ($pageCount >= 2 && $size < ($pageCount * 18000)) {
        return false;
    }

    // Real text operators (escaped slash — previous pattern was invalid PHP regex).
    $hasText = (bool) preg_match('/(\/TJ|\/Tj|\bBT\b|\bET\b|\/Font\b)/', $sample);
    $hasImage = (bool) preg_match('/\/(XObject|Image|DCTDecode|JPXDecode|JBIG2Decode)\b/', $sample);
    $streamCount = substr_count($sample, 'stream');

    if ($hasText || $hasImage) {
        // Still reject tiny "font-only" shells with empty content streams.
        if ($pageCount >= 2 && $size < 50000 && !$hasImage) {
            return false;
        }
        return true;
    }

    // Image-only PDFs (html2canvas JPEG slices) must still be reasonably large.
    return $streamCount >= 2 && $size >= max($min, 90000);
}

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
 * Prefers loopback/public HTTP so CSS/images/fonts paint; file:// is last resort.
 */
function crmQuotationPdfRenderWithChrome(string $html, string $outPdfAbs): array
{
    $chrome = crmQuotationPdfFindChrome();
    if (!$chrome) {
        return ['ok' => false, 'message' => 'Chrome/Edge is not available on this server.'];
    }

    $dir = crmQuotationPdfStorageDir();
    $token = bin2hex(random_bytes(16));
    $tmpHtml = crmQuotationPdfRenderHtmlPath($token);
    $tmpPdf = $dir . '/_tmp_' . bin2hex(random_bytes(8)) . '.pdf';
    $html = crmQuotationPdfPrepareHtmlForChrome($html);

    if (@file_put_contents($tmpHtml, $html) === false) {
        return ['ok' => false, 'message' => 'Could not write temporary HTML for PDF generation.'];
    }

    $loopUrl = crmQuotationPdfRenderHtmlLoopbackUrl($token);
    $httpUrl = crmQuotationPdfRenderHtmlUrl($token);
    $fileUrl = 'file:///' . str_replace('\\', '/', $tmpHtml);
    $urlsToTry = [];
    foreach ([$loopUrl, $httpUrl, $fileUrl] as $candidate) {
        if ($candidate === '' || isset($urlsToTry[$candidate])) {
            continue;
        }
        // Deduplicate while preserving order.
        $urlsToTry[$candidate] = $candidate;
    }
    $urlsToTry = array_values($urlsToTry);

    $isWin = stripos(PHP_OS, 'WIN') === 0;
    $runChrome = static function (string $chromeBin, string $pdfOut, string $pageUrl) use ($isWin) {
        $cmd = [
            $chromeBin,
            '--headless=new',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--hide-scrollbars',
            '--no-first-run',
            '--no-default-browser-check',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=30000',
            '--font-render-hinting=none',
            '--ignore-certificate-errors',
            '--allow-file-access-from-files',
            '--disable-web-security',
            '--print-to-pdf-no-header',
            '--print-to-pdf=' . $pdfOut,
            $pageUrl,
        ];
        // Linux servers almost always need --no-sandbox for headless Chrome under PHP/Apache.
        if (!$isWin) {
            array_splice($cmd, 2, 0, ['--no-sandbox', '--disable-setuid-sandbox']);
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $stderr = '';
        $exitCode = 1;
        $proc = @proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 180);
            stream_set_timeout($pipes[2], 180);
            stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);
        } else {
            $q = static function ($s) {
                return '"' . str_replace('"', '\\"', $s) . '"';
            };
            $extra = $isWin ? '' : ' --no-sandbox --disable-setuid-sandbox';
            $line = $q($chromeBin)
                . ' --headless=new --disable-gpu --disable-dev-shm-usage --hide-scrollbars'
                . $extra
                . ' --no-pdf-header-footer --run-all-compositor-stages-before-draw'
                . ' --virtual-time-budget=30000 --ignore-certificate-errors'
                . ' --allow-file-access-from-files --disable-web-security'
                . ' --print-to-pdf=' . $q($pdfOut) . ' ' . $q($pageUrl);
            @exec($line . ' 2>&1', $out, $exitCode);
            $stderr = is_array($out) ? implode("\n", $out) : '';
        }
        return ['exit' => $exitCode, 'stderr' => $stderr];
    };

    $stderr = '';
    $exitCode = 1;
    $okPdf = false;
    foreach ($urlsToTry as $htmlUrl) {
        @unlink($tmpPdf);
        $run = $runChrome($chrome, $tmpPdf, $htmlUrl);
        $stderr = (string) ($run['stderr'] ?? '');
        $exitCode = (int) ($run['exit'] ?? 1);
        if (is_file($tmpPdf) && crmQuotationPdfLooksContentful($tmpPdf, $html)) {
            $okPdf = true;
            break;
        }
        // Drop blank attempt before trying the next URL strategy.
        @unlink($tmpPdf);
    }

    @unlink($tmpHtml);

    if (!$okPdf) {
        @unlink($tmpPdf);
        return [
            'ok' => false,
            'message' => 'Chrome PDF render failed or produced a blank PDF.'
                . ($stderr !== '' ? ' ' . substr($stderr, 0, 240) : ''),
            'exit_code' => $exitCode,
            'need_client_pdf' => true,
        ];
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
function crmQuotationPdfStoreBinary(string $binary, string $outPdfAbs, string $html = ''): array
{
    if (strlen($binary) < CRM_QUOTATION_PDF_MIN_BYTES || strlen($binary) > CRM_QUOTATION_PDF_MAX_BYTES) {
        return ['ok' => false, 'message' => 'PDF size is invalid or appears blank.'];
    }
    if (strpos($binary, '%PDF') !== 0) {
        return ['ok' => false, 'message' => 'Uploaded file is not a valid PDF.'];
    }
    if (@file_put_contents($outPdfAbs, $binary) === false) {
        return ['ok' => false, 'message' => 'Could not store uploaded PDF.'];
    }
    if (!crmQuotationPdfLooksContentful($outPdfAbs, $html)) {
        @unlink($outPdfAbs);
        return ['ok' => false, 'message' => 'Uploaded PDF appears blank or incomplete.'];
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
    $filePresent = is_file($abs) && filesize($abs) > 50;
    $contentful = $filePresent && crmQuotationPdfLooksContentful($abs);
    $invalidBlank = $filePresent && !$contentful;
    // Drop blank/undersized shells so Download never reuses them.
    if ($invalidBlank) {
        @unlink($abs);
        $filePresent = false;
    }
    $exists = $filePresent && $contentful;
    $fresh = $exists && hash_equals((string) $pdf['content_token'], $token);

    return [
        'ok' => true,
        'exists' => $exists,
        'fresh' => $fresh,
        'version' => $version,
        'file_name' => (string) ($pdf['file_name'] ?? 'Quotation.pdf'),
        'generated_at' => (string) ($pdf['generated_at'] ?? ''),
        'byte_size' => $exists ? (int) filesize($abs) : 0,
        'chrome' => crmQuotationPdfChromeAvailable(),
        'invalid_blank' => $invalidBlank,
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
    $needClient = false;

    $html = trim($html);
    // Drop any previous blank/invalid file for this version before writing a new one.
    $existing = crmQuotationPdfLoadRow($conn, $quotationId, $version);
    if ($existing) {
        $oldAbs = crmQuotationPdfAbsolutePath((string) ($existing['file_path'] ?? ''));
        if (is_file($oldAbs) && !crmQuotationPdfLooksContentful($oldAbs, $html)) {
            @unlink($oldAbs);
        }
    }

    // Client upload wins when present (avoids re-running Chrome after a blank failure).
    // Print/Download without an upload still prefer Chrome headless for best fidelity.
    $hasUpload = is_string($uploadedPdfBinary) && $uploadedPdfBinary !== '';

    if ($hasUpload) {
        $result = crmQuotationPdfStoreBinary($uploadedPdfBinary, $abs, $html);
        $method = 'upload';
    }

    if (empty($result['ok']) && $html !== '' && crmQuotationPdfChromeAvailable()) {
        $result = crmQuotationPdfRenderWithChrome($html, $abs);
        $method = 'chrome';
        if (empty($result['ok']) && !empty($result['need_client_pdf'])) {
            $needClient = true;
        }
    } elseif (empty($result['ok'])) {
        $needClient = ($html !== '' && !$hasUpload);
    }

    if (empty($result['ok']) && $needClient && !$hasUpload) {
        return [
            'ok' => false,
            'message' => 'Server PDF engine unavailable or produced a blank PDF. Please retry so the browser can upload a PDF copy.',
            'need_client_pdf' => true,
            'chrome' => crmQuotationPdfChromeAvailable(),
        ];
    }

    if (empty($result['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($result['message'] ?? 'Could not generate PDF.'),
            'need_client_pdf' => $needClient,
            'chrome' => crmQuotationPdfChromeAvailable(),
        ];
    }

    if (!crmQuotationPdfLooksContentful($abs, $html)) {
        @unlink($abs);
        return [
            'ok' => false,
            'message' => 'Generated PDF appears blank or incomplete.',
            'need_client_pdf' => true,
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
