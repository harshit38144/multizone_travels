<?php

/**
 * Lead contacts — primary profile + family members with ID proof documents.
 */

function lcEnsureContactTables(mysqli $conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_contact_profiles` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(10) DEFAULT NULL,
        `first_name` VARCHAR(120) DEFAULT NULL,
        `last_name` VARCHAR(120) DEFAULT NULL,
        `email` VARCHAR(190) DEFAULT NULL,
        `mobile` VARCHAR(40) DEFAULT NULL,
        `alt_mobile` VARCHAR(40) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT NULL,
        `nationality` VARCHAR(80) DEFAULT NULL,
        `address_line1` VARCHAR(255) DEFAULT NULL,
        `address_line2` VARCHAR(255) DEFAULT NULL,
        `city` VARCHAR(120) DEFAULT NULL,
        `state` VARCHAR(120) DEFAULT NULL,
        `country` VARCHAR(120) DEFAULT NULL,
        `pincode` VARCHAR(20) DEFAULT NULL,
        `id_proof_type` VARCHAR(60) DEFAULT NULL,
        `id_proof_number` VARCHAR(80) DEFAULT NULL,
        `id_proof_front` VARCHAR(255) DEFAULT NULL,
        `id_proof_back` VARCHAR(255) DEFAULT NULL,
        `photo` VARCHAR(255) DEFAULT NULL,
        `passport_number` VARCHAR(80) DEFAULT NULL,
        `passport_expiry` DATE DEFAULT NULL,
        `notes` TEXT,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_lead_profile` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `crm_contact_family` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `contact_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `relation` VARCHAR(60) NOT NULL DEFAULT '',
        `title` VARCHAR(10) DEFAULT NULL,
        `first_name` VARCHAR(120) NOT NULL DEFAULT '',
        `last_name` VARCHAR(120) DEFAULT NULL,
        `email` VARCHAR(190) DEFAULT NULL,
        `mobile` VARCHAR(40) DEFAULT NULL,
        `alt_mobile` VARCHAR(40) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT NULL,
        `nationality` VARCHAR(80) DEFAULT NULL,
        `address_line1` VARCHAR(255) DEFAULT NULL,
        `address_line2` VARCHAR(255) DEFAULT NULL,
        `city` VARCHAR(120) DEFAULT NULL,
        `state` VARCHAR(120) DEFAULT NULL,
        `country` VARCHAR(120) DEFAULT NULL,
        `pincode` VARCHAR(20) DEFAULT NULL,
        `id_proof_type` VARCHAR(60) DEFAULT NULL,
        `id_proof_number` VARCHAR(80) DEFAULT NULL,
        `id_proof_front` VARCHAR(255) DEFAULT NULL,
        `id_proof_back` VARCHAR(255) DEFAULT NULL,
        `photo` VARCHAR(255) DEFAULT NULL,
        `passport_number` VARCHAR(80) DEFAULT NULL,
        `passport_expiry` DATE DEFAULT NULL,
        `notes` TEXT,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_family_lead` (`lead_id`),
        KEY `idx_family_contact` (`contact_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `crm_contacts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(10) DEFAULT NULL,
        `first_name` VARCHAR(120) NOT NULL DEFAULT '',
        `last_name` VARCHAR(120) DEFAULT NULL,
        `email` VARCHAR(190) DEFAULT NULL,
        `mobile` VARCHAR(40) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT NULL,
        `address_line1` VARCHAR(255) DEFAULT NULL,
        `photo` VARCHAR(255) DEFAULT NULL,
        `id_proof_front` VARCHAR(255) DEFAULT NULL,
        `id_proof_back` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $famCol = $conn->query("SHOW COLUMNS FROM `crm_contact_family` LIKE 'contact_id'");
    if ($famCol && $famCol->num_rows == 0) {
        $conn->query("ALTER TABLE `crm_contact_family` ADD `contact_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `lead_id`, ADD KEY `idx_family_contact` (`contact_id`)");
    }

    $otherDocCol = $conn->query("SHOW COLUMNS FROM `crm_contacts` LIKE 'id_proof_back'");
    if ($otherDocCol && $otherDocCol->num_rows == 0) {
        $conn->query("ALTER TABLE `crm_contacts` ADD `id_proof_back` VARCHAR(255) DEFAULT NULL AFTER `id_proof_front`");
    }

    $cityCol = $conn->query("SHOW COLUMNS FROM `crm_contacts` LIKE 'city'");
    if ($cityCol && $cityCol->num_rows == 0) {
        $conn->query("ALTER TABLE `crm_contacts` ADD `city` VARCHAR(120) DEFAULT NULL AFTER `address_line1`");
    }

    $profilePhotoTables = ['crm_contacts', 'crm_contact_profiles'];
    foreach ($profilePhotoTables as $table) {
        $chk = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'profile_photo'");
        if ($chk && $chk->num_rows == 0) {
            $conn->query("ALTER TABLE `{$table}` ADD `profile_photo` VARCHAR(255) DEFAULT NULL AFTER `photo`");
        }
    }
}

function lcJson($success, $message = '', $extra = [])
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => (string) $message,
    ], $extra));
    exit;
}

