<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_quotation_prefill.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');
$limit = min(15, max(5, (int) ($_GET['limit'] ?? 10)));

if (mb_strlen($query) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$tableCheck = $conn->query("SHOW TABLES LIKE 'crm_leads'");
if ($tableCheck && $tableCheck->num_rows === 0) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

crmEnsureLeadDeleteColumns($conn);

$destinationLookup = crmQuotationDestinationLookup($conn);

$like = '%' . $query . '%';
$sql = "SELECT `id`, `lead_uid`, `customer_name`, `customer_phone`, `customer_email`, `referred_by`,
        `itinerary_total_nights`, `payload_json`
    FROM `crm_leads`
    WHERE `deleted_at` IS NULL
      AND (`customer_name` LIKE ? OR `customer_phone` LIKE ? OR `customer_email` LIKE ?)
    ORDER BY `id` DESC
    LIMIT ?";

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('sssi', $like, $like, $like, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $prefill = crmLeadRowToQuotationPrefill($row, $destinationLookup);
        $guestName = $prefill['guest_name'];
        $mobileNo = $prefill['mobile_no'];
        $email = $prefill['email'];
        $prefill['label'] = $guestName !== '' ? $guestName : ($mobileNo !== '' ? $mobileNo : $email);
        $prefill['sub_label'] = trim($mobileNo . ($email !== '' ? ($mobileNo !== '' ? ' · ' : '') . $email : ''));
        $rows[] = $prefill;
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'data' => $rows,
], JSON_UNESCAPED_UNICODE);
