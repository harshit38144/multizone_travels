<?php
if (empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/geo_locations.php';
print_r(geoLocationCounts($conn));
