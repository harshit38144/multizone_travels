<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lcJson(false, 'Invalid request.');
}

try {
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

    $title = (string) ($fields['title'] ?? '');
    $firstName = (string) ($fields['first_name'] ?? '');
    $email = (string) ($fields['email'] ?? '');
    $mobile = (string) ($fields['mobile'] ?? '');
    // Always bind a string; SQL NULLIF turns '' into NULL for DATE columns.
    $dobSql = (string) (($fields['date_of_birth'] !== null && $fields['date_of_birth'] !== '')
        ? $fields['date_of_birth']
        : '');
    $gender = (string) ($fields['gender'] ?? '');
    $address = (string) ($fields['address_line1'] ?? '');
    $city = (string) ($fields['city'] ?? '');

    $newProfileUploaded = !empty($_FILES['profile_photo']['name'])
        && (int) ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

    if ($source === 'manual') {
        $existing = $refId > 0 ? lcGetManualContact($conn, $refId) : null;
        if ($refId > 0 && !$existing) {
            lcJson(false, 'Contact not found.');
        }

        $profilePhoto = lcUploadProfilePhoto('profile_photo', (string) ($existing['profile_photo'] ?? ''));
        if ($profilePhoto === false) {
            lcJson(false, 'Invalid profile image. Allowed: JPG, PNG, WEBP, GIF (max 5MB).');
        }
        $panPhoto = lcUploadImage('pan_photo', (string) ($existing['photo'] ?? ''));
        if ($panPhoto === false) {
            lcJson(false, 'Invalid PAN photo. Allowed: JPG, PNG, WEBP, GIF, PDF (max 8MB).');
        }
        $aadharPhoto = lcUploadImage('aadhar_photo', (string) ($existing['id_proof_front'] ?? ''));
        if ($aadharPhoto === false) {
            lcJson(false, 'Invalid Aadhar photo.');
        }
        $otherDoc = lcUploadImage('other_document', (string) ($existing['id_proof_back'] ?? ''));
        if ($otherDoc === false) {
            lcJson(false, 'Invalid other document.');
        }

        if (!$newProfileUploaded && !empty($_POST['clear_profile_photo'])) {
            $profilePhoto = '';
        }

        $panPhoto = (string) $panPhoto;
        $profilePhoto = (string) $profilePhoto;
        $aadharPhoto = (string) $aadharPhoto;
        $otherDoc = (string) $otherDoc;

        if ($existing) {
            $sql = "UPDATE crm_contacts SET
                title=?, first_name=?, last_name='', email=?, mobile=?,
                date_of_birth=NULLIF(?, ''), gender=?, address_line1=?, city=?,
                photo=?, profile_photo=?, id_proof_front=?, id_proof_back=?
                WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                lcJson(false, 'Could not prepare update: ' . $conn->error);
            }
            if (!$stmt->bind_param(
                'ssssssssssssi',
                $title,
                $firstName,
                $email,
                $mobile,
                $dobSql,
                $gender,
                $address,
                $city,
                $panPhoto,
                $profilePhoto,
                $aadharPhoto,
                $otherDoc,
                $refId
            )) {
                lcJson(false, 'Could not bind update params: ' . $stmt->error);
            }
        } else {
            $sql = "INSERT INTO crm_contacts
                (title, first_name, last_name, email, mobile,
                 date_of_birth, gender, address_line1, city, photo, profile_photo, id_proof_front, id_proof_back)
                VALUES (?,?,'',?,?,NULLIF(?, ''),?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                lcJson(false, 'Could not prepare save: ' . $conn->error);
            }
            if (!$stmt->bind_param(
                'ssssssssssss',
                $title,
                $firstName,
                $email,
                $mobile,
                $dobSql,
                $gender,
                $address,
                $city,
                $panPhoto,
                $profilePhoto,
                $aadharPhoto,
                $otherDoc
            )) {
                lcJson(false, 'Could not bind save params: ' . $stmt->error);
            }
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

    $leadId = $refId;
    if ($leadId <= 0 || !lcGetLead($conn, $leadId)) {
        lcJson(false, 'Lead not found.');
    }

    $existing = lcGetProfile($conn, $leadId);

    $profilePhoto = lcUploadProfilePhoto('profile_photo', (string) ($existing['profile_photo'] ?? ''));
    if ($profilePhoto === false) {
        lcJson(false, 'Invalid profile image. Allowed: JPG, PNG, WEBP, GIF (max 5MB).');
    }
    $panPhoto = lcUploadImage('pan_photo', (string) ($existing['photo'] ?? ''));
    if ($panPhoto === false) {
        lcJson(false, 'Invalid PAN photo. Allowed: JPG, PNG, WEBP, GIF, PDF (max 8MB).');
    }
    $aadharPhoto = lcUploadImage('aadhar_photo', (string) ($existing['id_proof_front'] ?? ''));
    if ($aadharPhoto === false) {
        lcJson(false, 'Invalid Aadhar photo.');
    }
    $otherDoc = lcUploadImage('other_document', (string) ($existing['id_proof_back'] ?? ''));
    if ($otherDoc === false) {
        lcJson(false, 'Invalid other document.');
    }

    if (!$newProfileUploaded && !empty($_POST['clear_profile_photo'])) {
        $profilePhoto = '';
    }

    $panPhoto = (string) $panPhoto;
    $profilePhoto = (string) $profilePhoto;
    $aadharPhoto = (string) $aadharPhoto;
    $otherDoc = (string) $otherDoc;

    if ($existing) {
        $sql = "UPDATE crm_contact_profiles SET
            title=?, first_name=?, last_name='', email=?, mobile=?,
            date_of_birth=NULLIF(?, ''), gender=?, address_line1=?, city=?,
            photo=?, profile_photo=?, id_proof_front=?, id_proof_back=?
            WHERE lead_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            lcJson(false, 'Could not prepare update: ' . $conn->error);
        }
        if (!$stmt->bind_param(
            'ssssssssssssi',
            $title,
            $firstName,
            $email,
            $mobile,
            $dobSql,
            $gender,
            $address,
            $city,
            $panPhoto,
            $profilePhoto,
            $aadharPhoto,
            $otherDoc,
            $leadId
        )) {
            lcJson(false, 'Could not bind update params: ' . $stmt->error);
        }
    } else {
        $sql = "INSERT INTO crm_contact_profiles
            (lead_id, title, first_name, last_name, email, mobile,
             date_of_birth, gender, address_line1, city, photo, profile_photo, id_proof_front, id_proof_back)
            VALUES (?,?,?,'',?,?,NULLIF(?, ''),?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            lcJson(false, 'Could not prepare save: ' . $conn->error);
        }
        if (!$stmt->bind_param(
            'issssssssssss',
            $leadId,
            $title,
            $firstName,
            $email,
            $mobile,
            $dobSql,
            $gender,
            $address,
            $city,
            $panPhoto,
            $profilePhoto,
            $aadharPhoto,
            $otherDoc
        )) {
            lcJson(false, 'Could not bind save params: ' . $stmt->error);
        }
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
} catch (Throwable $e) {
    lcJson(false, 'Could not save contact: ' . $e->getMessage());
}
