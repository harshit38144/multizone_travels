<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../../includes/lead_contacts_db.php';
require_once __DIR__ . '/../includes/traveller_documents.php';
require_once __DIR__ . '/../includes/service_vouchers.php';
require_once __DIR__ . '/../includes/service_payments.php';

header('Content-Type: application/json; charset=utf-8');

function qConfirmSaveJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    qConfirmSaveJson(false, 'Invalid request method.');
}

crmEnsureQuotationTables($conn);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    qConfirmSaveJson(false, 'Invalid quotation.');
}

$guestName = trim($_POST['guest_name'] ?? '');
$mobileNo = trim($_POST['mobile_no'] ?? '');
$email = trim($_POST['email'] ?? '');

$travellers = crmQuotationNormalizeConfirmTravellers(
    json_decode((string) ($_POST['travellers_json'] ?? '[]'), true)
);
if (!empty($travellers)) {
    $guestName = (string) $travellers[0]['name'];
    if ($travellers[0]['mobile'] !== '') {
        $mobileNo = (string) $travellers[0]['mobile'];
    }
    if ($travellers[0]['email'] !== '') {
        $email = (string) $travellers[0]['email'];
    }
}
if ($guestName === '') {
    qConfirmSaveJson(false, 'Guest name is required.');
}

// "travellers" mode persists PAX edits immediately without touching services or confirmed status.
$travellersOnly = (($_POST['mode'] ?? '') === 'travellers');

$current = null;
$curStmt = $conn->prepare(
    'SELECT `lead_id`, `mobile_no`, `email`, `tour_confirmed`, `tour_confirm_json` FROM `crm_quotations` WHERE `id` = ? LIMIT 1'
);
if ($curStmt) {
    $curStmt->bind_param('i', $id);
    if ($curStmt->execute()) {
        $curRes = $curStmt->get_result();
        $current = $curRes ? $curRes->fetch_assoc() : null;
    }
    $curStmt->close();
}
if (!$current) {
    qConfirmSaveJson(false, 'Quotation not found.');
}
$stored = json_decode((string) ($current['tour_confirm_json'] ?? ''), true);
$stored = is_array($stored) ? $stored : [];

$leadId = (int) ($current['lead_id'] ?? 0);
if ($leadId <= 0) {
    $leadId = crmQuotationResolveLeadId($conn, [
        'lead_id' => 0,
        'mobile_no' => (string) ($current['mobile_no'] ?? ''),
        'email' => (string) ($current['email'] ?? ''),
    ]);
}

// Primary Contact → Family / Friends → Travellers: write PAX back into lead contacts.
$leadSynced = false;
if ($leadId > 0 && !empty($travellers)) {
    $idsBefore = array_column($travellers, 'id');
    $sync = crmQuotationSyncTravellersWithContacts($conn, $leadId, $travellers, $mobileNo, $email);
    $travellers = $sync['travellers'];
    tdEnsureTable($conn);
    foreach ($travellers as $i => $t) {
        $before = (string) ($idsBefore[$i] ?? '');
        if ($before !== '' && $before !== (string) $t['id']) {
            tdRekeyTraveller($conn, tdTravellerKey($id, $before), tdTravellerKey($id, (string) $t['id']));
        }
    }
    if (!empty($sync['primary'])) {
        $guestName = $sync['primary']['name'];
        $mobileNo = $sync['primary']['mobile'];
        $email = $sync['primary']['email'];
        $leadSynced = true;
    }
} elseif ($leadId > 0) {
    $leadSynced = lcSyncLeadGuestDetails($conn, $leadId, $guestName, $mobileNo, $email);
}

tdEnsureTable($conn);
foreach ($travellers as $i => $t) {
    $travellers[$i]['documents'] = tdDocumentsSummary($conn, tdTravellerKey($id, (string) $t['id']));
}

if ($travellersOnly) {
    $decoded = isset($stored['services']) && is_array($stored['services']) ? $stored['services'] : [];
} else {
    $servicesRaw = $_POST['services_json'] ?? '[]';
    $decoded = json_decode(is_string($servicesRaw) ? $servicesRaw : '[]', true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
}

$payload = crmQuotationNormalizeConfirmPayload([
    'guest_name' => $guestName,
    'mobile_no' => $mobileNo,
    'email' => $email,
    'guest_attachment_name' => trim($_POST['guest_attachment_name'] ?? ($stored['guest_attachment_name'] ?? '')),
    'guest_attachment_path' => trim($_POST['guest_attachment_path'] ?? ($stored['guest_attachment_path'] ?? '')),
    'travellers' => $travellers,
    'services' => $decoded,
]);

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}

$tourConfirmed = $travellersOnly
    ? (int) ($current['tour_confirmed'] ?? 0)
    : (!empty($payload['services']) ? 1 : 0);

$stmt = $conn->prepare(
    'UPDATE `crm_quotations`
     SET `guest_name` = ?, `mobile_no` = ?, `email` = ?, `tour_confirmed` = ?, `tour_confirm_json` = ?
     WHERE `id` = ? LIMIT 1'
);
if (!$stmt) {
    qConfirmSaveJson(false, 'Could not prepare save. ' . $conn->error);
}

$stmt->bind_param('sssisi', $guestName, $mobileNo, $email, $tourConfirmed, $json, $id);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    qConfirmSaveJson(false, 'Could not save. ' . $err);
}
$stmt->close();

if ($leadId > 0) {
    crmLeadSyncFeatureStageForLead($conn, $leadId);
}

svEnsureTable($conn);
spEnsureTable($conn);
if (!$travellersOnly) {
    svDeleteOrphans($conn, $id, array_column($payload['services'], 'uid'));
    spDeleteOrphans($conn, $id, array_column($payload['services'], 'uid'));
}
$payload['services'] = svAttachCounts($conn, $id, $payload['services']);
$payload['services'] = spAttachCounts($conn, $id, $payload['services']);

qConfirmSaveJson(true, 'Tour confirmation saved.', [
    'id' => $id,
    'lead_id' => $leadId,
    'lead_synced' => $leadSynced ? 1 : 0,
    'tour_confirmed' => $tourConfirmed,
    'status_html' => crmQuotationRenderStatusBadges($json),
    'booking_status_html' => crmQuotationBookingStatusIconsHtml($json),
    'confirm' => $payload,
    'saved_guests' => crmQuotationSavedGuestsForLead($conn, $leadId),
    'lead' => [
        'id' => $leadId,
        'customer_name' => $guestName,
        'customer_phone' => $mobileNo,
        'customer_email' => $email,
    ],
]);
