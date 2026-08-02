<?php
/**
 * Legacy route — create/edit cruise opens as modal on cruise_master.php
 */
require_once __DIR__ . '/bootstrap.php';

$edit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($edit > 0) {
	header('Location: cruise_master.php?open=edit&id=' . $edit);
} else {
	header('Location: cruise_master.php?open=create');
}
exit;
