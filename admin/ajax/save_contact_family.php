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
$relation = trim($_POST['relation'] ?? '');

if ($refId <= 0) {
    lcJson(false, 'Invalid contact.');
}
if ($relation === '') {
    lcJson(false, 'Relation is required.');
}

if ($source === 'manual' && !lcGetManualContact($conn, $refId)) {
    lcJson(false, 'Contact not found.');
}
if ($source === 'lead' && !lcGetLead($conn, $refId)) {
    lcJson(false, 'Lead not found.');
}

$fields = lcCollectPersonFields();
if ($fields['first_name'] === '') {
    lcJson(false, 'Name is required.');
}

$existing = null;
if ($memberId > 0) {
    if ($source === 'manual') {
        $stmt = $conn->prepare("SELECT * FROM crm_contact_family WHERE id = ? AND contact_id = ? LIMIT 1");
        $stmt->bind_param('ii', $memberId, $refId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM crm_contact_family WHERE id = ? AND lead_id = ? AND contact_id = 0 LIMIT 1");
        $stmt->bind_param('ii', $memberId, $refId);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$existing) {
        lcJson(false, 'Family member not found.');
    }
}

$panExisting = $existing['photo'] ?? '';
$aadharExisting = $existing['id_proof_front'] ?? '';
$otherExisting = $existing['id_proof_back'] ?? '';

$panPhoto = lcUploadImage('pan_photo', $panExisting);
if ($panPhoto === false) {
    lcJson(false, 'Invalid PAN photo. Allowed: JPG, PNG, WEBP, GIF, PDF (max 8MB).');
}
$aadharPhoto = lcUploadImage('aadhar_photo', $aadharExisting);
if ($aadharPhoto === false) {
    lcJson(false, 'Invalid Aadhar photo.');
}
$otherDoc = lcUploadImage('other_document', $otherExisting);
if ($otherDoc === false) {
    lcJson(false, 'Invalid other document.');
}

if ($existing) {
    if ($source === 'manual') {
        $sql = "UPDATE crm_contact_family SET
            relation=?, title=?, first_name=?, last_name='', email=?, mobile=?,
            date_of_birth=?, gender=?, address_line1=?,
            photo=?, id_proof_front=?, id_proof_back=?
            WHERE id=? AND contact_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sssssssssssii',
            $relation,
            $fields['title'],
            $fields['first_name'],
            $fields['email'],
            $fields['mobile'],
            $fields['date_of_birth'],
            $fields['gender'],
            $fields['address_line1'],
            $panPhoto,
            $aadharPhoto,
            $otherDoc,
            $memberId,
            $refId
        );
    } else {
        $sql = "UPDATE crm_contact_family SET
            relation=?, title=?, first_name=?, last_name='', email=?, mobile=?,
            date_of_birth=?, gender=?, address_line1=?,
            photo=?, id_proof_front=?, id_proof_back=?
            WHERE id=? AND lead_id=? AND contact_id=0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sssssssssssii',
            $relation,
            $fields['title'],
            $fields['first_name'],
            $fields['email'],
            $fields['mobile'],
            $fields['date_of_birth'],
            $fields['gender'],
            $fields['address_line1'],
            $panPhoto,
            $aadharPhoto,
            $otherDoc,
            $memberId,
            $refId
        );
    }
} else {
    $leadId = $source === 'lead' ? $refId : 0;
    $contactId = $source === 'manual' ? $refId : 0;
    $sql = "INSERT INTO crm_contact_family
        (lead_id, contact_id, relation, title, first_name, last_name, email, mobile,
         date_of_birth, gender, address_line1, photo, id_proof_front, id_proof_back)
        VALUES (?,?,?,?,?,'',?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'iisssssssssss',
        $leadId,
        $contactId,
        $relation,
        $fields['title'],
        $fields['first_name'],
        $fields['email'],
        $fields['mobile'],
        $fields['date_of_birth'],
        $fields['gender'],
        $fields['address_line1'],
        $panPhoto,
        $aadharPhoto,
        $otherDoc
    );
}

if (!$stmt) {
    lcJson(false, 'Could not prepare save.');
}

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    lcJson(false, 'Could not save family member. ' . $err);
}
$newId = $existing ? $memberId : (int) $stmt->insert_id;
$stmt->close();

$family = $source === 'manual'
    ? lcGetFamilyByContact($conn, $refId)
    : lcGetFamily($conn, $refId);

lcJson(true, $existing ? 'Family member updated.' : 'Family member added.', [
    'member_id' => $newId,
    'family' => $family,
]);
