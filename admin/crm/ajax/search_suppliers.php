<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/supplier_db.php';

header('Content-Type: application/json; charset=utf-8');

function searchSuppliersJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    searchSuppliersJson(false, 'Invalid request method.');
}

$query = trim((string) ($_GET['q'] ?? ''));
$serviceKey = trim((string) ($_GET['service'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 25);

$suppliers = crmSuppliersSuggest($conn, $query, $serviceKey, $limit);

searchSuppliersJson(true, 'OK', [
    'suppliers' => $suppliers,
    'count' => count($suppliers),
]);
