<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/supplier_db.php';

header('Content-Type: application/json; charset=utf-8');

function supplierJson($success, $message, $extra = [])
{
    echo json_encode(array_merge(['success' => (bool) $success, 'message' => (string) $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    supplierJson(false, 'Invalid request method.');
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    supplierJson(false, 'Database connection failed.');
}

try {
    crmEnsureSupplierTables($conn);

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $website = trim($_POST['website'] ?? '');
    $cityId = max(0, (int) ($_POST['city_id'] ?? 0));
    $cityName = trim($_POST['city_name'] ?? '');
    $countryName = trim($_POST['country_name'] ?? '');
    $physicalAddress = trim($_POST['physical_address'] ?? '');
    $internalNotes = mb_substr(trim((string) ($_POST['internal_notes'] ?? '')), 0, 500);
    $isActive = isset($_POST['is_active']) && (int) $_POST['is_active'] === 1 ? 1 : 0;

    if ($name === '') {
        supplierJson(false, 'Supplier name is required.');
    }

    $typesRaw = $_POST['supplier_types_json'] ?? ($_POST['supplier_type'] ?? '[]');
    if (is_string($typesRaw) && $typesRaw !== '' && $typesRaw[0] !== '[') {
        // Legacy single value
        $types = crmSupplierNormalizeTypes($typesRaw);
    } else {
        $decodedTypes = json_decode((string) $typesRaw, true);
        $types = crmSupplierNormalizeTypes(is_array($decodedTypes) ? $decodedTypes : []);
    }
    if (!$types) {
        supplierJson(false, 'Please select at least one supplier type.');
    }
    $supplierType = crmSupplierTypesToStorage($types);

    $contactsRaw = $_POST['contacts_json'] ?? '[]';
    $contactsDecoded = json_decode((string) $contactsRaw, true);
    if ($contactsRaw !== '' && $contactsDecoded === null && json_last_error() !== JSON_ERROR_NONE) {
        supplierJson(false, 'Invalid contact data.');
    }
    $contacts = crmSupplierNormalizeContacts(is_array($contactsDecoded) ? $contactsDecoded : []);

    $hasEmail = false;
    foreach ($contacts as $c) {
        if (trim((string) ($c['email'] ?? '')) !== '') {
            $hasEmail = true;
            break;
        }
    }
    if (!$hasEmail) {
        supplierJson(false, 'At least one contact email is required.');
    }

    $supplierOfRaw = $_POST['supplier_of_json'] ?? '[]';
    $supplierOfDecoded = json_decode((string) $supplierOfRaw, true);
    $supplierOf = crmSupplierNormalizeSupplierOf(is_array($supplierOfDecoded) ? $supplierOfDecoded : []);

    $placesRaw = $_POST['places_json'] ?? '[]';
    $placesDecoded = json_decode((string) $placesRaw, true);
    $places = crmSupplierNormalizePlaces(is_array($placesDecoded) ? $placesDecoded : []);

    $contactsJson = json_encode($contacts, JSON_UNESCAPED_UNICODE);
    $supplierOfJson = json_encode($supplierOf, JSON_UNESCAPED_UNICODE);
    $placesJson = json_encode($places, JSON_UNESCAPED_UNICODE);

    if ($id > 0) {
        $stmt = $conn->prepare(
            'UPDATE crm_suppliers SET
                name = ?, supplier_type = ?, company_name = ?, website = ?, city_id = ?, city_name = ?, country_name = ?,
                physical_address = ?, contacts_json = ?, supplier_of_json = ?, places_json = ?,
                internal_notes = ?, is_active = ?
             WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            supplierJson(false, 'Could not prepare update: ' . $conn->error);
        }
        $stmt->bind_param(
            'ssssisssssssii',
            $name,
            $supplierType,
            $companyName,
            $website,
            $cityId,
            $cityName,
            $countryName,
            $physicalAddress,
            $contactsJson,
            $supplierOfJson,
            $placesJson,
            $internalNotes,
            $isActive,
            $id
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            supplierJson(false, 'Could not update supplier: ' . $err);
        }
        $stmt->close();
        supplierJson(true, 'Supplier updated successfully.', ['id' => $id]);
    }

    $stmt = $conn->prepare(
        'INSERT INTO crm_suppliers
            (name, supplier_type, company_name, website, city_id, city_name, country_name, physical_address,
             contacts_json, supplier_of_json, places_json, internal_notes, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        supplierJson(false, 'Could not prepare insert: ' . $conn->error);
    }
    $stmt->bind_param(
        'ssssisssssssi',
        $name,
        $supplierType,
        $companyName,
        $website,
        $cityId,
        $cityName,
        $countryName,
        $physicalAddress,
        $contactsJson,
        $supplierOfJson,
        $placesJson,
        $internalNotes,
        $isActive
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        supplierJson(false, 'Could not save supplier: ' . $err);
    }
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    $mailSupplier = null;
    $fetch = $conn->prepare('SELECT * FROM crm_suppliers WHERE id = ? LIMIT 1');
    if ($fetch) {
        $fetch->bind_param('i', $newId);
        $fetch->execute();
        $fres = $fetch->get_result();
        $newRow = $fres ? $fres->fetch_assoc() : null;
        $fetch->close();
        if ($newRow) {
            $mailSupplier = crmSupplierMailCatalogItemFromRow($newRow);
        }
    }

    supplierJson(true, 'Supplier saved successfully.', [
        'id' => $newId,
        'supplier' => $mailSupplier,
    ]);
} catch (Throwable $e) {
    supplierJson(false, 'Save failed: ' . $e->getMessage());
}
