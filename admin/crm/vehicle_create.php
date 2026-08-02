<?php
/**
 * Legacy route — create/edit vehicle opens as modal on vehicle_master.php
 */
require_once __DIR__ . '/bootstrap.php';

$edit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($edit > 0) {
	header('Location: vehicle_master.php?open=edit&id=' . $edit);
} else {
	header('Location: vehicle_master.php?open=create');
}
exit;
