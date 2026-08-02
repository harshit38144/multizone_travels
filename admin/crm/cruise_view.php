<?php
/**
 * Legacy route — view cruise opens as modal on cruise_master.php
 */
require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
	header('Location: cruise_master.php?open=view&id=' . $id);
} else {
	header('Location: cruise_master.php');
}
exit;
