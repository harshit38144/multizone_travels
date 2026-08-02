<?php

/** @return array<string, string> */
function crmQuotationTermsFields(): array
{
    return [
        'inclusion' => 'Inclusion',
        'exclusion' => 'Exclusion',
        'payment_policy' => 'Payment Policy',
        'cancellation_policy' => 'Cancellation Policy',
        'terms_conditions' => 'Quotation Terms and Conditions',
        'other_details' => 'Other Details',
    ];
}

function crmEnsureQuotationTermsMasterTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `crm_quotation_terms_master` (
        `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `inclusion` MEDIUMTEXT,
        `exclusion` MEDIUMTEXT,
        `payment_policy` MEDIUMTEXT,
        `cancellation_policy` MEDIUMTEXT,
        `terms_conditions` MEDIUMTEXT,
        `other_details` MEDIUMTEXT,
        `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chk = $conn->query('SELECT id FROM crm_quotation_terms_master WHERE id = 1 LIMIT 1');
    if ($chk && $chk->num_rows === 0) {
        $conn->query('INSERT INTO crm_quotation_terms_master (id) VALUES (1)');
    }
}

/** @return array<string, string> */
function crmGetQuotationTermsMaster(mysqli $conn): array
{
    crmEnsureQuotationTermsMasterTable($conn);

    $defaults = [];
    foreach (array_keys(crmQuotationTermsFields()) as $field) {
        $defaults[$field] = '';
    }

    $res = $conn->query('SELECT * FROM crm_quotation_terms_master WHERE id = 1 LIMIT 1');
    if (!$res || $res->num_rows === 0) {
        return $defaults;
    }

    $row = $res->fetch_assoc();
    foreach ($defaults as $field => $_) {
        $defaults[$field] = (string) ($row[$field] ?? '');
    }

    return $defaults;
}

/** @param array<string, string> $data */
function crmSaveQuotationTermsMaster(mysqli $conn, array $data): bool
{
    crmEnsureQuotationTermsMasterTable($conn);

    $fields = array_keys(crmQuotationTermsFields());
    $sets = [];
    $values = [];
    foreach ($fields as $field) {
        $sets[] = "`$field` = ?";
        $values[] = (string) ($data[$field] ?? '');
    }
    $values[] = 1;

    $sql = 'UPDATE crm_quotation_terms_master SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $types = str_repeat('s', count($fields)) . 'i';
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();

    return (bool) $ok;
}
