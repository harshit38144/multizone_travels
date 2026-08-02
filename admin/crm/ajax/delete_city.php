<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function cityDeleteJsonResponse($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cityDeleteJsonResponse(false, 'Invalid request method.');
}

require_once __DIR__ . '/../../includes/geo_locations.php';
geoEnsureTables($conn);

$mode = strtolower(trim((string) ($_POST['mode'] ?? 'soft')));
if (!in_array($mode, ['soft', 'restore', 'permanent'], true)) {
    $mode = 'soft';
}

$ids = [];
if (isset($_POST['ids']) && is_array($_POST['ids'])) {
    foreach ($_POST['ids'] as $rawId) {
        $id = (int) $rawId;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
} elseif (isset($_POST['ids']) && is_string($_POST['ids'])) {
    foreach (explode(',', $_POST['ids']) as $rawId) {
        $id = (int) trim($rawId);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
} else {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $ids[] = $id;
    }
}

$ids = array_values(array_unique($ids));
if (!$ids) {
    cityDeleteJsonResponse(false, 'Invalid city ID.');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

if ($mode === 'restore') {
    $sql = "UPDATE `cities`
            SET `is_deleted` = 0,
                `deleted_at` = NULL,
                `deleted_by_country` = 0,
                `is_active` = 1,
                `updated_at` = NOW()
            WHERE `id` IN ({$placeholders})
              AND COALESCE(`is_deleted`, 0) = 1";
    $failMsg = 'Could not restore cit' . (count($ids) > 1 ? 'ies' : 'y') . '.';
    $emptyMsg = 'City not found or not deleted.';
    $okOne = 'City restored successfully.';
    $okManyTpl = '%d cities restored successfully.';
} elseif ($mode === 'permanent') {
    $sql = "DELETE FROM `cities`
            WHERE `id` IN ({$placeholders})
              AND COALESCE(`is_deleted`, 0) = 1";
    $failMsg = 'Could not permanently delete cit' . (count($ids) > 1 ? 'ies' : 'y') . '.';
    $emptyMsg = 'City not found in deleted list.';
    $okOne = 'City permanently deleted.';
    $okManyTpl = '%d cities permanently deleted.';
} else {
    $sql = "UPDATE `cities`
            SET `is_deleted` = 1,
                `deleted_at` = NOW(),
                `deleted_by_country` = 0,
                `is_active` = 0,
                `updated_at` = NOW()
            WHERE `id` IN ({$placeholders})
              AND COALESCE(`is_deleted`, 0) = 0";
    $failMsg = 'Could not delete cit' . (count($ids) > 1 ? 'ies' : 'y') . '.';
    $emptyMsg = 'City not found or already deleted.';
    $okOne = 'City deleted successfully.';
    $okManyTpl = '%d cities deleted successfully.';
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    cityDeleteJsonResponse(false, 'Could not prepare request.');
}

$stmt->bind_param($types, ...$ids);

if (!$stmt->execute()) {
    $err = $conn->error;
    $stmt->close();
    cityDeleteJsonResponse(false, $failMsg . ($err !== '' ? ' ' . $err : ''));
}

$affected = (int) $stmt->affected_rows;
$stmt->close();

if ($affected === 0) {
    cityDeleteJsonResponse(false, $emptyMsg);
}

$message = count($ids) > 1 ? sprintf($okManyTpl, $affected) : $okOne;

$_SESSION['city_flash'] = $message;
$_SESSION['city_flash_type'] = 'success';

cityDeleteJsonResponse(true, $message, [
    'affected' => $affected,
    'mode' => $mode,
]);
