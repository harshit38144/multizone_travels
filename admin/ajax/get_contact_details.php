<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/lead_contacts_db.php';

lcRequireAdmin();
lcEnsureContactTables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    lcJson(false, 'Invalid request.');
}

$ref = lcContactRefFromRequest();
$source = $ref['source'];
$refId = $ref['ref_id'];

if ($refId <= 0) {
    lcJson(false, 'Invalid contact.');
}

if ($source === 'manual') {
    $contact = lcGetManualContact($conn, $refId);
    if (!$contact) {
        lcJson(false, 'Contact not found.');
    }
    lcJson(true, '', [
        'source' => 'manual',
        'ref_id' => $refId,
        'contact' => [
            'customer_name' => lcDisplayName($contact),
            'customer_phone' => $contact['mobile'] ?? '',
            'customer_email' => $contact['email'] ?? '',
        ],
        'profile' => lcManualContactAsProfile($contact),
        'family' => lcGetFamilyByContact($conn, $refId),
    ]);
}

$lead = lcGetLead($conn, $refId);
if (!$lead) {
    lcJson(false, 'Lead not found.');
}

lcJson(true, '', [
    'source' => 'lead',
    'ref_id' => $refId,
    'lead' => $lead,
    'profile' => lcGetProfile($conn, $refId),
    'family' => lcGetFamily($conn, $refId),
]);
