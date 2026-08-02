<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/supplier_db.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid supplier.']);
    exit;
}

crmEnsureSupplierTables($conn);

$stmt = $conn->prepare('SELECT * FROM crm_suppliers WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Could not load supplier.']);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Supplier not found.']);
    exit;
}

$contacts = json_decode((string) ($row['contacts_json'] ?? '[]'), true);
$supplierOf = json_decode((string) ($row['supplier_of_json'] ?? '[]'), true);
$places = json_decode((string) ($row['places_json'] ?? '[]'), true);
$supplierTypes = crmSupplierNormalizeTypes($row['supplier_type'] ?? '');

echo json_encode([
    'success' => true,
    'data' => [
        'id' => (int) $row['id'],
        'name' => (string) ($row['name'] ?? ''),
        'supplier_type' => (string) ($row['supplier_type'] ?? ''),
        'supplier_types' => $supplierTypes,
        'company_name' => (string) ($row['company_name'] ?? ''),
        'website' => (string) ($row['website'] ?? ''),
        'city_id' => (int) ($row['city_id'] ?? 0),
        'city_name' => (string) ($row['city_name'] ?? ''),
        'country_name' => (string) ($row['country_name'] ?? ''),
        'physical_address' => (string) ($row['physical_address'] ?? ''),
        'contacts' => crmSupplierNormalizeContacts(is_array($contacts) ? $contacts : []),
        'supplier_of' => crmSupplierNormalizeSupplierOf(is_array($supplierOf) ? $supplierOf : []),
        'places' => crmSupplierNormalizePlaces(is_array($places) ? $places : []),
        'internal_notes' => (string) ($row['internal_notes'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 1),
    ],
]);