function lcRequireAdmin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] != '1') {
        http_response_code(403);
        lcJson(false, 'Forbidden');
    }
}

function lcUploadDir()
{
    $dir = __DIR__ . '/../uploads/contact_documents/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lcUploadImage($field, $existing = '')
{
    if (empty($_FILES[$field]['name']) || !empty($_FILES[$field]['error'])) {
        return (string) $existing;
    }

    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        return false;
    }

    if ($_FILES[$field]['size'] > 8 * 1024 * 1024) {
        return false;
    }

    $filename = 'cd_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = lcUploadDir() . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return false;
    }

    return 'uploads/contact_documents/' . $filename;
}

/** Profile avatar — images only (no PDF). */
function lcUploadProfilePhoto($field, $existing = '')
{
    if (empty($_FILES[$field]['name']) || !empty($_FILES[$field]['error'])) {
        return (string) $existing;
    }

    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        return false;
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $filename = 'av_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = lcUploadDir() . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return false;
    }

    return 'uploads/contact_documents/' . $filename;
}

function lcNullableDate($value)
{
    $value = trim((string) $value);
    if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    return $value;
}

function lcSplitName($fullName)
{
    $fullName = trim(preg_replace('/\s+/', ' ', (string) $fullName));
    if ($fullName === '') {
        return ['', ''];
    }
    $parts = explode(' ', $fullName, 2);
    return [$parts[0], $parts[1] ?? ''];
}

