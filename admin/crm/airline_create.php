<?php
/**
 * Legacy route — create airline opens as modal on airline_master.php
 */
require_once __DIR__ . '/bootstrap.php';

header('Location: airline_master.php?open=create');
exit;
