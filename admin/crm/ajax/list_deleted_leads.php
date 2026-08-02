<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

crmEnsureLeadDeleteColumns($conn);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(5, min(50, (int) ($_GET['per_page'] ?? 10)));
$total = crmLeadsDeletedCount($conn);
$totalPages = max(1, (int) ceil($total / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$rows = crmLeadsListDeleted($conn, $perPage, $offset);
$data = [];

foreach ($rows as $row) {
    $deletedText = '';
    if (!empty($row['deleted_at'])) {
        $ts = strtotime((string) $row['deleted_at']);
        if ($ts !== false) {
            $deletedText = date('d ', $ts) . strtoupper(date('M', $ts)) . date(', h:i A', $ts);
        }
    }

    $payload = [];
    if (!empty($row['payload_json'])) {
        $decoded = json_decode((string) $row['payload_json'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }
    $customerName = (string) ($row['customer_name'] ?? '');
    $customerInitial = crmCustomerInitialFromPayload($payload);

    $data[] = [
        'id' => (int) ($row['id'] ?? 0),
        'lead_uid' => (string) ($row['lead_uid'] ?? ''),
        'customer_name' => $customerName,
        'customer_display_name' => crmFormatCustomerDisplayName($customerName, $customerInitial),
        'customer_phone' => (string) ($row['customer_phone'] ?? ''),
        'customer_email' => (string) ($row['customer_email'] ?? ''),
        'assign_to' => (string) ($row['assign_to'] ?? ''),
        'deleted_at' => (string) ($row['deleted_at'] ?? ''),
        'deleted_at_text' => $deletedText,
        'deleted_by_name' => (string) ($row['deleted_by_name'] ?? ''),
    ];
}

$from = $total > 0 ? $offset + 1 : 0;
$to = $total > 0 ? min($offset + count($data), $total) : 0;

echo json_encode([
    'success' => true,
    'count' => $total,
    'data' => $data,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'from' => $from,
        'to' => $to,
    ],
], JSON_UNESCAPED_UNICODE);
