<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/legacy_import_db.php';

header('Content-Type: application/json; charset=utf-8');

@set_time_limit(300);
@ini_set('memory_limit', '512M');
ignore_user_abort(true);

if (ob_get_level() === 0) {
    ob_start();
}

function legacyImportJson(bool $success, string $message, array $extra = []): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . ($err['message'] ?? 'Unknown error'),
        'file' => basename((string) ($err['file'] ?? '')),
        'line' => (int) ($err['line'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legacyImportJson(false, 'Invalid request method.');
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    legacyImportJson(false, 'Database connection failed.');
}

$action = trim((string) ($_POST['action'] ?? 'run'));

$options = [
    'clear_existing' => !empty($_POST['clear_existing']),
    'suppliers' => !empty($_POST['import_suppliers']),
    'customers' => !empty($_POST['import_customers']),
    'quotations' => !empty($_POST['import_quotations']),
];

if ($action === 'probe') {
    legacyImportJson(true, 'Legacy database status.', ['probe' => crmLegacyImportProbe($conn)]);
}

$stepActions = [
    'wipe',
    'stage_init',
    'stage_quotations',
    'import_suppliers',
    'import_customers',
    'import_quotations',
    'cleanup',
    'repair_quotations',
    'import_leads_from_quotations',
];

if (in_array($action, $stepActions, true)) {
    try {
        $result = crmLegacyImportHandleStep($conn, $action, $options);
        legacyImportJson((bool) ($result['success'] ?? false), (string) ($result['message'] ?? 'Done.'), $result);
    } catch (Throwable $e) {
        legacyImportJson(false, 'Import error: ' . $e->getMessage(), ['step' => $action]);
    }
}

if (!$options['suppliers'] && !$options['customers'] && !$options['quotations']) {
    legacyImportJson(false, 'Select at least one data type to import.');
}

try {
    $result = crmLegacyImportRun($conn, $options);
    legacyImportJson((bool) ($result['success'] ?? false), (string) ($result['message'] ?? 'Done.'), $result);
} catch (Throwable $e) {
    legacyImportJson(false, 'Import error: ' . $e->getMessage());
}
