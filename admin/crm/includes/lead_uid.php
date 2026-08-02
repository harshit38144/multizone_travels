<?php

if (!function_exists('crmLeadUidIsSequential')) {
    function crmLeadUidIsSequential(string $uid): bool
    {
        return (bool) preg_match('/^LD-\d{4}$/', $uid);
    }
}

if (!function_exists('crmFormatLeadUidSerial')) {
    function crmFormatLeadUidSerial(int $serial): string
    {
        return 'LD-' . str_pad((string) max(1, $serial), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('crmEnsureSequentialLeadUids')) {
    /**
     * Renumber leads to LD-0001, LD-0002, … when any row uses a non-sequential UID.
     */
    function crmEnsureSequentialLeadUids(mysqli $conn): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $tableCheck = $conn->query("SHOW TABLES LIKE 'crm_leads'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return;
        }

        $res = $conn->query('SELECT `id`, `lead_uid` FROM `crm_leads` ORDER BY `id` ASC');
        if (!$res || $res->num_rows === 0) {
            return;
        }

        $rows = [];
        $needsFix = false;
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
            if (!crmLeadUidIsSequential((string) ($row['lead_uid'] ?? ''))) {
                $needsFix = true;
            }
        }
        $res->free();

        if (!$needsFix) {
            return;
        }

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $tempUid = 'TMP-' . $id;
            $stmt = $conn->prepare('UPDATE `crm_leads` SET `lead_uid` = ? WHERE `id` = ?');
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('si', $tempUid, $id);
            $stmt->execute();
            $stmt->close();
        }

        $serial = 1;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $newUid = crmFormatLeadUidSerial($serial);
            $stmt = $conn->prepare('UPDATE `crm_leads` SET `lead_uid` = ? WHERE `id` = ?');
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('si', $newUid, $id);
            $stmt->execute();
            $stmt->close();
            $serial++;
        }
    }
}

if (!function_exists('generateLeadUid')) {
    /**
     * Next lead UID in LD-0001 format (sequential, zero-padded).
     */
    function generateLeadUid(mysqli $conn): string
    {
        crmEnsureSequentialLeadUids($conn);

        $next = 1;
        $sql = "SELECT MAX(CAST(SUBSTRING(`lead_uid`, 4) AS UNSIGNED)) AS max_serial
                FROM `crm_leads`
                WHERE `lead_uid` REGEXP '^LD-[0-9]{4}$'";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && $row['max_serial'] !== null && $row['max_serial'] !== '') {
                $next = max(1, (int) $row['max_serial']) + 1;
            }
            $result->free();
        }

        return crmFormatLeadUidSerial($next);
    }
}
