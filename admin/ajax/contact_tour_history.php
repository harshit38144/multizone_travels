<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';
require_once __DIR__ . '/../crm/includes/tour_portfolio.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

header('Content-Type: application/json; charset=utf-8');

function contactTourHistoryJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    contactTourHistoryJson(false, 'Invalid request method.');
}

$ref = lcContactRefFromRequest();
$source = $ref['source'];
$refId = $ref['ref_id'];
if ($refId < 1) {
    contactTourHistoryJson(false, 'Invalid contact.');
}

if ($source === 'manual') {
    if (!lcGetManualContact($conn, $refId)) {
        contactTourHistoryJson(false, 'Contact not found.');
    }
} else {
    if (!lcGetLead($conn, $refId)) {
        contactTourHistoryJson(false, 'Lead not found.');
    }
}

// Soft sync existing confirmed tours → supplier_id (idempotent).
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

$result = tpContactTourHistory($conn, $source, $refId, $filters);
$paged = tpPaginate($result['tours'], $page, $perPage);

contactTourHistoryJson(true, 'OK', [
    'source' => $source,
    'ref_id' => $refId,
    'summary' => $result['summary'],
    'tours' => $paged['items'],
    'pagination' => [
        'page' => $paged['page'],
        'per_page' => $paged['per_page'],
        'total' => $paged['total'],
        'pages' => $paged['pages'],
    ],
    'needs_review' => (int) ($result['needs_review'] ?? 0),
]);
