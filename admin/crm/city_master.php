<?php
/**
 * City Master UI removed from CRM — redirect to Hotels master.
 * City data / AJAX endpoints remain available for quotations & hotels.
 */
require_once __DIR__ . '/bootstrap.php';
header('Location: hotel_master.php');
exit;
