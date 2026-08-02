<?php
/**
 * Legacy route — create/edit sightseeing opens as modal on sightseeing_master.php
 */
require_once __DIR__ . '/bootstrap.php';

$edit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($edit > 0) {
	header('Location: sightseeing_master.php?open=edit&id=' . $edit);
	exit;
}

$dest = isset($_GET['destination']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['destination']) : '';
$params = ['open' => 'create'];
if ($dest !== '') {
	$params['destination'] = $dest;
}
header('Location: sightseeing_master.php?' . http_build_query($params));
exit;
