<?php
/**
 * One-click Confirm Tour from Leads Book button.
 * Sets tour_confirmed = 1, syncs lead stage to confirmed, lists in bookings.
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';
require_once __DIR__ . '/../includes/lead_db.php';
require_once __DIR__ . '/../includes/service_vouchers.php';
require_once __DIR__ . '/../includes/service_payments.php';

header('Content-Type: application/json; charset=utf-8');

function qConfirmQuickJson($ok, $msg, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $ok, 'message' => (string) $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    qConfirmQuickJson(false, 'Invalid request method.');
}

crmEnsureQuotationTables($conn);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    qConfirmQuickJson(false, 'Invalid quotation.');
}

$stmt = $conn->prepare(
    'SELECT `id`, `quotation_uid`, `lead_id`, `guest_name`, `mobile_no`, `email`, `destination`,
            `tentative_date`, `no_of_nights`, `no_of_adults`, `no_of_children`, `no_of_infants`,
            `package_total`, `quotation_total`, `cost_sheet_json`, `hotels_json`, `flights_json`,
            `tour_confirmed`, `tour_confirm_json`, `status`
     FROM `crm_quotations` WHERE `id` = ? LIMIT 1'
);
if (!$stmt) {
    qConfirmQuickJson(false, 'Could not load quotation.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    qConfirmQuickJson(false, 'Quotation not found.');
}

$stored = json_decode((string) ($row['tour_confirm_json'] ?? ''), true);
$stored = is_array($stored) ? $stored : [];
$storedServices = (isset($stored['services']) && is_array($stored['services'])) ? $stored['services'] : [];

$services = crmQuotationMergeConfirmServices(
    crmQuotationBuildConfirmServicesFromQuote($row),
    $storedServices
);
if (empty($services) && !empty($storedServices)) {
    $services = $storedServices;
}

$guestName = trim((string) ($stored['guest_name'] ?? ($row['guest_name'] ?? '')));
$mobileNo = trim((string) ($stored['mobile_no'] ?? ($row['mobile_no'] ?? '')));
$email = trim((string) ($stored['email'] ?? ($row['email'] ?? '')));
$travellers = crmQuotationNormalizeConfirmTravellers(
    isset($stored['travellers']) && is_array($stored['travellers']) ? $stored['travellers'] : []
);

$leadId = (int) ($row['lead_id'] ?? 0);
if ($leadId <= 0) {
    $leadId = crmQuotationResolveLeadId($conn, [
        'lead_id' => 0,
        'mobile_no' => $mobileNo,
        'email' => $email,
    ]);
}

if ($guestName === '' && $leadId > 0) {
    $lead = crmLeadFetchById($conn, $leadId, false);
    if (is_array($lead)) {
        $guestName = trim((string) ($lead['customer_name'] ?? ''));
        if ($mobileNo === '') {
            $mobileNo = trim((string) ($lead['customer_phone'] ?? ''));
        }
        if ($email === '') {
            $email = trim((string) ($lead['customer_email'] ?? ''));
        }
    }
}

if ($guestName === '') {
    $guestName = 'Guest';
}

$payload = crmQuotationNormalizeConfirmPayload([
    'guest_name' => $guestName,
    'mobile_no' => $mobileNo,
    'email' => $email,
    'guest_attachment_name' => trim((string) ($stored['guest_attachment_name'] ?? '')),
    'guest_attachment_path' => trim((string) ($stored['guest_attachment_path'] ?? '')),
    'travellers' => $travellers,
    'services' => $services,
]);

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}

$tourConfirmed = 1;
$upd = $conn->prepare(
    'UPDATE `crm_quotations`
     SET `guest_name` = ?, `mobile_no` = ?, `email` = ?, `tour_confirmed` = ?, `tour_confirm_json` = ?
     WHERE `id` = ? LIMIT 1'
);
if (!$upd) {
    qConfirmQuickJson(false, 'Could not prepare confirm. ' . $conn->error);
}
$upd->bind_param('sssisi', $guestName, $mobileNo, $email, $tourConfirmed, $json, $id);
if (!$upd->execute()) {
    $err = $upd->error;
    $upd->close();
    qConfirmQuickJson(false, 'Could not confirm tour. ' . $err);
}
$upd->close();

if ($leadId > 0) {
    crmLeadSyncFeatureStageForLead($conn, $leadId);
}

svEnsureTable($conn);
spEnsureTable($conn);
$payload['services'] = svAttachCounts($conn, $id, $payload['services']);
$payload['services'] = spAttachCounts($conn, $id, $payload['services']);

qConfirmQuickJson(true, 'Tour confirmed.', [
    'id' => $id,
    'lead_id' => $leadId,
    'tour_confirmed' => 1,
    'status_html' => crmQuotationRenderStatusBadges($json),
    'booking_status_html' => crmQuotationBookingStatusIconsHtml($json),
    'confirm' => $payload,
    'lead' => [
        'id' => $leadId,
        'customer_name' => $guestName,
        'customer_phone' => $mobileNo,
        'customer_email' => $email,
        'stage' => 'confirmed',
    ],
]);
