<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';

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
if ($guestName === '') {
    qConfirmSaveJson(false, 'Guest name is required.');
}

$servicesRaw = $_POST['services_json'] ?? '[]';
$decoded = json_decode(is_string($servicesRaw) ? $servicesRaw : '[]', true);
if (!is_array($decoded)) {
    $decoded = [];
}

$payload = crmQuotationNormalizeConfirmPayload([
    'guest_name' => $guestName,
    'mobile_no' => $mobileNo,
    'services' => $decoded,
]);

$json = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}

$tourConfirmed = !empty($payload['services']) ? 1 : 0;

$stmt = $conn->prepare(
    'UPDATE `crm_quotations`
     SET `guest_name` = ?, `mobile_no` = ?, `tour_confirmed` = ?, `tour_confirm_json` = ?
     WHERE `id` = ? LIMIT 1'
);
if (!$stmt) {
    qConfirmSaveJson(false, 'Could not prepare save. ' . $conn->error);
}

$stmt->bind_param('ssisi', $guestName, $mobileNo, $tourConfirmed, $json, $id);
if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    qConfirmSaveJson(false, 'Could not save. ' . $err);
}
$stmt->close();

require_once __DIR__ . '/../includes/lead_db.php';

$leadId = 0;
$leadLookup = $conn->prepare('SELECT `lead_id`, `mobile_no`, `email` FROM `crm_quotations` WHERE `id` = ? LIMIT 1');
if ($leadLookup) {
    $leadLookup->bind_param('i', $id);
    if ($leadLookup->execute()) {
        $leadRes = $leadLookup->get_result();
        $leadRow = $leadRes ? $leadRes->fetch_assoc() : null;
        if ($leadRow) {
            $leadId = (int) ($leadRow['lead_id'] ?? 0);
            if ($leadId <= 0) {
                $leadId = crmQuotationResolveLeadId($conn, [
                    'lead_id' => 0,
                    'mobile_no' => (string) ($leadRow['mobile_no'] ?? ''),
                    'email' => (string) ($leadRow['email'] ?? ''),
                ]);
            }
        }
    }
    $leadLookup->close();
}

if ($leadId > 0) {
    crmLeadSyncFeatureStageForLead($conn, $leadId);
}

qConfirmSaveJson(true, 'Tour confirmation saved.', [
    'id' => $id,
    'tour_confirmed' => $tourConfirmed,
    'status_html' => crmQuotationRenderStatusBadges($json),
    'confirm' => $payload,
]);
