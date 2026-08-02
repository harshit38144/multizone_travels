<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lcJson(false, 'Invalid request.');
}

$ref = lcContactRefFromRequest();
$source = $ref['source'];
$refId = $ref['ref_id'];
$memberId = (int) ($_POST['member_id'] ?? 0);
$field = trim((string) ($_POST['field'] ?? ''));
$allowed = ['photo', 'id_proof_front', 'id_proof_back'];

if ($refId <= 0) {
    lcJson(false, 'Invalid contact.');
}
if (!in_array($field, $allowed, true)) {
    lcJson(false, 'Invalid attachment field.');
}

if ($source === 'manual' && !lcGetManualContact($conn, $refId)) {
    lcJson(false, 'Contact not found.');
}
if ($source === 'lead' && !lcGetLead($conn, $refId)) {
    lcJson(false, 'Lead not found.');
}

$empty = '';

if ($memberId > 0) {
    if ($source === 'manual') {
        $sql = "UPDATE crm_contact_family SET `{$field}` = ? WHERE id = ? AND contact_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            lcJson(false, 'Could not prepare update.');
        }
        $stmt->bind_param('sii', $empty, $memberId, $refId);
    } else {
        $sql = "UPDATE crm_contact_family SET `{$field}` = ? WHERE id = ? AND lead_id = ? AND contact_id = 0";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            lcJson(false, 'Could not prepare update.');
        }
        $stmt->bind_param('sii', $empty, $memberId, $refId);
    }
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        lcJson(false, 'Could not remove attachment. ' . $err);
    }
    $stmt->close();

    lcJson(true, 'Attachment removed.', [
        'family' => $source === 'manual' ? lcGetFamilyByContact($conn, $refId) : lcGetFamily($conn, $refId),
        'profile' => $source === 'manual' ? lcGetManualContact($conn, $refId) : lcGetProfile($conn, $refId),
    ]);
}

// Primary contact / profile
if ($source === 'manual') {
    $sql = "UPDATE crm_contacts SET `{$field}` = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        lcJson(false, 'Could not prepare update.');
    }
    $stmt->bind_param('si', $empty, $refId);
} else {
    $existing = lcGetProfile($conn, $refId);
    if (!$existing) {
        lcJson(false, 'Profile not found.');
    }
    $sql = "UPDATE crm_contact_profiles SET `{$field}` = ? WHERE lead_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        lcJson(false, 'Could not prepare update.');
    }
    $stmt->bind_param('si', $empty, $refId);
}

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    lcJson(false, 'Could not remove attachment. ' . $err);
}
$stmt->close();

lcJson(true, 'Attachment removed.', [
    'family' => $source === 'manual' ? lcGetFamilyByContact($conn, $refId) : lcGetFamily($conn, $refId),
    'profile' => $source === 'manual' ? lcGetManualContact($conn, $refId) : lcGetProfile($conn, $refId),
]);
