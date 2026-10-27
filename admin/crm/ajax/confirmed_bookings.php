<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/confirmed_bookings.php';

header('Content-Type: application/json; charset=utf-8');

function cbJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cbJson(false, 'Invalid request method.');
}

$action = (string) ($_POST['action'] ?? '');
$quotationId = (int) ($_POST['quotation_id'] ?? 0);
if ($quotationId <= 0) {
    cbJson(false, 'Invalid booking.');
}

if ($action === 'cancel') {
    if (!cbSetStatus($conn, $quotationId, 'cancelled')) {
        cbJson(false, 'Could not cancel this booking.');
    }
    cbJson(true, 'Booking marked cancelled. The tour, hotels, and payments were left unchanged.', [
        'booking' => cbFind($conn, $quotationId),
    ]);
}

if ($action === 'restore') {
    if (!cbSetStatus($conn, $quotationId, 'active')) {
        cbJson(false, 'Could not restore this booking.');
    }
    cbJson(true, 'Booking restored.', [
        'booking' => cbFind($conn, $quotationId),
    ]);
}

cbJson(false, 'Unknown action.');