function lcGetLead(mysqli $conn, $leadId)
{
    $stmt = $conn->prepare("SELECT id, customer_name, customer_phone, customer_email, created_at FROM crm_leads WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function lcGetProfile(mysqli $conn, $leadId)
{
    $stmt = $conn->prepare("SELECT * FROM crm_contact_profiles WHERE lead_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function lcGetFamily(mysqli $conn, $leadId)
{
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM crm_contact_family WHERE lead_id = ? AND contact_id = 0 ORDER BY id ASC");
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $leadId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function lcGetFamilyByContact(mysqli $conn, $contactId)
{
    $rows = [];
    $stmt = $conn->prepare("SELECT * FROM crm_contact_family WHERE contact_id = ? ORDER BY id ASC");
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('i', $contactId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function lcGetManualContact(mysqli $conn, $contactId)
{
    $stmt = $conn->prepare("SELECT * FROM crm_contacts WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $contactId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function lcParseContactRef($source, $refId)
{
    $source = strtolower(trim((string) $source));
    if (!in_array($source, ['lead', 'manual'], true)) {
        $source = 'lead';
    }
    return ['source' => $source, 'ref_id' => max(0, (int) $refId)];
}

function lcContactRefFromRequest()
{
    $source = $_POST['contact_source'] ?? $_GET['contact_source'] ?? 'lead';
    $refId = $_POST['ref_id'] ?? $_GET['ref_id'] ?? $_POST['lead_id'] ?? $_GET['lead_id'] ?? $_POST['contact_id'] ?? $_GET['contact_id'] ?? 0;
    return lcParseContactRef($source, $refId);
}

function lcManualContactAsProfile($row)
{
    if (!$row) {
        return null;
    }
    return $row;
}

function lcContactDisplayName($name, $title = '')
{
    $name = trim((string) $name);
    $title = trim((string) $title);
    if ($title !== '' && $name !== '') {
        return $title . ' ' . $name;
    }
    return $name !== '' ? $name : $title;
}

function lcListAllContacts(mysqli $conn)
{
    $items = [];

    $leadTable = $conn->query("SHOW TABLES LIKE 'crm_leads'");
    if ($leadTable && $leadTable->num_rows > 0) {
        $sql = "SELECT l.id, l.customer_name, l.customer_phone, l.customer_email, l.created_at,
                       (SELECT COUNT(*) FROM crm_contact_family f WHERE f.lead_id = l.id AND f.contact_id = 0) AS family_count,
                       (SELECT COUNT(*) FROM crm_contact_profiles p WHERE p.lead_id = l.id) AS has_profile,
                       (SELECT p.profile_photo FROM crm_contact_profiles p WHERE p.lead_id = l.id LIMIT 1) AS profile_photo
                FROM crm_leads l
                ORDER BY l.id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = [
                    'source' => 'lead',
                    'ref_id' => (int) $row['id'],
                    'customer_name' => (string) ($row['customer_name'] ?? ''),
                    'customer_phone' => (string) ($row['customer_phone'] ?? ''),
                    'customer_email' => (string) ($row['customer_email'] ?? ''),
                    'family_count' => (int) ($row['family_count'] ?? 0),
                    'has_profile' => (int) ($row['has_profile'] ?? 0) > 0,
                    'profile_photo' => (string) ($row['profile_photo'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        }
    }

    $manualSql = "SELECT c.id, c.title, c.first_name, c.last_name, c.email, c.mobile, c.profile_photo, c.created_at,
                         (SELECT COUNT(*) FROM crm_contact_family f WHERE f.contact_id = c.id) AS family_count
                  FROM crm_contacts c
                  ORDER BY c.id DESC";
    $mRes = $conn->query($manualSql);
    if ($mRes) {
        while ($row = $mRes->fetch_assoc()) {
            $name = lcDisplayName($row);
            $items[] = [
                'source' => 'manual',
                'ref_id' => (int) $row['id'],
                'customer_name' => $name,
                'customer_phone' => (string) ($row['mobile'] ?? ''),
                'customer_email' => (string) ($row['email'] ?? ''),
                'family_count' => (int) ($row['family_count'] ?? 0),
                'has_profile' => true,
                'profile_photo' => (string) ($row['profile_photo'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
    }

    usort($items, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return $items;
}

/**
 * Search contacts (leads + manual + profiles) for payment link autocomplete.
 *
 * @return list<array{name:string,email:string,mobile:string,label:string,sub_label:string,source:string,ref_id:int}>
 */
function lcSearchContactsForPayment(mysqli $conn, string $query, int $limit = 10): array
{
    lcEnsureContactTables($conn);
    $query = trim($query);
    if (strlen($query) < 2) {
        return [];
    }

    $limit = min(15, max(5, $limit));
    $esc = $conn->real_escape_string($query);
    $like = "'%" . $esc . "%'";
    $lim = (int) $limit;
    $items = [];
    $seen = [];

    $push = function ($name, $email, $mobile, $source, $refId, $title = '', $label = '') use (&$items, &$seen, $limit) {
        if (count($items) >= $limit) {
            return true;
        }
        $name = trim((string) $name);
        $email = trim((string) $email);
        $mobile = trim((string) $mobile);
        $title = trim((string) $title);
        if ($name === '' && $email === '' && $mobile === '') {
            return false;
        }
        $mobileDigits = preg_replace('/\D/', '', $mobile);
        $key = strtolower($mobileDigits . '|' . strtolower($email) . '|' . strtolower($name));
        if (isset($seen[$key])) {
            return false;
        }
        $seen[$key] = true;
        $sourceLabel = $source === 'manual' ? 'Contact' : 'Lead';
        $sub = trim($mobile . ($email !== '' ? ($mobile !== '' ? ' · ' : '') . $email : ''));
        if ($sub !== '') {
            $sub .= ' · ' . $sourceLabel;
        } else {
            $sub = $sourceLabel;
        }
        if ($label === '') {
            $label = trim($title . ($title !== '' && $name !== '' ? ' ' : '') . $name);
        }
        $items[] = [
            'name' => $name,
            'title' => $title,
            'email' => $email,
            'mobile' => $mobile,
            'source' => $source,
            'ref_id' => (int) $refId,
            'label' => $label !== '' ? $label : ($mobile !== '' ? $mobile : $email),
            'sub_label' => $sub,
        ];
        return count($items) >= $limit;
    };

    $parseLeadCustomerName = function (string $customerName): array {
        $customerName = trim($customerName);
        if ($customerName === '') {
            return ['', ''];
        }
        if (preg_match('/^(Mr|Mrs|Ms|Mstr|Miss)\.?\s+(.+)$/iu', $customerName, $matches)) {
            $title = $matches[1];
            $normalized = [
                'mr' => 'Mr',
                'mrs' => 'Mrs',
                'ms' => 'Ms',
                'mstr' => 'Mstr',
                'miss' => 'Miss',
            ];
            $titleKey = strtolower($title);
            if (isset($normalized[$titleKey])) {
                $title = $normalized[$titleKey];
            }
            return [$title, trim((string) ($matches[2] ?? ''))];
        }
        return ['', $customerName];
    };

    $manualRes = $conn->query(
        "SELECT id, title, first_name, last_name, email, mobile
         FROM crm_contacts
         WHERE first_name LIKE $like OR last_name LIKE $like OR email LIKE $like OR mobile LIKE $like
         ORDER BY id DESC
         LIMIT $lim"
    );
    if ($manualRes) {
        while ($row = $manualRes->fetch_assoc()) {
            $nameOnly = trim(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? ''));
            if ($push(
                $nameOnly,
                (string) ($row['email'] ?? ''),
                (string) ($row['mobile'] ?? ''),
                'manual',
                (int) $row['id'],
                (string) ($row['title'] ?? ''),
                lcDisplayName($row)
            )) {
                break;
            }
        }
        $manualRes->free();
    }

    $leadTable = $conn->query("SHOW TABLES LIKE 'crm_leads'");
    if ($leadTable && $leadTable->num_rows > 0) {
        $leadRes = $conn->query(
            "SELECT id, customer_name, customer_phone, customer_email
             FROM crm_leads
             WHERE customer_name LIKE $like OR customer_phone LIKE $like OR customer_email LIKE $like
             ORDER BY id DESC
             LIMIT $lim"
        );
        if ($leadRes) {
            while ($row = $leadRes->fetch_assoc()) {
                $customerName = trim((string) ($row['customer_name'] ?? ''));
                [$title, $nameOnly] = $parseLeadCustomerName($customerName);
                if ($push(
                    $nameOnly,
                    (string) ($row['customer_email'] ?? ''),
                    (string) ($row['customer_phone'] ?? ''),
                    'lead',
                    (int) $row['id'],
                    $title,
                    $customerName
                )) {
                    break;
                }
            }
            $leadRes->free();
        }

        $profileRes = $conn->query(
            "SELECT p.lead_id, p.title, p.first_name, p.last_name, p.email, p.mobile,
                    l.customer_name, l.customer_phone, l.customer_email
             FROM crm_contact_profiles p
             INNER JOIN crm_leads l ON l.id = p.lead_id
             WHERE p.first_name LIKE $like OR p.last_name LIKE $like OR p.email LIKE $like OR p.mobile LIKE $like
                OR l.customer_name LIKE $like OR l.customer_phone LIKE $like OR l.customer_email LIKE $like
             ORDER BY p.id DESC
             LIMIT $lim"
        );
        if ($profileRes) {
            while ($row = $profileRes->fetch_assoc()) {
                $profileName = lcDisplayName($row);
                if ($profileName === '') {
                    $profileName = trim((string) ($row['customer_name'] ?? ''));
                }
                $profileEmail = trim((string) ($row['email'] ?? ''));
                if ($profileEmail === '') {
                    $profileEmail = trim((string) ($row['customer_email'] ?? ''));
                }
                $profileMobile = trim((string) ($row['mobile'] ?? ''));
                if ($profileMobile === '') {
                    $profileMobile = trim((string) ($row['customer_phone'] ?? ''));
                }
                $profileNameOnly = trim(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? ''));
                if ($profileNameOnly === '') {
                    [$profileTitle, $profileNameOnly] = $parseLeadCustomerName(trim((string) ($row['customer_name'] ?? '')));
                } else {
                    $profileTitle = trim((string) ($row['title'] ?? ''));
                }
                if ($push(
                    $profileNameOnly !== '' ? $profileNameOnly : $profileName,
                    $profileEmail,
                    $profileMobile,
                    'lead',
                    (int) $row['lead_id'],
                    $profileTitle,
                    $profileName !== '' ? $profileName : trim($profileTitle . ($profileTitle !== '' && $profileNameOnly !== '' ? ' ' : '') . $profileNameOnly)
                )) {
                    break;
                }
            }
            $profileRes->free();
        }
    }

    $familyRes = $conn->query(
        "SELECT id, lead_id, contact_id, title, first_name, last_name, email, mobile
         FROM crm_contact_family
         WHERE first_name LIKE $like OR last_name LIKE $like OR email LIKE $like OR mobile LIKE $like
         ORDER BY id DESC
         LIMIT $lim"
    );
    if ($familyRes) {
        while ($row = $familyRes->fetch_assoc()) {
            $source = ((int) ($row['contact_id'] ?? 0)) > 0 ? 'manual' : 'lead';
            $refId = $source === 'manual' ? (int) $row['contact_id'] : (int) $row['lead_id'];
            $familyNameOnly = trim(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? ''));
            if ($push(
                $familyNameOnly,
                (string) ($row['email'] ?? ''),
                (string) ($row['mobile'] ?? ''),
                $source,
                $refId,
                (string) ($row['title'] ?? ''),
                lcDisplayName($row)
            )) {
                break;
            }
        }
        $familyRes->free();
    }

    return array_slice($items, 0, $limit);
}

function lcCollectPersonFields()
{
    $name = trim($_POST['name'] ?? '');
    return [
        'title' => trim($_POST['title'] ?? ''),
        'first_name' => $name,
        'last_name' => '',
        'email' => trim($_POST['email'] ?? ''),
        'mobile' => trim($_POST['mobile'] ?? ''),
        'date_of_birth' => lcNullableDate($_POST['date_of_birth'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''),
        'address_line1' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
    ];
}

function lcDisplayName($row)
{
    if (!$row || !is_array($row)) {
        return '';
    }
    $name = trim(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? ''));
    $title = trim($row['title'] ?? '');
    return trim($title . ($title && $name ? ' ' : '') . $name);
}
