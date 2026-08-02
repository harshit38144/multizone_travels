<?php

require_once __DIR__ . '/lead_intake_db.php';
require_once __DIR__ . '/lead_intake_fields.php';
require_once __DIR__ . '/lead_uid.php';

/**
 * Insert a CRM lead from POST-like payload array. Returns ['success'=>bool, 'message'=>..., 'lead'=>...].
 */
function crmSaveLeadFromPayload(mysqli $conn, array $payload, array $meta = [])
{
    crmEnsureLeadIntakeTables($conn);

    $createTableSql = "CREATE TABLE IF NOT EXISTS `crm_leads` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `lead_uid` VARCHAR(40) NOT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_phone` VARCHAR(30) NOT NULL,
        `customer_email` VARCHAR(190) DEFAULT NULL,
        `lead_source` VARCHAR(60) DEFAULT NULL,
        `referred_by` VARCHAR(150) DEFAULT NULL,
        `assign_to` VARCHAR(120) DEFAULT NULL,
        `services` TEXT,
        `itinerary_total_nights` INT DEFAULT 0,
        `itinerary_total_days` INT DEFAULT 0,
        `payload_json` LONGTEXT,
        `created_by_id` INT DEFAULT NULL,
        `created_by_name` VARCHAR(120) DEFAULT NULL,
        `intake_submission_id` INT UNSIGNED DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_crm_lead_uid` (`lead_uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($createTableSql);

    $customerName = trim($payload['customer_name'] ?? '');
    $customerPhone = trim($payload['customer_phone'] ?? '');
    $customerEmail = trim($payload['customer_email'] ?? '');
    $leadSource = trim($meta['lead_source'] ?? ($payload['lead_source'] ?? ''));
    $referredBy = trim($meta['referred_by'] ?? ($payload['referred_by'] ?? ''));
    $assignTo = trim($meta['assign_to'] ?? ($payload['assign_to'] ?? ''));
    if ($assignTo === '') {
        $assignTo = trim((string) ($meta['created_by_name'] ?? ''));
    }
    if ($assignTo === '') {
        $assignTo = 'Admin';
    }
    $services = crmInferPayloadServices($payload);
    $payload['services'] = $services;
    $itineraryTotalNights = max(0, (int) ($payload['itinerary_total_nights'] ?? 0));
    $itineraryTotalDays = max(0, (int) ($payload['itinerary_total_days'] ?? 0));
    $intakeSubmissionId = isset($meta['intake_submission_id']) ? (int) $meta['intake_submission_id'] : 0;

    if ($customerName === '') {
        return ['success' => false, 'message' => 'Customer name is required.'];
    }
    if ($customerPhone === '') {
        return ['success' => false, 'message' => 'Customer phone is required.'];
    }

    $leadUid = generateLeadUid($conn);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($payloadJson === false) {
        $payloadJson = '{}';
    }
    $servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE);
    if ($servicesJson === false) {
        $servicesJson = '[]';
    }

    $createdById = isset($meta['created_by_id']) ? (int) $meta['created_by_id'] : 0;
    $createdByName = isset($meta['created_by_name']) ? trim((string) $meta['created_by_name']) : '';

    $sql = "INSERT INTO `crm_leads`
        (`lead_uid`, `customer_name`, `customer_phone`, `customer_email`, `lead_source`, `referred_by`, `assign_to`,
         `services`, `itinerary_total_nights`, `itinerary_total_days`, `payload_json`, `created_by_id`, `created_by_name`, `intake_submission_id`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not prepare save. ' . $conn->error];
    }

    $stmt->bind_param(
        'ssssssssiisisi',
        $leadUid,
        $customerName,
        $customerPhone,
        $customerEmail,
        $leadSource,
        $referredBy,
        $assignTo,
        $servicesJson,
        $itineraryTotalNights,
        $itineraryTotalDays,
        $payloadJson,
        $createdById,
        $createdByName,
        $intakeSubmissionId
    );

    if (!$stmt->execute()) {
        $err = $conn->error;
        $stmt->close();
        return ['success' => false, 'message' => 'Could not save lead. ' . $err];
    }

    $newId = (int) $stmt->insert_id;
    $stmt->close();

    return [
        'success' => true,
        'message' => 'Lead saved successfully.',
        'lead' => [
            'id' => $newId,
            'lead_uid' => $leadUid,
            'customer_name' => $customerName,
        ],
    ];
}
