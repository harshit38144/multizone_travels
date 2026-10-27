<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/hotel_bookings.php';

$code = trim((string) ($_GET['code'] ?? ''));
$booking = $code !== '' ? hbFind($conn, $code) : null;
function hv($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function hvDate(string $iso): string
{
    if ($iso === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : hv($iso);
}
function hvMoney(float $n): string
{
    return '₹' . number_format($n, $n === floor($n) ? 0 : 2);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $booking ? hv($booking['code']) : 'Hotel voucher' ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #1e293b; margin: 0; background: #f8fafc; }
        .sheet { max-width: 820px; margin: 24px auto; background: #fff; border: 1px solid #e5e7eb; }
        .head { background: #e11d48; color: #fff; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; }
        .head h1 { margin: 0; font-size: 22px; }
        .head small { opacity: 0.9; }
        .body { padding: 22px; }
        h2 { margin: 0 0 8px; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        td { border-bottom: 1px solid #f1f5f9; padding: 8px 4px; vertical-align: top; font-size: 14px; }
        td.k { width: 180px; color: #64748b; font-weight: 700; }
        .actions { padding: 0 22px 22px; }
        button { background: #e11d48; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 700; cursor: pointer; }
        @media print { .actions { display: none; } body { background: #fff; } .sheet { border: 0; margin: 0; } }
    </style>
</head>
<body>
<?php if (!$booking) { ?>
    <div class="sheet"><div class="body"><h2>Hotel booking not found.</h2></div></div>
<?php } else { ?>
    <div class="sheet">
        <div class="head">
            <div>
                <h1>Hotel Voucher</h1>
                <small>Multizone Travels</small>
            </div>
            <div style="text-align:right">
                <strong><?= hv($booking['code']) ?></strong><br>
                <small><?= hv(ucfirst($booking['booking_status'])) ?></small>
            </div>
        </div>
        <div class="body">
            <h2><?= hv($booking['hotel']) ?></h2>
            <div><?= hv(trim($booking['city'] . ($booking['country'] !== '' ? ', ' . $booking['country'] : ''))) ?></div>
            <table>
                <tr><td class="k">Guest</td><td><?= hv($booking['guest']) ?></td></tr>
                <tr><td class="k">Contact</td><td><?= hv($booking['phone']) ?><?= $booking['email'] !== '' ? ' · ' . hv($booking['email']) : '' ?></td></tr>
                <tr><td class="k">Lead / Tour</td><td><?= hv($booking['lead_uid']) ?> · <?= hv($booking['tour']) ?></td></tr>
                <tr><td class="k">Check-in</td><td><?= hvDate($booking['checkin']) ?></td></tr>
                <tr><td class="k">Check-out</td><td><?= hvDate($booking['checkout']) ?></td></tr>
                <tr><td class="k">Nights / Rooms</td><td><?= (int) $booking['nights'] ?> nights · <?= (int) $booking['rooms'] ?> room(s)</td></tr>
                <tr><td class="k">Room type</td><td><?= hv($booking['room_type'] !== '' ? $booking['room_type'] : '—') ?></td></tr>
                <tr><td class="k">Meal plan</td><td><?= hv($booking['meal_plan'] !== '' ? $booking['meal_plan'] : '—') ?></td></tr>
                <tr><td class="k">Supplier</td><td><?= hv($booking['supplier'] !== '' ? $booking['supplier'] : '—') ?></td></tr>
                <tr><td class="k">Booking reference</td><td><?= hv($booking['booking_ref'] !== '' ? $booking['booking_ref'] : '—') ?></td></tr>
                <tr><td class="k">Supplier cost</td><td><?= hvMoney((float) $booking['total']) ?> · Paid <?= hvMoney((float) $booking['paid']) ?> · Due <?= hvMoney((float) $booking['balance']) ?></td></tr>
                <tr><td class="k">Cancellation</td><td><?= hv($booking['cancellation_policy'] !== '' ? $booking['cancellation_policy'] : '—') ?></td></tr>
            </table>
        </div>
        <div class="actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div>
    </div>
<?php } ?>
</body>
</html>
