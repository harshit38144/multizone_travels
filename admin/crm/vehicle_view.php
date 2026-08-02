<?php
/**
 * Legacy route — view vehicle opens as modal on vehicle_master.php
 */
require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
	header('Location: vehicle_master.php?open=view&id=' . $id);
} else {
	header('Location: vehicle_master.php');
}
exit;
