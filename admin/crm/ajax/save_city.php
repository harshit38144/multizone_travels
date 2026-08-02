<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function cityJsonResponse($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cityJsonResponse(false, 'Invalid request method.');
}

require_once __DIR__ . '/../../includes/geo_locations.php';
geoEnsureTables($conn);

$id = (int) ($_POST['id'] ?? 0);
$countryId = (int) ($_POST['country_id'] ?? 0);
$stateId = isset($_POST['state_id']) && $_POST['state_id'] !== '' ? (int) $_POST['state_id'] : null;
$name = trim($_POST['city_name'] ?? '');
$airportCode = strtoupper(trim($_POST['airport_code'] ?? ''));

if ($countryId <= 0) {
    cityJsonResponse(false, 'Please select a country.');
}

if ($name === '') {
    cityJsonResponse(false, 'City name is required.');
}

$countryCheck = $conn->prepare('SELECT id FROM countries WHERE id = ? LIMIT 1');
$countryCheck->bind_param('i', $countryId);
$countryCheck->execute();
$countryRes = $countryCheck->get_result();
if (!$countryRes->fetch_assoc()) {
    cityJsonResponse(false, 'Selected country is invalid.');
}
$countryCheck->close();

if ($stateId !== null && $stateId > 0) {
    $stateCheck = $conn->prepare('SELECT id FROM states WHERE id = ? AND country_id = ? LIMIT 1');
    $stateCheck->bind_param('ii', $stateId, $countryId);
    $stateCheck->execute();
    $stateRes = $stateCheck->get_result();
    if (!$stateRes->fetch_assoc()) {
        cityJsonResponse(false, 'Selected state is invalid for this country.');
    }
    $stateCheck->close();
} else {
    $stateId = null;
}

$airportCode = $airportCode !== '' ? substr($airportCode, 0, 8) : '';
$isActive = isset($_POST['is_active']) ? ((int) $_POST['is_active'] ? 1 : 0) : 1;
$timezone = trim((string) ($_POST['timezone'] ?? ''));
$region = trim((string) ($_POST['region'] ?? ''));
$createdBy = trim((string) ($_SESSION['name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin User'));
if ($createdBy === '') {
    $createdBy = 'Admin User';
}

if ($id > 0) {
    if ($stateId === null) {
        $stmt = $conn->prepare(
            'UPDATE cities SET country_id = ?, state_id = NULL, name = ?, airport_code = ?, is_active = ?, timezone = ?, region = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('ississi', $countryId, $name, $airportCode, $isActive, $timezone, $region, $id);
    } else {
        $stmt = $conn->prepare(
            'UPDATE cities SET country_id = ?, state_id = ?, name = ?, airport_code = ?, is_active = ?, timezone = ?, region = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->bind_param('iississi', $countryId, $stateId, $name, $airportCode, $isActive, $timezone, $region, $id);
    }

    if (!$stmt->execute()) {
        cityJsonResponse(false, 'Could not update city. ' . $conn->error);
    }
    $stmt->close();

    $_SESSION['city_flash'] = 'City updated successfully.';
    $_SESSION['city_flash_type'] = 'success';
    cityJsonResponse(true, 'City updated successfully.', ['id' => $id]);
}

if ($stateId === null) {
    $stmt = $conn->prepare(
        'INSERT INTO cities (country_id, state_id, name, airport_code, is_active, timezone, region, created_by) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ississs', $countryId, $name, $airportCode, $isActive, $timezone, $region, $createdBy);
} else {
    $stmt = $conn->prepare(
        'INSERT INTO cities (country_id, state_id, name, airport_code, is_active, timezone, region, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('iississs', $countryId, $stateId, $name, $airportCode, $isActive, $timezone, $region, $createdBy);
}

if (!$stmt->execute()) {
    if ($conn->errno === 1062) {
        cityJsonResponse(false, 'This city already exists for the selected country/state.');
    }
    cityJsonResponse(false, 'Could not save city. ' . $conn->error);
}

$newId = (int) $conn->insert_id;
$stmt->close();

$_SESSION['city_flash'] = 'City added successfully.';
$_SESSION['city_flash_type'] = 'success';
cityJsonResponse(true, 'City created successfully.', ['id' => $newId]);
