<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lcJson(false, 'Invalid request.');
}

$contactId = (int) ($_POST['contact_id'] ?? 0);
if ($contactId <= 0 || !lcGetManualContact($conn, $contactId)) {
    lcJson(false, 'Contact not found.');
}

$conn->query("DELETE FROM crm_contact_family WHERE contact_id = " . $contactId);
$stmt = $conn->prepare("DELETE FROM crm_contacts WHERE id = ?");
$stmt->bind_param('i', $contactId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    lcJson(false, 'Could not delete contact.');
}

lcJson(true, 'Contact deleted.');
