<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/package_quotation.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$limit = min(20, max(5, (int) ($_GET['limit'] ?? 12)));

if (!crmPackageTablesExist($conn)) {
    echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = crmSearchPackagesForQuotation($conn, $query, $limit);

echo json_encode([
    'success' => true,
    'data' => $rows,
], JSON_UNESCAPED_UNICODE);
