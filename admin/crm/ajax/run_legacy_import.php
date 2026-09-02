<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/legacy_import_db.php';

header('Content-Type: application/json; charset=utf-8');

@set_time_limit(600);
@ini_set('memory_limit', '512M');

function legacyImportJson(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    legacyImportJson(false, 'Invalid request method.');
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    legacyImportJson(false, 'Database connection failed.');
}

$action = trim((string) ($_POST['action'] ?? 'run'));

if ($action === 'probe') {
    legacyImportJson(true, 'Legacy database status.', ['probe' => crmLegacyImportProbe($conn)]);
}

$clearExisting = !empty($_POST['clear_existing']);
$runSuppliers = !empty($_POST['import_suppliers']);
$runCustomers = !empty($_POST['import_customers']);
$runQuotations = !empty($_POST['import_quotations']);

if (!$runSuppliers && !$runCustomers && !$runQuotations) {
    legacyImportJson(false, 'Select at least one data type to import.');
}

$result = crmLegacyImportRun($conn, [
    'clear_existing' => $clearExisting,
    'suppliers' => $runSuppliers,
    'customers' => $runCustomers,
    'quotations' => $runQuotations,
]);

legacyImportJson((bool) ($result['success'] ?? false), (string) ($result['message'] ?? 'Done.'), $result);
