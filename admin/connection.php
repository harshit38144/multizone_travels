<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/database.php';

if ($conn->connect_errno) {
	echo 'connection failed!!';
}
date_default_timezone_set('Asia/Kolkata');

// Footer / WhatsApp columns (only if site_settings exists — avoids uncaught mysqli exceptions on strict hosts)
$ssTable = $conn->query("SHOW TABLES LIKE 'site_settings'");
if ($ssTable && $ssTable->num_rows > 0) {
	$footerCols = [
		'footer_address' => 'TEXT DEFAULT NULL AFTER `copyright_text`',
		'footer_phone' => 'VARCHAR(120) DEFAULT NULL AFTER `footer_address`',
		'footer_email' => 'VARCHAR(255) DEFAULT NULL AFTER `footer_phone`',
		'footer_working_hours' => 'VARCHAR(255) DEFAULT NULL AFTER `footer_email`',
		'footer_newsletter_heading' => 'VARCHAR(255) DEFAULT NULL AFTER `footer_working_hours`',
		'footer_newsletter_placeholder' => 'VARCHAR(255) DEFAULT NULL AFTER `footer_newsletter_heading`',
		'whatsapp_phone' => 'VARCHAR(50) DEFAULT NULL AFTER `footer_newsletter_placeholder`',
	];
	foreach ($footerCols as $col => $ddl) {
		$chk = $conn->query('SHOW COLUMNS FROM `site_settings` LIKE \'' . $conn->real_escape_string($col) . '\'');
		if ($chk && $chk->num_rows == 0) {
			$conn->query('ALTER TABLE `site_settings` ADD `' . $col . '` ' . $ddl);
		}
	}
	$settingsRes = $conn->query('SELECT * FROM site_settings WHERE id=1');
} else {
	$settingsRes = false;
}

// Fetch site settings globally
$siteSettings = [];
if ($settingsRes && $settingsRes->num_rows > 0) {
	$siteSettings = $settingsRes->fetch_assoc();
}
