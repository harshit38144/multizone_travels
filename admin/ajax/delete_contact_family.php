<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lcJson(false, 'Invalid request.');
}

$memberId = (int) ($_POST['member_id'] ?? 0);
$ref = lcContactRefFromRequest();
$source = $ref['source'];
$refId = $ref['ref_id'];

if ($memberId <= 0 || $refId <= 0) {
    lcJson(false, 'Invalid request.');
}

if ($source === 'manual') {
    $stmt = $conn->prepare("DELETE FROM crm_contact_family WHERE id = ? AND contact_id = ?");
    $stmt->bind_param('ii', $memberId, $refId);
} else {
    $stmt = $conn->prepare("DELETE FROM crm_contact_family WHERE id = ? AND lead_id = ? AND contact_id = 0");
    $stmt->bind_param('ii', $memberId, $refId);
}

if (!$stmt) {
    lcJson(false, 'Could not delete.');
}
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    lcJson(false, 'Could not delete family member.');
}

$family = $source === 'manual'
    ? lcGetFamilyByContact($conn, $refId)
    : lcGetFamily($conn, $refId);

lcJson(true, 'Family member removed.', ['family' => $family]);
