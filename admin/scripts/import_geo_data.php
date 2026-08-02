<?php

/**
 * Import countries, states, and cities from CountriesNow API into local DB.
 *
 * Usage:
 *   php admin/scripts/import_geo_data.php
 *   php admin/scripts/import_geo_data.php --cities=country
 *   php admin/scripts/import_geo_data.php --cities=state
 *   php admin/scripts/import_geo_data.php --cities=both
 *   php admin/scripts/import_geo_data.php --only=countries
 */

if (php_sapi_name() === 'cli' && empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/geo_locations.php';

set_time_limit(0);

$options = getopt('', ['only:', 'cities:', 'delay:']);
$only = isset($options['only']) ? strtolower((string) $options['only']) : 'all';
$citiesMode = isset($options['cities']) ? strtolower((string) $options['cities']) : 'country';
$delayMs = isset($options['delay']) ? max(0, (int) $options['delay']) : 150;

$log = static function (string $message): void {
    echo '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
};

geoEnsureTables($conn);
$log('Geo tables ready.');

if ($only === 'all' || $only === 'countries') {
    $log('Importing countries and states...');
    $result = geoImportCountriesAndStates($conn, $log);
    $log('Done countries/states: ' . $result['countries'] . ' countries, ' . $result['states'] . ' states.');
}

if ($only === 'all' || $only === 'cities') {
    if ($citiesMode === 'country' || $citiesMode === 'both') {
        $log('Importing cities by country (this may take several minutes)...');
        $result = geoImportCitiesByCountry($conn, $log, $delayMs);
        $log('Done country cities: ' . $result['cities'] . ' cities across ' . $result['countries_processed'] . ' countries.');
    }

    if ($citiesMode === 'state' || $citiesMode === 'both') {
        $log('Importing cities by state (this can take 1+ hour)...');
        $result = geoImportCitiesByState($conn, $log, $delayMs);
        $log('Done state cities: ' . $result['cities'] . ' cities across ' . $result['states_processed'] . ' states.');
    }
}

$counts = geoLocationCounts($conn);
$log('Final counts — countries: ' . $counts['countries'] . ', states: ' . $counts['states'] . ', cities: ' . $counts['cities']);
