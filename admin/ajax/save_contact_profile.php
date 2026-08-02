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

$fields = lcCollectPersonFields();
if ($fields['first_name'] === '' && $source === 'lead' && $refId > 0) {
    $lead = lcGetLead($conn, $refId);
    $fields['first_name'] = trim($lead['customer_name'] ?? '');
}
if ($fields['first_name'] === '') {
    lcJson(false, 'Name is required.');
}

$panExisting = '';
$aadharExisting = '';

if ($source === 'manual') {
    $existing = $refId > 0 ? lcGetManualContact($conn, $refId) : null;
    if ($refId > 0 && !$existing) {
        lcJson(false, 'Contact not found.');
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
        $sql = "UPDATE crm_contacts SET
            title=?, first_name=?, last_name='', email=?, mobile=?,
            date_of_birth=?, gender=?, address_line1=?, city=?,
            photo=?, id_proof_front=?, id_proof_back=?
            WHERE id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            lcJson(false, 'Could not prepare update.');
        }
        $stmt->bind_param(
            'sssssssssssi',
            $fields['title'],
            $fields['first_name'],
            $fields['email'],
            $fields['mobile'],
            $fields['date_of_birth'],
            $fields['gender'],
            $fields['address_line1'],
            $fields['city'],
            $panPhoto,
            $aadharPhoto,
            $otherDoc,
            $refId
        );
    } else {
        $sql = "INSERT INTO crm_contacts
            (title, first_name, last_name, email, mobile,
             date_of_birth, gender, address_line1, city, photo, id_proof_front, id_proof_back)
            VALUES (?,?,'',?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            lcJson(false, 'Could not prepare save.');
        }
        $stmt->bind_param(
            'sssssssssss',
            $fields['title'],
            $fields['first_name'],
            $fields['email'],
            $fields['mobile'],
            $fields['date_of_birth'],
            $fields['gender'],
            $fields['address_line1'],
            $fields['city'],
            $panPhoto,
            $aadharPhoto,
            $otherDoc
        );
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        lcJson(false, 'Could not save contact. ' . $err);
    }
    if (!$existing) {
        $refId = (int) $stmt->insert_id;
    }
    $stmt->close();

    lcJson(true, $existing ? 'Contact updated.' : 'Contact added.', [
        'source' => 'manual',
        'ref_id' => $refId,
        'profile' => lcGetManualContact($conn, $refId),
        'reload' => !$existing,
    ]);
}

// Lead profile
$leadId = $refId;
if ($leadId <= 0 || !lcGetLead($conn, $leadId)) {
    lcJson(false, 'Lead not found.');
}

$existing = lcGetProfile($conn, $leadId);
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
    $sql = "UPDATE crm_contact_profiles SET
        title=?, first_name=?, last_name='', email=?, mobile=?,
        date_of_birth=?, gender=?, address_line1=?, city=?,
        photo=?, id_proof_front=?, id_proof_back=?
        WHERE lead_id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        lcJson(false, 'Could not prepare update.');
    }
    $stmt->bind_param(
        'sssssssssssi',
        $fields['title'],
        $fields['first_name'],
        $fields['email'],
        $fields['mobile'],
        $fields['date_of_birth'],
        $fields['gender'],
        $fields['address_line1'],
        $fields['city'],
        $panPhoto,
        $aadharPhoto,
        $otherDoc,
        $leadId
    );
} else {
    $sql = "INSERT INTO crm_contact_profiles
        (lead_id, title, first_name, last_name, email, mobile,
         date_of_birth, gender, address_line1, city, photo, id_proof_front, id_proof_back)
        VALUES (?,?,?,'',?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        lcJson(false, 'Could not prepare save.');
    }
    $stmt->bind_param(
        'isssssssssss',
        $leadId,
        $fields['title'],
        $fields['first_name'],
        $fields['email'],
        $fields['mobile'],
        $fields['date_of_birth'],
        $fields['gender'],
        $fields['address_line1'],
        $fields['city'],
        $panPhoto,
        $aadharPhoto,
        $otherDoc
    );
}

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    lcJson(false, 'Could not save profile. ' . $err);
}
$stmt->close();

lcJson(true, 'Contact profile saved.', [
    'source' => 'lead',
    'ref_id' => $leadId,
    'profile' => lcGetProfile($conn, $leadId),
]);
