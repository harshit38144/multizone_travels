<?php
/**
 * Legacy route — view sightseeing opens as modal on sightseeing_master.php
 */
require_once __DIR__ . '/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
	header('Location: sightseeing_master.php?open=view&id=' . $id);
} else {
	header('Location: sightseeing_master.php');
}
exit;
