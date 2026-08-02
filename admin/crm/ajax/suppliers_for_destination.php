<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/supplier_db.php';

header('Content-Type: application/json; charset=utf-8');

function suppliersDestJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    suppliersDestJson(false, 'Invalid request method.');
}

crmEnsureSupplierTables($conn);

$destination = trim((string) ($_GET['destination'] ?? ''));
$suppliers = crmSuppliersForDestination($conn, $destination);

suppliersDestJson(true, 'OK', [
    'destination' => $destination,
    'suppliers' => $suppliers,
    'count' => count($suppliers),
]);
