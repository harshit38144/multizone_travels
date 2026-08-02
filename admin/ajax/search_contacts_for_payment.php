<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

$query = trim($_GET['q'] ?? '');
$limit = min(15, max(5, (int) ($_GET['limit'] ?? 10)));
$rows = lcSearchContactsForPayment($conn, $query, $limit);

lcJson(true, '', ['data' => $rows]);
