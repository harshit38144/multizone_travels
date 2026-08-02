<?php
/**
 * Legacy route — create city opens as modal on city_master.php
 */
require_once __DIR__ . '/bootstrap.php';

header('Location: city_master.php?open=create');
exit;
