<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../includes/lead_quotation_prefill.php';

header('Content-Type: application/json; charset=utf-8');

function updateLeadJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    updateLeadJson(false, 'Invalid request method.');
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
if ($leadId <= 0) {
    updateLeadJson(false, 'Invalid lead.');
}

$check = $conn->prepare('SELECT `id` FROM `crm_leads` WHERE `id` = ? AND ' . crmLeadActiveWhereSql() . ' LIMIT 1');
if (!$check) {
    updateLeadJson(false, 'Could not load lead.');
}
$check->bind_param('i', $leadId);
$check->execute();
$cRes = $check->get_result();
$exists = $cRes && $cRes->fetch_assoc();
$check->close();
if (!$exists) {
    updateLeadJson(false, 'Lead not found.');
}

$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$leadSource = trim($_POST['lead_source'] ?? '');
crmEnsureLeadReferralColumns($conn);
$referral = crmResolveLeadReferral(
    $conn,
    trim((string) ($_POST['referred_by'] ?? '')),
    trim((string) ($_POST['referred_contact_source'] ?? '')),
    (int) ($_POST['referred_contact_id'] ?? 0)
);
$referredBy = $referral['referred_by'];
$referredContactSource = $referral['source'];
$referredContactId = $referral['id'];
$assignTo = trim($_POST['assign_to'] ?? '');
$services = $_POST['services'] ?? [];
$itineraryTotalNights = max(0, (int) ($_POST['itinerary_total_nights'] ?? 0));
$itineraryTotalDays = max(0, (int) ($_POST['itinerary_total_days'] ?? 0));

if ($customerName === '') {
    updateLeadJson(false, 'Customer name is required.');
}
if ($customerPhone === '') {
    updateLeadJson(false, 'Customer phone is required.');
}
if ($assignTo === '') {
    updateLeadJson(false, 'Please select assignee.');
}

if (!is_array($services)) {
    $services = [];
}
$services = array_values(array_filter(array_map('trim', $services), function ($s) {
    return $s !== '';
}));

if (count($services) === 0) {
    updateLeadJson(false, 'Please select at least one service.');
}

$createdByName = isset($_SESSION['name']) ? trim((string) $_SESSION['name']) : '';
if ($assignTo === '__self__') {
    $assignTo = $createdByName !== '' ? $createdByName : 'To Self';
}

$payload = $_POST;
unset($payload['lead_id']);
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($payloadJson === false) {
    $payloadJson = '{}';
}
$servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE);
if ($servicesJson === false) {
    $servicesJson = '[]';
}

$sql = "UPDATE `crm_leads` SET
        `customer_name` = ?, `customer_phone` = ?, `customer_email` = ?, `lead_source` = ?, `referred_by` = ?,
        `referred_contact_source` = ?, `referred_contact_id` = ?,
        `assign_to` = ?, `services` = ?, `itinerary_total_nights` = ?, `itinerary_total_days` = ?, `payload_json` = ?
    WHERE `id` = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    updateLeadJson(false, 'Could not prepare update statement. ' . $conn->error);
}

$stmt->bind_param(
    'ssssssissiisi',
    $customerName,
    $customerPhone,
    $customerEmail,
    $leadSource,
    $referredBy,
    $referredContactSource,
    $referredContactId,
    $assignTo,
    $servicesJson,
    $itineraryTotalNights,
    $itineraryTotalDays,
    $payloadJson,
    $leadId
);

if (!$stmt->execute()) {
    $stmt->close();
    updateLeadJson(false, 'Could not update lead. ' . $conn->error);
}
$stmt->close();

// Keep linked quotations' lead-related guest/tour fields aligned with the lead.
$leadRow = crmLeadFetchById($conn, $leadId, false);
$destinationLookup = crmQuotationDestinationLookup($conn);
$leadPrefill = $leadRow ? crmLeadRowToQuotationPrefill($leadRow, $destinationLookup) : [];
$syncedQuotations = 0;
if (!empty($leadPrefill)) {
    $syncedQuotations = crmSyncLinkedQuotationsFromLead($conn, $leadId, $leadPrefill);
}

$sidebar = null;
if ($leadRow) {
    $sidebar = crmLeadRowToSidebarPanel($conn, $leadRow, $destinationLookup);
}

updateLeadJson(true, 'Lead updated successfully.', [
    'lead' => [
        'id' => $leadId,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'customer_email' => $customerEmail,
        'guest_name' => (string) ($leadPrefill['guest_name'] ?? $customerName),
        'mobile_no' => (string) ($leadPrefill['mobile_no'] ?? $customerPhone),
        'email' => (string) ($leadPrefill['email'] ?? $customerEmail),
        'reference_name' => (string) ($leadPrefill['reference_name'] ?? $referredBy),
        'destination' => (string) ($leadPrefill['destination'] ?? ''),
        'tentative_date' => (string) ($leadPrefill['tentative_date'] ?? ''),
        'no_of_adults' => (int) ($leadPrefill['no_of_adults'] ?? 1),
        'no_of_children' => (int) ($leadPrefill['no_of_children'] ?? 0),
        'no_of_infants' => (int) ($leadPrefill['no_of_infants'] ?? 0),
        'children_ages' => array_values($leadPrefill['children_ages'] ?? []),
        'no_of_nights' => (int) ($leadPrefill['no_of_nights'] ?? 0),
    ],
    'sidebar' => $sidebar,
    'synced_quotations' => $syncedQuotations,
]);
