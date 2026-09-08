<?php
/**
 * Create a lead source from the Create Lead modal (AJAX).
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function lsJson(bool $ok, string $message = '', array $extra = []): void
{
	echo json_encode(array_merge([
		'success' => $ok,
		'message' => $message,
	], $extra), JSON_UNESCAPED_UNICODE);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	lsJson(false, 'Invalid request method.');
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
	lsJson(false, 'Database connection failed.');
}

$conn->query("CREATE TABLE IF NOT EXISTS `crm_lead_sources` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(120) NOT NULL,
	`display_order` INT NOT NULL DEFAULT 0,
	`is_active` TINYINT(1) NOT NULL DEFAULT 1,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uniq_crm_lead_source_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$name = trim((string) ($_POST['name'] ?? ''));
if ($name === '') {
	lsJson(false, 'Lead source name is required.');
}
if (function_exists('mb_strlen') ? mb_strlen($name) > 120 : strlen($name) > 120) {
	lsJson(false, 'Lead source name is too long (max 120 characters).');
}

$existing = null;
$check = $conn->prepare('SELECT `id`, `name`, `is_active` FROM `crm_lead_sources` WHERE LOWER(`name`) = LOWER(?) LIMIT 1');
if ($check) {
	$check->bind_param('s', $name);
	$check->execute();
	$res = $check->get_result();
	$existing = $res ? $res->fetch_assoc() : null;
	$check->close();
}

if ($existing) {
	$id = (int) ($existing['id'] ?? 0);
	$existingName = trim((string) ($existing['name'] ?? $name));
	$isActive = (int) ($existing['is_active'] ?? 0);
	if ($isActive !== 1 && $id > 0) {
		$reactivate = $conn->prepare('UPDATE `crm_lead_sources` SET `is_active` = 1 WHERE `id` = ?');
		if ($reactivate) {
			$reactivate->bind_param('i', $id);
			$reactivate->execute();
			$reactivate->close();
		}
	}
	lsJson(true, 'Lead source already exists and was selected.', [
		'data' => [
			'id' => $id,
			'name' => $existingName,
		],
	]);
}

$displayOrder = 1;
$orderRes = $conn->query('SELECT COALESCE(MAX(`display_order`), 0) + 1 AS next_order FROM `crm_lead_sources`');
if ($orderRes) {
	$displayOrder = max(1, (int) ($orderRes->fetch_assoc()['next_order'] ?? 1));
}

$isActive = 1;
$stmt = $conn->prepare('INSERT INTO `crm_lead_sources` (`name`, `display_order`, `is_active`) VALUES (?, ?, ?)');
if (!$stmt) {
	lsJson(false, 'Could not prepare lead source insert.');
}
$stmt->bind_param('sii', $name, $displayOrder, $isActive);
$ok = $stmt->execute();
$errno = $conn->errno;
$newId = (int) $stmt->insert_id;
$stmt->close();

if (!$ok) {
	lsJson(false, $errno === 1062 ? 'This lead source already exists.' : 'Could not add lead source.');
}

lsJson(true, 'Lead source added successfully.', [
	'data' => [
		'id' => $newId,
		'name' => $name,
	],
]);
