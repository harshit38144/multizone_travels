<?php
/**
 * Legacy route — create/edit open in modal on testimonial_master.php
 */
require_once __DIR__ . '/bootstrap.php';

if (isset($_GET['edit']) && (int) $_GET['edit'] > 0) {
	header('Location: testimonial_master.php?open=edit&id=' . (int) $_GET['edit']);
	exit;
}

header('Location: testimonial_master.php?open=create');
exit;
