<?php
/**
 * Legacy route — create hotel opens as modal on hotel_master.php
 */
require_once __DIR__ . '/bootstrap.php';

header('Location: hotel_master.php?open=create');
exit;
