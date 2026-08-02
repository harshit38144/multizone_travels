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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    supplierJson(false, 'Invalid supplier.');
}

crmEnsureSupplierTables($conn);

$stmt = $conn->prepare('DELETE FROM crm_suppliers WHERE id = ? LIMIT 1');
if (!$stmt) {
    supplierJson(false, 'Could not prepare delete.');
}
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
    $stmt->close();
    supplierJson(false, 'Could not delete supplier.');
}
$stmt->close();

supplierJson(true, 'Supplier deleted successfully.');
