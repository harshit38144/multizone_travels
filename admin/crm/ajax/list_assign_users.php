<?php
/**
 * List active CRM users for the Create/Edit Lead "Assign To" dropdown.
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
	echo json_encode(['success' => false, 'message' => 'Database connection failed.', 'data' => []]);
	exit;
}

$users = [];
$tableRes = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableRes && $tableRes->num_rows > 0) {
	$res = $conn->query("SELECT `username`, `full_name` FROM `users` WHERE `is_deleted` = 0 ORDER BY `full_name` ASC, `username` ASC");
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$username = trim((string) ($row['username'] ?? ''));
			$fullName = trim((string) ($row['full_name'] ?? ''));
			if ($username === '' && $fullName === '') {
				continue;
			}
			$users[] = [
				'username' => $username,
				'full_name' => $fullName,
			];
		}
	}
}

echo json_encode([
	'success' => true,
	'data' => $users,
	'self_name' => trim((string) ($_SESSION['name'] ?? '')),
], JSON_UNESCAPED_UNICODE);
