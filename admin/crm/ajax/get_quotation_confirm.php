<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';

header('Content-Type: application/json; charset=utf-8');

function qConfirmJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    qConfirmJson(false, 'Invalid request.');
}

crmEnsureQuotationTables($conn);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    qConfirmJson(false, 'Invalid quotation.');
}

$stmt = $conn->prepare(
    'SELECT `id`, `quotation_uid`, `lead_id`, `guest_name`, `mobile_no`, `email`, `tour_confirmed`, `tour_confirm_json`,
            `flights_json`, `hotels_json`, `cost_sheet_json`, `no_of_adults`, `no_of_children`, `no_of_infants`,
            `package_total`, `destination`, `tentative_date`
     FROM `crm_quotations` WHERE `id` = ? LIMIT 1'
);
if (!$stmt) {
    qConfirmJson(false, 'Could not load quotation.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    qConfirmJson(false, 'Quotation not found.');
}

$stored = json_decode((string) ($row['tour_confirm_json'] ?? ''), true);
$payload = crmQuotationNormalizeConfirmPayload(is_array($stored) ? $stored : []);
if ($payload['guest_name'] === '') {
    $payload['guest_name'] = (string) $row['guest_name'];
}
if ($payload['mobile_no'] === '') {
    $payload['mobile_no'] = (string) $row['mobile_no'];
}
if ($payload['email'] === '') {
    $payload['email'] = (string) ($row['email'] ?? '');
}

// First-time Book: prefill supplier + totals from quotation details.
if (empty($payload['services'])) {
    $payload['services'] = crmQuotationBuildConfirmServicesFromQuote($row);
    foreach ($payload['services'] as $i => $svc) {
        $payload['services'][$i]['uid'] = crmQuotationServiceUid(is_array($svc) ? $svc : [], $i);
    }
}
require_once __DIR__ . '/../includes/service_vouchers.php';
svEnsureTable($conn);
$payload['services'] = svAttachCounts($conn, $id, $payload['services']);
require_once __DIR__ . '/../includes/service_payments.php';
spEnsureTable($conn);
$payload['services'] = spAttachCounts($conn, $id, $payload['services']);
require_once __DIR__ . '/../includes/customer_payments.php';
cpEnsureTable($conn);
$customerPaid = cpPaidTotal($conn, $id);

$leadId = (int) ($row['lead_id'] ?? 0);
if ($leadId <= 0) {
    $leadId = crmQuotationResolveLeadId($conn, [
        'lead_id' => 0,
        'mobile_no' => (string) ($payload['mobile_no'] ?: ($row['mobile_no'] ?? '')),
        'email' => (string) ($payload['email'] ?: ($row['email'] ?? '')),
    ]);
}

$departureCity = '';
if ($leadId > 0) {
    $leadStmt = $conn->prepare('SELECT `payload_json` FROM `crm_leads` WHERE `id` = ? LIMIT 1');
    if ($leadStmt) {
        $leadStmt->bind_param('i', $leadId);
        $leadStmt->execute();
        $leadRes = $leadStmt->get_result();
        $leadRow = $leadRes ? $leadRes->fetch_assoc() : null;
        $leadStmt->close();
        $leadPayload = json_decode((string) ($leadRow['payload_json'] ?? ''), true);
        if (is_array($leadPayload)) {
            $departureCity = trim((string) ($leadPayload['tp_departure'] ?? ''));
        }
    }
}
$tentativeDate = (string) ($row['tentative_date'] ?? '');
if ($tentativeDate === '0000-00-00') {
    $tentativeDate = '';
}

if (!empty($payload['travellers']) && $leadId > 0) {
    $payload['travellers'] = crmQuotationRefreshTravellersFromContacts($conn, $leadId, $payload['travellers']);
}

// Prefill travellers from lead contacts when none saved yet.
if (empty($payload['travellers']) && $leadId > 0) {
    $payload['travellers'] = crmQuotationBuildTravellersFromLead(
        $conn,
        $leadId,
        (string) ($payload['guest_name'] ?: ($row['guest_name'] ?? ''))
    );
}
if (empty($payload['travellers']) && trim((string) ($payload['guest_name'] ?? '')) !== '') {
    $payload['travellers'] = crmQuotationNormalizeConfirmTravellers([[
        'id' => 'guest_primary',
        'name' => (string) $payload['guest_name'],
        'type' => 'adult',
        'age' => null,
        'passport_number' => '',
        'passport_expiry' => '',
        'documents' => [],
    ]]);
}

require_once __DIR__ . '/../includes/traveller_documents.php';
tdEnsureTable($conn);
foreach ($payload['travellers'] as $i => $t) {
    $payload['travellers'][$i]['documents'] = tdDocumentsSummary($conn, tdTravellerKey($id, (string) $t['id']));
}

if (!empty($payload['travellers'][0])) {
    $first = $payload['travellers'][0];
    $payload['guest_name'] = (string) $first['name'];
    if ((string) ($first['mobile'] ?? '') !== '') {
        $payload['mobile_no'] = (string) $first['mobile'];
    }
    if ((string) ($first['email'] ?? '') !== '') {
        $payload['email'] = (string) $first['email'];
    }
}

qConfirmJson(true, 'OK', [
    'quotation' => [
        'id' => (int) $row['id'],
        'quotation_uid' => (string) $row['quotation_uid'],
        'lead_id' => $leadId,
        'guest_name' => (string) $row['guest_name'],
        'mobile_no' => (string) $row['mobile_no'],
        'email' => (string) ($row['email'] ?? ''),
        'tour_confirmed' => (int) ($row['tour_confirmed'] ?? 0),
        'no_of_adults' => (int) ($row['no_of_adults'] ?? 0),
        'no_of_children' => (int) ($row['no_of_children'] ?? 0),
        'no_of_infants' => (int) ($row['no_of_infants'] ?? 0),
        'package_total' => round((float) ($row['package_total'] ?? 0), 2),
        'customer_paid' => $customerPaid,
        'destination' => trim((string) ($row['destination'] ?? '')),
        'tentative_date' => $tentativeDate,
        'departure_city' => $departureCity,
    ],
    'confirm' => $payload,
    'saved_guests' => crmQuotationSavedGuestsForLead($conn, $leadId),
    'services' => crmQuotationConfirmServiceMap(),
    'autofilled' => !is_array($stored) || empty($stored['services']),
]);
