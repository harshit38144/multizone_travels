<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/tour_portfolio.php';

header('Content-Type: application/json; charset=utf-8');

function supplierToursJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    supplierToursJson(false, 'Invalid request method.');
}

$supplierId = (int) ($_GET['supplier_id'] ?? $_GET['id'] ?? 0);
if ($supplierId < 1) {
    supplierToursJson(false, 'Invalid supplier.');
}

crmEnsureSupplierTables($conn);
$stmt = $conn->prepare('SELECT `id`, `name`, `company_name`, `is_active` FROM `crm_suppliers` WHERE `id` = ? LIMIT 1');
if (!$stmt) {
    supplierToursJson(false, 'Could not load supplier.');
}
$stmt->bind_param('i', $supplierId);
$stmt->execute();
$res = $stmt->get_result();
$supplier = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$supplier) {
    supplierToursJson(false, 'Supplier not found.');
}

tpBackfillSupplierIds($conn);

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'destination' => trim((string) ($_GET['destination'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'tour_status' => trim((string) ($_GET['tour_status'] ?? '')),
    'payment_status' => trim((string) ($_GET['payment_status'] ?? '')),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(5, min(50, (int) ($_GET['per_page'] ?? 10)));

$result = tpSupplierAssociatedTours($conn, $supplierId, $filters);
$paged = tpPaginate($result['rows'], $page, $perPage);

supplierToursJson(true, 'OK', [
    'supplier' => [
        'id' => (int) $supplier['id'],
        'name' => (string) ($supplier['name'] ?? ''),
        'company_name' => (string) ($supplier['company_name'] ?? ''),
        'is_active' => (int) ($supplier['is_active'] ?? 0),
    ],
    'summary' => $result['summary'],
    'rows' => $paged['items'],
    'pagination' => [
        'page' => $paged['page'],
        'per_page' => $paged['per_page'],
        'total' => $paged['total'],
        'pages' => $paged['pages'],
    ],
    'needs_review' => (int) ($result['needs_review'] ?? 0),
]);
