<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/lead_contacts_db.php';

header('Content-Type: application/json; charset=utf-8');

lcEnsureContactTables($conn);

$query = trim((string) ($_GET['q'] ?? ''));
$limit = min(30, max(5, (int) ($_GET['limit'] ?? 20)));
$rows = lcListReferralContacts($conn, $query, $limit);

echo json_encode([
    'success' => true,
    'data' => $rows,
], JSON_UNESCAPED_UNICODE);
