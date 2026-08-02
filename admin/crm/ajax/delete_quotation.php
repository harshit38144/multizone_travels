<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/quotation_db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

crmEnsureQuotationTables($conn);

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM `crm_quotations` WHERE `id` = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Could not delete. ' . $conn->error]);
    exit;
}
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => (bool) $ok, 'message' => $ok ? 'Quotation deleted.' : 'Could not delete quotation.']);
