<?php
require_once dirname(__DIR__) . '/includes/legacy_sql_staging.php';

$path = crmLegacySqlFilePath();
if ($path === '') {
    echo "SQL file not found\n";
    exit(1);
}

echo 'File: ' . $path . "\n";
echo 'Size: ' . number_format(filesize($path)) . " bytes\n\n";

$counts = crmLegacyEstimateCountsFromFile($path);
foreach ($counts as $table => $count) {
    echo "{$table}: ~{$count}\n";
}

$c = @new mysqli('localhost', 'root', '', 'u560130840_dashboard');
if (!$c->connect_errno) {
    echo "\n(Optional local legacy DB still available for comparison.)\n";
}
