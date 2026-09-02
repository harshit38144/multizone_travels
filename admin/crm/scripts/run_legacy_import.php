<?php
/**
 * CLI runner: php admin/crm/scripts/run_legacy_import.php [--clear] [--suppliers] [--customers] [--quotations]
 */
$root = dirname(__DIR__, 3);

if (PHP_SAPI === 'cli') {
    $conn = @new mysqli('localhost', 'root', '', 'db_multizone');
    if ($conn->connect_errno) {
        $conn = @new mysqli('localhost', 'u560130840_crm_multizone', '#hmhiW2v', 'u560130840_crm_multizone');
    }
    if (!$conn->connect_errno) {
        $conn->query("SET time_zone = '+05:30'");
    }
} else {
    require_once $root . '/admin/connection.php';
}

require_once $root . '/admin/crm/includes/legacy_import_db.php';

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "CRM database connection failed.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$clear = in_array('--clear', $args, true);
$suppliers = in_array('--suppliers', $args, true);
$customers = in_array('--customers', $args, true);
$quotations = in_array('--quotations', $args, true);

if (!$suppliers && !$customers && !$quotations) {
    $suppliers = $customers = $quotations = true;
}

$result = crmLegacyImportRun($conn, [
    'clear_existing' => $clear,
    'suppliers' => $suppliers,
    'customers' => $customers,
    'quotations' => $quotations,
]);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit(empty($result['success']) ? 1 : 0);
