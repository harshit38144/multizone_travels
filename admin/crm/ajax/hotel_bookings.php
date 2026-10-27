<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/hotel_bookings.php';

header('Content-Type: application/json; charset=utf-8');

function hbJson(bool $ok, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hbJson(false, 'Invalid request.');
}

hbEnsureTable($conn);
$action = trim((string) ($_POST['action'] ?? ''));
$code = trim((string) ($_POST['code'] ?? ''));

if ($action === 'save') {
    $result = hbSaveMeta($conn, $code, $_POST);
    hbJson(!empty($result['ok']), (string) ($result['message'] ?? 'Could not save.'), ['booking' => $result['booking'] ?? null]);
}

if ($action === 'payment') {
    $result = hbRecordPayment($conn, $code, $_POST);
    hbJson(!empty($result['ok']), (string) ($result['message'] ?? 'Could not save the payment.'), ['booking' => $result['booking'] ?? null]);
}

if ($action === 'reconfirm') {
    $result = hbSaveMeta($conn, $code, ['reconfirm_status' => 'confirmed']);
    hbJson(!empty($result['ok']), !empty($result['ok']) ? 'Marked as reconfirmed.' : (string) ($result['message'] ?? 'Could not update.'), ['booking' => $result['booking'] ?? null]);
}

if ($action === 'cancel') {
    $result = hbSaveMeta($conn, $code, ['booking_status' => 'cancelled']);
    hbJson(!empty($result['ok']), !empty($result['ok']) ? 'Hotel booking cancelled. The tour itself was left unchanged.' : (string) ($result['message'] ?? 'Could not cancel.'), ['booking' => $result['booking'] ?? null]);
}

if ($action === 'reminder') {
    $result = hbScheduleReminder($conn, $code, (string) ($_POST['reminder_at'] ?? ''));
    hbJson(!empty($result['ok']), (string) ($result['message'] ?? 'Could not schedule the reminder.'), ['booking' => $result['booking'] ?? null]);
}

if ($action === 'one') {
    $booking = hbFind($conn, $code);
    if (!$booking) {
        hbJson(false, 'Booking not found.');
    }
    hbJson(true, 'OK', ['booking' => $booking]);
}

if ($action === 'prepare') {
    $booking = hbFind($conn, $code);
    if (!$booking) {
        hbJson(false, 'Booking not found.');
    }
    if (!hbEnsureServiceRow($conn, (int) $booking['quotation_id'], (string) $booking['service_uid'])) {
        hbJson(false, 'This hotel is not on the confirmed tour anymore.');
    }
    hbJson(true, 'OK', [
        'quotation_id' => (int) $booking['quotation_id'],
        'service_uid' => (string) $booking['service_uid'],
    ]);
}

if ($action === 'bulk') {
    $codes = $_POST['codes'] ?? [];
    if (!is_array($codes)) {
        $codes = [$codes];
    }
    $bulk = (string) ($_POST['bulk'] ?? '');
    $updated = [];
    foreach ($codes as $one) {
        $one = trim((string) $one);
        if ($one === '') {
            continue;
        }
        if ($bulk === 'reconfirm') {
            $result = hbSaveMeta($conn, $one, ['reconfirm_status' => 'confirmed']);
        } elseif ($bulk === 'cancel') {
            $result = hbSaveMeta($conn, $one, ['booking_status' => 'cancelled']);
        } else {
            hbJson(false, 'Choose a bulk action.');
        }
        if (!empty($result['booking'])) {
            $updated[] = $result['booking'];
        }
    }
    hbJson(true, 'Updated.', ['bookings' => $updated]);
}

hbJson(false, 'Unknown action.');
