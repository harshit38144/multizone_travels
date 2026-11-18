<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../includes/lead_quotation_prefill.php';

header('Content-Type: application/json; charset=utf-8');

$leadId = (int) ($_GET['lead_id'] ?? $_POST['lead_id'] ?? 0);
if ($leadId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid lead.']);
    exit;
}

$row = crmLeadFetchById($conn, $leadId, false);
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Lead not found.']);
    exit;
}

$payload = [];
if (!empty($row['payload_json'])) {
    $decoded = json_decode((string) $row['payload_json'], true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$services = [];
if (!empty($row['services'])) {
    $decodedServices = json_decode((string) $row['services'], true);
    if (is_array($decodedServices)) {
        $services = array_values(array_filter(array_map('trim', $decodedServices)));
    }
}

$destinationLookup = crmQuotationDestinationLookup($conn);
$quotationPrefill = crmLeadRowToQuotationPrefill($row, $destinationLookup);
$sidebar = crmLeadRowToSidebarPanel($conn, $row, $destinationLookup);

$lead = [
    'id' => (int) ($row['id'] ?? 0),
    'lead_uid' => (string) ($row['lead_uid'] ?? ''),
    'customer_name' => (string) ($row['customer_name'] ?? ''),
    'customer_phone' => (string) ($row['customer_phone'] ?? ''),
    'customer_email' => (string) ($row['customer_email'] ?? ''),
    'lead_source' => (string) ($row['lead_source'] ?? ''),
    'referred_by' => (string) ($row['referred_by'] ?? ''),
    'referred_contact_source' => (string) ($row['referred_contact_source'] ?? ''),
    'referred_contact_id' => (int) ($row['referred_contact_id'] ?? 0),
    'assign_to' => (string) ($row['assign_to'] ?? ''),
    'itinerary_total_nights' => (int) ($row['itinerary_total_nights'] ?? 0),
    'services' => $services,
    'payload' => $payload,
];

echo json_encode([
    'success' => true,
    'lead' => $lead,
    'quotation_prefill' => $quotationPrefill,
    'sidebar' => $sidebar,
], JSON_UNESCAPED_UNICODE);
