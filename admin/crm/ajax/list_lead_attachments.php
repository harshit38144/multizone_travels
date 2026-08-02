<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../includes/lead_attachments_db.php';

header('Content-Type: application/json; charset=utf-8');

$leadId = (int) ($_GET['lead_id'] ?? 0);
if ($leadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid lead.']);
    exit;
}

$check = $conn->prepare('SELECT `id`, `lead_uid`, `customer_name` FROM `crm_leads` WHERE `id` = ? AND ' . crmLeadActiveWhereSql() . ' LIMIT 1');
if (!$check) {
    echo json_encode(['success' => false, 'message' => 'Could not load lead.']);
    exit;
}
$check->bind_param('i', $leadId);
$check->execute();
$res = $check->get_result();
$lead = $res ? $res->fetch_assoc() : null;
$check->close();

if (!$lead) {
    echo json_encode(['success' => false, 'message' => 'Lead not found.']);
    exit;
}

$items = crmLeadAttachmentsList($conn, $leadId);
foreach ($items as &$item) {
    $item['file_size_text'] = crmLeadAttachmentFormatSize((int) ($item['file_size'] ?? 0));
}
unset($item);

echo json_encode([
    'success' => true,
    'lead' => [
        'id' => (int) ($lead['id'] ?? 0),
        'lead_uid' => (string) ($lead['lead_uid'] ?? ''),
        'customer_name' => (string) ($lead['customer_name'] ?? ''),
    ],
    'data' => $items,
], JSON_UNESCAPED_UNICODE);
