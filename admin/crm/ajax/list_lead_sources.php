<?php
/**
 * List active lead sources for Create/Edit Lead dropdown refresh.
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
	echo json_encode(['success' => false, 'message' => 'Database connection failed.', 'data' => []]);
	exit;
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

$sources = [];
$res = $conn->query("SELECT `id`, `name` FROM `crm_lead_sources` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `name` ASC");
if ($res) {
	while ($row = $res->fetch_assoc()) {
		$name = trim((string) ($row['name'] ?? ''));
		if ($name === '') {
			continue;
		}
		$sources[] = [
			'id' => (int) ($row['id'] ?? 0),
			'name' => $name,
		];
	}
}

echo json_encode([
	'success' => true,
	'data' => $sources,
], JSON_UNESCAPED_UNICODE);
