<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/geo_locations.php';

header('Content-Type: application/json; charset=utf-8');

function countryActionResponse($success, $message)
{
    echo json_encode([
        'success' => (bool) $success,
        'message' => (string) $message,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    countryActionResponse(false, 'Invalid request method.');
}

geoEnsureTables($conn);

$id = (int) ($_POST['id'] ?? 0);
$mode = strtolower(trim((string) ($_POST['mode'] ?? 'soft')));
if ($id <= 0) {
    countryActionResponse(false, 'Invalid country.');
}
if (!in_array($mode, ['soft', 'restore', 'permanent'], true)) {
    countryActionResponse(false, 'Invalid action.');
}

$conn->begin_transaction();

try {
    if ($mode === 'permanent') {
        $checkStmt = $conn->prepare(
            'SELECT id FROM countries
             WHERE id = ? AND COALESCE(is_deleted, 0) = 1
             FOR UPDATE'
        );
        if (!$checkStmt) {
            throw new RuntimeException('Could not verify country.');
        }
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $checkStmt->store_result();
        $countryExists = $checkStmt->num_rows > 0;
        $checkStmt->close();
        if (!$countryExists) {
            throw new RuntimeException('Country was not found in deleted items.');
        }

        $cityStmt = $conn->prepare('DELETE FROM cities WHERE country_id = ?');
        $stateStmt = $conn->prepare('DELETE FROM states WHERE country_id = ?');
        $countryStmt = $conn->prepare('DELETE FROM countries WHERE id = ?');
        if (!$cityStmt || !$stateStmt || !$countryStmt) {
            throw new RuntimeException('Could not prepare permanent deletion.');
        }

        foreach ([$cityStmt, $stateStmt, $countryStmt] as $deleteStmt) {
            $deleteStmt->bind_param('i', $id);
            if (!$deleteStmt->execute()) {
                throw new RuntimeException($deleteStmt->error);
            }
            $deleteStmt->close();
        }

        $conn->commit();
        countryActionResponse(true, 'Country, states, and all its cities permanently deleted.');
    }

    if ($mode === 'restore') {
        $stmt = $conn->prepare(
            'UPDATE countries SET is_deleted = 0, deleted_at = NULL
             WHERE id = ? AND COALESCE(is_deleted, 0) = 1'
        );
        $successMessage = 'Country and its cities restored successfully.';
    } else {
        $stmt = $conn->prepare(
            'UPDATE countries SET is_deleted = 1, deleted_at = NOW()
             WHERE id = ? AND COALESCE(is_deleted, 0) = 0'
        );
        $successMessage = 'Country and its cities moved to deleted items.';
    }

    if (!$stmt) {
        throw new RuntimeException('Could not prepare country action.');
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $countryAffected = (int) $stmt->affected_rows;
    $stmt->close();

    if ($countryAffected === 0) {
        throw new RuntimeException('Country was not found or already processed.');
    }

    if ($mode === 'soft') {
        $cityStmt = $conn->prepare(
            'UPDATE cities
             SET is_deleted = 1, deleted_at = NOW(), deleted_by_country = 1,
                 is_active = 0, updated_at = NOW()
             WHERE country_id = ? AND COALESCE(is_deleted, 0) = 0'
        );
    } elseif ($mode === 'restore') {
        $cityStmt = $conn->prepare(
            'UPDATE cities
             SET is_deleted = 0, deleted_at = NULL, deleted_by_country = 0,
                 is_active = 1, updated_at = NOW()
             WHERE country_id = ? AND COALESCE(deleted_by_country, 0) = 1'
        );
    }

    $cityStmt->bind_param('i', $id);
    if (!$cityStmt->execute()) {
        throw new RuntimeException($cityStmt->error);
    }
    $cityStmt->close();

    $conn->commit();
    countryActionResponse(true, $successMessage);
} catch (Throwable $e) {
    $conn->rollback();
    countryActionResponse(false, 'Could not complete country action. ' . $e->getMessage());
}
