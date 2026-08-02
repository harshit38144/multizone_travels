<?php
session_start();
if ($_SESSION['role'] != '1') {
	header('location:index.php');
	exit;
}
include __DIR__ . '/connection.php';

// Travellers table
$conn->query("CREATE TABLE IF NOT EXISTS `privilege_travellers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(10) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL DEFAULT '',
  `last_name` VARCHAR(100) DEFAULT '',
  `email` VARCHAR(150) DEFAULT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `card_no` VARCHAR(50) DEFAULT NULL,
  `points` INT DEFAULT 0,
  `city` VARCHAR(100) DEFAULT NULL,
  `address` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_card_no` (`card_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Points history table
$conn->query("CREATE TABLE IF NOT EXISTS `privilege_points_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `traveller_id` INT NOT NULL,
  `txn_date` DATE NOT NULL,
  `invoice_number` VARCHAR(128) NOT NULL DEFAULT '',
  `txn_type` VARCHAR(191) NOT NULL DEFAULT '',
  `row_name` VARCHAR(191) NOT NULL DEFAULT '',
  `tour_name` VARCHAR(191) NOT NULL DEFAULT '',
  `direction` ENUM('add','redeem') NOT NULL DEFAULT 'add',
  `points` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_traveller` (`traveller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

foreach ([
    'pax_nos' => 'VARCHAR(32) DEFAULT NULL',
    'pax_mobile' => 'VARCHAR(64) DEFAULT NULL',
    'per_pax_points' => 'INT UNSIGNED DEFAULT NULL',
    'remark' => 'TEXT DEFAULT NULL',
] as $histCol => $histDef) {
    $histChk = $conn->query("SHOW COLUMNS FROM `privilege_points_history` LIKE '" . $conn->real_escape_string($histCol) . "'");
    if ($histChk && $histChk->num_rows == 0) {
        $conn->query("ALTER TABLE `privilege_points_history` ADD `$histCol` $histDef");
    }
}

// --- AJAX (before HTML output) ---
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'points_history') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SESSION['role'] != '1') {
        http_response_code(403);
        echo json_encode(['ok' => false]);
        exit;
    }
    $tid = isset($_GET['traveller_id']) ? (int) $_GET['traveller_id'] : 0;
    $out = [];
    if ($tid > 0) {
        $stmt = $conn->prepare("SELECT id, txn_date, invoice_number, txn_type, row_name, tour_name, direction, points, remark 
            FROM privilege_points_history WHERE traveller_id=? ORDER BY txn_date DESC, id DESC");
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
        $stmt->close();
    }
    echo json_encode(['ok' => true, 'rows' => $out]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'points_txn') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SESSION['role'] != '1') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }

    $tid = isset($_POST['traveller_id']) ? (int) $_POST['traveller_id'] : 0;
    $pts = isset($_POST['points']) ? (int) $_POST['points'] : 0;
    $direction = (isset($_POST['direction']) && $_POST['direction'] === 'redeem') ? 'redeem' : 'add';

    if ($tid <= 0 || $pts <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid traveller or points.']);
        exit;
    }

    $txn_date = mysqli_real_escape_string($conn, $_POST['txn_date'] ?? '');
    if ($txn_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn_date)) {
        $txn_date = date('Y-m-d');
    }

    $invoice_number = mysqli_real_escape_string($conn, $_POST['invoice_number'] ?? '');
    $txn_type = mysqli_real_escape_string($conn, $_POST['txn_type'] ?? '');
    $row_name = mysqli_real_escape_string($conn, $_POST['row_name'] ?? '');
    $tour_name = mysqli_real_escape_string($conn, $_POST['tour_name'] ?? '');
    $pax_nos_raw = mysqli_real_escape_string($conn, trim($_POST['pax_nos'] ?? ''));
    $pax_mobile_raw = mysqli_real_escape_string($conn, trim($_POST['pax_mobile'] ?? ''));
    $remark_raw = mysqli_real_escape_string($conn, $_POST['remark'] ?? '');

    $per_pax_sql = 'NULL';
    if (isset($_POST['per_pax_points']) && trim((string) $_POST['per_pax_points']) !== '') {
        $pp = (int) $_POST['per_pax_points'];
        if ($pp > 0) {
            $per_pax_sql = (string) $pp;
        }
    }

    $point_category = preg_replace('/[^a-z_]/', '', strtolower($_POST['point_category'] ?? ''));
    if ($direction === 'add' && in_array($point_category, ['referral', 'self_tour', 'other'], true)) {
        if ($point_category === 'referral') {
            $txn_type = mysqli_real_escape_string($conn, 'Referral');
        } elseif ($point_category === 'self_tour') {
            $txn_type = mysqli_real_escape_string($conn, 'Self Tour');
        } else {
            $txn_type = mysqli_real_escape_string($conn, 'Other');
        }
        if ($point_category !== 'other' && $tour_name === '') {
            echo json_encode(['ok' => false, 'error' => 'Tour name is required for this category.']);
            exit;
        }
        if (($point_category === 'referral' || $point_category === 'self_tour') && $per_pax_sql === 'NULL') {
            echo json_encode(['ok' => false, 'error' => 'Per pax points is required.']);
            exit;
        }
    }

    $pax_nos_sql = ($pax_nos_raw === '') ? 'NULL' : "'" . $pax_nos_raw . "'";
    $pax_mobile_sql = ($pax_mobile_raw === '') ? 'NULL' : "'" . $pax_mobile_raw . "'";
    $remark_sql = ($remark_raw === '') ? 'NULL' : "'" . $remark_raw . "'";

    $chk = mysqli_query($conn, "SELECT points FROM privilege_travellers WHERE id=$tid LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        echo json_encode(['ok' => false, 'error' => 'Traveller not found.']);
        exit;
    }
    $balance = (int) mysqli_fetch_assoc($chk)['points'];

    if ($direction === 'redeem') {
        if ($pts > $balance) {
            echo json_encode(['ok' => false, 'error' => 'Insufficient points (balance ' . $balance . ').']);
            exit;
        }
        mysqli_query($conn, "UPDATE privilege_travellers SET points = points - $pts WHERE id=$tid");
        $histDir = 'redeem';
    } else {
        mysqli_query($conn, "UPDATE privilege_travellers SET points = points + $pts WHERE id=$tid");
        $histDir = 'add';
    }

    $histDirEsc = mysqli_real_escape_string($conn, $histDir);
    $ins = "INSERT INTO privilege_points_history (traveller_id, txn_date, invoice_number, txn_type, row_name, tour_name, direction, points, pax_nos, pax_mobile, per_pax_points, remark) 
        VALUES ($tid, '$txn_date', '$invoice_number', '$txn_type', '$row_name', '$tour_name', '$histDirEsc', $pts, $pax_nos_sql, $pax_mobile_sql, $per_pax_sql, $remark_sql)";
    if (!mysqli_query($conn, $ins)) {
        echo json_encode(['ok' => false, 'error' => mysqli_error($conn)]);
        exit;
    }

    $nb = mysqli_fetch_assoc(mysqli_query($conn, "SELECT points FROM privilege_travellers WHERE id=$tid LIMIT 1"));
    echo json_encode(['ok' => true, 'new_balance' => (int) ($nb['points'] ?? 0)]);
    exit;
}

$msg = "";

// Handle Add
if (isset($_POST['add_traveller'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $card_no = mysqli_real_escape_string($conn, $_POST['card_no']);
    $points = max(0, (int) ($_POST['points'] ?? 0));
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    $sql = "INSERT INTO privilege_travellers (title, first_name, last_name, email, mobile, card_no, points, city, address) 
            VALUES ('$title', '$fname', '$lname', '$email', '$mobile', '$card_no', $points, '$city', '$address')";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Traveller added successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . mysqli_error($conn);
    }
    header("Location: privilege.php");
    exit;
}

// Handle Edit
if (isset($_POST['edit_traveller'])) {
    $id = (int) $_POST['edit_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $card_no = mysqli_real_escape_string($conn, $_POST['card_no']);
    $points = (int) $_POST['points'];
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    $sql = "UPDATE privilege_travellers SET title='$title', first_name='$fname', last_name='$lname', 
            email='$email', mobile='$mobile', card_no='$card_no', points=$points, 
            city='$city', address='$address' WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['msg'] = "Traveller updated successfully!";
    } else {
        $_SESSION['msg'] = "Error: " . mysqli_error($conn);
    }
    header("Location: privilege.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM privilege_points_history WHERE traveller_id=$del_id");
    mysqli_query($conn, "DELETE FROM privilege_travellers WHERE id=$del_id");
    $_SESSION['msg'] = "Traveller deleted successfully!";
    header("Location: privilege.php");
    exit;
}

// Session message
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

$priv_stats = [
    'members' => 0,
    'points' => 0,
    'month_enrolments' => 0,
    'previous_month_enrolments' => 0,
    'month_points' => 0,
    'previous_month_points' => 0,
];
$st = @$conn->query("SELECT COUNT(*) AS c, COALESCE(SUM(points),0) AS p FROM privilege_travellers");
if ($st && ($sr = $st->fetch_assoc())) {
    $priv_stats['members'] = (int) ($sr['c'] ?? 0);
    $priv_stats['points'] = (int) ($sr['p'] ?? 0);
}
$st = @$conn->query("SELECT COUNT(*) AS c FROM privilege_travellers WHERE created_at >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')");
if ($st && ($sr = $st->fetch_assoc())) {
    $priv_stats['month_enrolments'] = (int) ($sr['c'] ?? 0);
}
$st = @$conn->query("SELECT COUNT(*) AS c FROM privilege_travellers
                     WHERE created_at >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01')
                       AND created_at < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')");
if ($st && ($sr = $st->fetch_assoc())) {
    $priv_stats['previous_month_enrolments'] = (int) ($sr['c'] ?? 0);
}
$st = @$conn->query("SELECT
                        COALESCE(SUM(CASE WHEN txn_date >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN points ELSE 0 END), 0) AS current_points,
                        COALESCE(SUM(CASE
                            WHEN txn_date >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01')
                             AND txn_date < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
                            THEN points ELSE 0 END), 0) AS previous_points
                     FROM privilege_points_history
                     WHERE direction = 'add'");
if ($st && ($sr = $st->fetch_assoc())) {
    $priv_stats['month_points'] = (int) ($sr['current_points'] ?? 0);
    $priv_stats['previous_month_points'] = (int) ($sr['previous_points'] ?? 0);
}

function privilegeIndianNumber($number)
{
    $number = (string) max(0, (int) $number);
    if (strlen($number) <= 3) {
        return $number;
    }
    $lastThree = substr($number, -3);
    $remaining = substr($number, 0, -3);
    return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining) . ',' . $lastThree;
}

function privilegeTier($points)
{
    $points = (int) $points;
    if ($points >= 25000) {
        return ['name' => 'Platinum', 'class' => 'platinum', 'icon' => 'fas fa-crown'];
    }
    if ($points >= 10000) {
        return ['name' => 'Gold', 'class' => 'gold', 'icon' => 'fas fa-star'];
    }
    return ['name' => 'Silver', 'class' => 'silver', 'icon' => 'fas fa-star'];
}

function privilegePercentChange($current, $previous)
{
    $current = (float) $current;
    $previous = (float) $previous;
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

$previousMemberTotal = max(0, $priv_stats['members'] - $priv_stats['month_enrolments']);
$memberTrend = privilegePercentChange($priv_stats['members'], $previousMemberTotal);
$pointsTrend = privilegePercentChange($priv_stats['month_points'], $priv_stats['previous_month_points']);
$enrolmentTrend = privilegePercentChange($priv_stats['month_enrolments'], $priv_stats['previous_month_enrolments']);

$privilegeRows = [];
$privilegeCities = [];
$privilegeSql = "SELECT pt.*,
                        COALESCE((
                            SELECT SUM(ph.points)
                            FROM privilege_points_history ph
                            WHERE ph.traveller_id = pt.id AND ph.direction = 'add'
                        ), pt.points) AS lifetime_points,
                        (
                            SELECT MAX(ph.txn_date)
                            FROM privilege_points_history ph
                            WHERE ph.traveller_id = pt.id
                        ) AS last_activity
                 FROM privilege_travellers pt
                 ORDER BY pt.id DESC";
$privilegeResult = mysqli_query($conn, $privilegeSql);
if ($privilegeResult) {
    while ($row = mysqli_fetch_assoc($privilegeResult)) {
        $privilegeRows[] = $row;
        $cityName = trim((string) ($row['city'] ?? ''));
        if ($cityName !== '') {
            $privilegeCities[$cityName] = $cityName;
        }
    }
}
natcasesort($privilegeCities);
$msg_is_error = ($msg !== '' && stripos($msg, 'error') !== false);
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privilege Travellers</title>

    <?php include __DIR__ . '/includes/header-links.php'; ?>

    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

    <style>
        .privilege-page { background: #eef1f6; }
        .privilege-page .content-wrapper { background: #eef1f6 !important; }
        .privilege-page .content-header { background: transparent; }

        .priv-hero {
            background: linear-gradient(135deg, #0f2747 0%, #1a3a5c 45%, #234a6e 100%);
            color: #fff;
            border-radius: 14px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 12px 40px rgba(15, 39, 71, 0.25);
            position: relative;
            overflow: hidden;
        }
        .priv-hero::after {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.18) 0%, transparent 70%);
            pointer-events: none;
        }
        .priv-hero h1 {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 0.35rem;
        }
        .priv-hero .priv-sub {
            opacity: 0.88;
            font-size: 0.9rem;
            max-width: 36rem;
            margin: 0;
        }
        .priv-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .priv-hero-badge i { color: #e8c547; }

        .priv-stat-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            height: 100%;
        }
        .priv-stat-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.75;
            margin-bottom: 0.2rem;
        }
        .priv-stat-card .value {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .priv-stat-card .value small {
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0.85;
        }

        .priv-main-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(15, 39, 71, 0.08);
            overflow: hidden;
            background: #fff;
        }
        .priv-card-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: linear-gradient(180deg, #fafbfd 0%, #fff 100%);
            border-bottom: 1px solid #e8ecf2;
        }
        .priv-card-toolbar .toolbar-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #5c6b7a;
            margin: 0;
        }
        .priv-search-wrap {
            position: relative;
            min-width: 220px;
            max-width: 320px;
        }
        .priv-search-wrap .form-control {
            border-radius: 10px;
            border: 1px solid #dce3ed;
            padding-left: 2.35rem;
            height: calc(1.5em + 0.65rem + 2px);
            font-size: 0.875rem;
        }
        .priv-search-wrap .form-control:focus {
            border-color: #2c5282;
            box-shadow: 0 0 0 3px rgba(44, 82, 130, 0.12);
        }
        .priv-search-wrap i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #8b9aad;
            font-size: 0.85rem;
        }

        .btn-priv-new {
            background: linear-gradient(135deg, #c9a227 0%, #e8c547 50%, #d4af37 100%);
            color: #1a2332;
            border: none;
            border-radius: 10px;
            padding: 0.45rem 1.1rem;
            font-weight: 700;
            font-size: 0.875rem;
            box-shadow: 0 4px 14px rgba(201, 162, 39, 0.35);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-priv-new:hover {
            color: #0f1724;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(201, 162, 39, 0.45);
        }
        .btn-priv-new i { margin-right: 0.35rem; }

        #privilegeTable thead th {
            background: #f0f4f9;
            color: #3d4f5f;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #dde5ef;
            white-space: nowrap;
            padding-top: 0.85rem;
            padding-bottom: 0.85rem;
        }
        #privilegeTable tbody td {
            vertical-align: middle;
            font-size: 0.875rem;
            border-color: #eef2f7;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        #privilegeTable tbody tr:hover {
            background: #f8fafc;
        }

        .priv-name-cell {
            font-weight: 600;
            color: #1e293b;
            letter-spacing: 0.02em;
        }
        .priv-muted { color: #64748b; font-size: 0.8125rem; }

        .priv-points-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #047857;
            font-weight: 700;
            font-size: 0.8125rem;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            border: 1px solid #a7f3d0;
        }
        .priv-points-badge i { font-size: 0.7rem; opacity: 0.85; }

        .priv-actions {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .priv-actions .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s, box-shadow 0.15s;
            border: 1px solid transparent;
        }
        .priv-actions .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .priv-actions .btn-print-card { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .priv-actions .btn-view-traveller { background: #f5f3ff; color: #5b21b6; border-color: #ddd6fe; }
        .priv-actions .btn-edit-traveller { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .priv-actions a[href*="delete_id"] { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter { padding: 0.5rem 1rem 0; }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { padding: 0.75rem 1rem 1rem; }

        #viewTravellerModal .travellers-detail-card,
        #viewTravellerModal .points-history-card {
            background: #fff;
            border: 1px solid #e8ecf2;
            border-radius: 12px;
            padding: 1.15rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 12px rgba(15, 39, 71, 0.04);
        }

        #viewTravellerModal .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #475569;
            margin-bottom: 0.85rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eef2f7;
        }

        #viewTravellerModal .detail-table th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            white-space: nowrap;
            border-color: #e8ecf2;
        }
        #viewTravellerModal .detail-table td { border-color: #e8ecf2; vertical-align: middle; }

        #viewTravellerModal #pointsHistoryTable {
            font-size: 0.8125rem;
        }
        #viewTravellerModal #pointsHistoryTable thead th {
            background: #f0f4f9;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            border-color: #e2e8f0;
        }

        #viewTravellerModal #btnShowAddPoints {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.4rem 0.85rem;
        }
        #viewTravellerModal #btnShowRedeemPoints {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.4rem 0.85rem;
            background: #0e7490;
            border-color: #0e7490;
        }
        #viewTravellerModal #btnShowRedeemPoints:hover {
            background: #155e75;
            border-color: #155e75;
        }

        /* Add / Redeem Points — material inputs */
        #addPointsModal .material-radio input[type="radio"] {
            accent-color: #c9a227;
        }

        #addPointsModal .material-radio label {
            color: #616161;
            font-size: 14px;
            cursor: pointer;
        }

        #addPointsModal .material-line,
        #redeemPointsModal .material-line {
            margin-bottom: 1rem;
        }

        #addPointsModal .material-line label.small,
        #redeemPointsModal .material-line label.small {
            color: #9e9e9e;
            font-size: 11px;
            margin-bottom: 2px;
        }

        #redeemPointsModal .material-line label.small.redeem-label-primary {
            color: #1976d2;
        }

        #addPointsModal .material-input,
        #redeemPointsModal .material-input {
            width: 100%;
            border: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.25);
            border-radius: 0;
            padding: 6px 0;
            font-size: 14px;
            background: transparent;
        }

        #addPointsModal .material-input:focus,
        #redeemPointsModal .material-input:focus {
            outline: none;
            border-bottom-color: #c9a227;
            box-shadow: none;
        }

        #redeemPointsModal #rdPoints:focus {
            border-bottom-color: #1976d2;
        }

        #addPointsModal .material-input.total-points {
            border-bottom-style: dotted;
        }

        #addPointsModal .add-cat-block input[disabled],
        #addPointsModal .add-cat-block select[disabled] {
            opacity: 0.45;
        }

        body.modal-open .modal.backup-modal-dim ~ .modal-backdrop {
            z-index: 1051;
        }

        .priv-welcome-box {
            border: 1px solid #e8ecf2 !important;
            border-radius: 12px !important;
            background: linear-gradient(180deg, #fafbfd 0%, #fff 100%) !important;
        }

        /* Privilege members dashboard */
        .privilege-page { background: #f4f6f9; color: #374151; }
        .privilege-page .content-wrapper { background: #f4f6f9 !important; min-height: calc(100vh - 3.5rem); }
        .privilege-page .content-header { display: none; }
        .privilege-page .content { padding: .75rem .5rem .5rem; }
        .privilege-page .content > .container-fluid { padding: 0 .5rem .5rem; }
        .priv-page-title-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;
        }
        .priv-page-title-copy { min-width: 0; }
        .priv-page-title {
            margin: 0 0 .2rem; color: #111827; font-size: 1.75rem;
            line-height: 1.2; font-weight: 700;
        }
        .priv-page-subtitle { margin: 0; color: #6b7280; font-size: .92rem; line-height: 1.45; }
        .priv-page-actions { display: flex; align-items: center; gap: .55rem; margin-left: auto; }
        .priv-page-actions .btn-priv-add {
            height: 38px; display: inline-flex; align-items: center; padding: 0 .95rem;
            border-radius: 8px; box-shadow: none; font-size: .84rem; font-weight: 600;
        }
        .priv-dashboard-hero {
            position: relative;
            overflow: hidden;
            margin: 0 -18px;
            padding: 28px 34px 42px;
            background:
                radial-gradient(circle at 75% 0%, rgba(255,255,255,.12), transparent 31%),
                radial-gradient(circle at 8% 115%, rgba(110,0,0,.18), transparent 34%),
                linear-gradient(120deg, #b80716 0%, #df1724 56%, #ef2b32 100%);
            color: #fff;
        }
        .priv-dashboard-hero::after {
            content: ""; position: absolute; inset: 0; opacity: .2; pointer-events: none;
            background-image: linear-gradient(135deg, transparent 48%, rgba(255,255,255,.08) 49%, transparent 50%);
            background-size: 72px 72px;
        }
        .priv-dashboard-hero > .row { position: relative; z-index: 1; }
        .priv-dashboard-title { display: flex; align-items: center; gap: 10px; }
        .priv-dashboard-title h1 { margin: 0; font-size: 1.75rem; font-weight: 800; letter-spacing: -.03em; }
        .priv-dashboard-title i { color: #ffd45d; font-size: 1.2rem; }
        .priv-dashboard-copy { margin: 10px 0 0; max-width: 340px; color: rgba(255,255,255,.84); font-size: .86rem; line-height: 1.55; }
        .priv-kpi-grid {
            display: grid; grid-template-columns: repeat(3, minmax(0,1fr));
            gap: .85rem; margin-bottom: 1rem;
        }
        .priv-kpi {
            min-height: 104px; display: flex; align-items: flex-start; gap: .8rem;
            background: #fff; color: #374151; border: 1px solid #e5e7eb;
            border-radius: 12px; padding: .95rem 1rem;
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
        }
        .priv-kpi-icon {
            width: 42px; height: 42px; flex: 0 0 42px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 10px; color: #e11d48; background: #ffe4e6; font-size: 1rem;
        }
        .priv-kpi:nth-child(2) .priv-kpi-icon { background: #ffedd5; color: #ea580c; }
        .priv-kpi:nth-child(3) .priv-kpi-icon { background: #dbeafe; color: #2563eb; }
        .priv-kpi-label { color: #6b7280; font-size: .8rem; font-weight: 400; margin-bottom: .2rem; line-height: 1.3; }
        .priv-kpi-value { color: #111827; font-size: 1.45rem; line-height: 1.15; font-weight: 700; margin-bottom: .35rem; }
        .priv-kpi-trend { color: #16a34a; font-size: .74rem; line-height: 1.2; font-weight: 600; }
        .priv-kpi-trend.is-down { color: #dc2626; }
        .priv-kpi-trend span { margin-left: 2px; color: inherit; font-weight: inherit; }

        .priv-directory {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            overflow: hidden; box-shadow: 0 1px 3px rgba(15,23,42,.05);
        }
        .priv-filter-bar {
            display: grid; grid-template-columns: minmax(260px,1.4fr) repeat(3,minmax(145px,.75fr)) minmax(180px,.9fr);
            gap: .65rem; align-items: end; padding: .8rem 1rem;
            background: #fff; border-bottom: 1px solid #e5e7eb;
        }
        .priv-filter-search { position: relative; }
        .priv-filter-search i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9aa1ac; font-size: .78rem; }
        .priv-filter-search input { padding-left: 34px; }
        .priv-filter-field label { display: block; margin: 0 0 5px; color: #444b57; font-size: .68rem; font-weight: 700; }
        .priv-filter-field .form-control,
        .priv-filter-search .form-control {
            height: 38px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff;
            color: #374151; font-size: .8rem; box-shadow: none;
        }
        .priv-points-filter { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .btn-priv-add {
            height: 39px; padding: 0 18px; border: 0; border-radius: 8px; white-space: nowrap;
            background: #df1724; color: #fff !important; font-size: .78rem; font-weight: 700;
            box-shadow: 0 5px 12px rgba(223,23,36,.2);
        }
        .btn-priv-add:hover { background: #c8121e; color: #fff; }
        .priv-table-wrap { overflow-x: auto; }
        #privilegeTable { width: 100% !important; margin: 0 !important; }
        #privilegeTable thead th {
            padding: .8rem .75rem; border: 0; border-bottom: 1px solid #e5e7eb;
            background: #f8fafc; color: #4b5563; font-size: .76rem; letter-spacing: .01em;
            text-transform: none; font-weight: 700; vertical-align: middle;
        }
        #privilegeTable tbody td {
            padding: .7rem .75rem; border-top: 0; border-bottom: 1px solid #eef2f7;
            color: #4b5563; font-size: .82rem; white-space: nowrap;
        }
        #privilegeTable tbody tr:hover { background: #f9fafb; }
        .priv-member-id { color: #59616d; font-size: .75rem; font-weight: 600; }
        .priv-member-profile { display: flex; align-items: center; gap: 9px; min-width: 185px; }
        .priv-avatar {
            width: 31px; height: 31px; flex: 0 0 31px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%; background: #fde8ed; color: #d61d3f; font-size: .65rem; font-weight: 800;
        }
        .priv-avatar.tone-1 { background:#eee9ff; color:#7757c7; }
        .priv-avatar.tone-2 { background:#e7f4ff; color:#397cb4; }
        .priv-avatar.tone-3 { background:#fff0df; color:#c3782a; }
        .priv-member-name { color: #282f3a; font-size: .82rem; font-weight: 700; }
        .priv-member-email { max-width: 175px; overflow: hidden; text-overflow: ellipsis; color: #9aa1ab; font-size: .69rem; }
        .priv-tier {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 7px;
            border-radius: 999px; font-size: .7rem; font-weight: 700;
        }
        .priv-tier.platinum { background:#fff7dc; color:#977008; }
        .priv-tier.gold { background:#fff8e9; color:#aa7800; }
        .priv-tier.silver { background:#f0f1f3; color:#6d7480; }
        .priv-points-pill {
            display: inline-flex; align-items: center; gap: 5px; border-radius: 999px;
            padding: 4px 8px; background:#fff1f2; color:#c91d2b; font-size:.72rem; font-weight:800;
        }
        .priv-points-pill i { font-size: .55rem; }
        .priv-status {
            display: inline-flex; padding: 4px 8px; border-radius: 999px;
            background: #eafaf0; color: #35a766; font-size: .69rem; font-weight: 700;
        }
        .priv-list-actions { display: inline-flex; align-items: center; gap: 2px; }
        .priv-list-actions .action-btn {
            width: 27px; height: 27px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%; color:#6f7782; background:transparent; font-size:.72rem;
        }
        .priv-list-actions .action-btn:hover { color:#d7192a; background:#fff0f1; }
        .priv-list-actions .action-delete { color:#e04a57; }
        .priv-directory .dataTables_wrapper .row:first-child { display: none; }
        .priv-directory .dataTables_wrapper > .row:last-child {
            margin: 0; padding: 12px 16px; align-items: center; border-top: 1px solid #edf0f4;
        }
        .priv-directory .dataTables_info { padding: 0 !important; color:#777f8a; font-size:.68rem; }
        .priv-directory .dataTables_paginate { padding: 0 !important; }
        .priv-directory .pagination .page-link {
            min-width: 30px; height: 30px; display:flex; align-items:center; justify-content:center;
            margin: 0 2px; border: 1px solid #edf0f4; border-radius: 6px !important;
            color:#69717d; font-size:.68rem; box-shadow:none;
        }
        .priv-directory .pagination .page-item.active .page-link { background:#df1724; border-color:#df1724; color:#fff; }
        .priv-empty { padding: 30px !important; text-align:center; color:#9299a4 !important; }
        @media (max-width: 1199.98px) {
            .priv-filter-bar { grid-template-columns: repeat(3,minmax(0,1fr)); }
        }
        @media (max-width: 767.98px) {
            .priv-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .priv-filter-bar { grid-template-columns: 1fr; }
            .priv-kpi-grid { gap: 8px; }
            .priv-page-actions { width: 100%; margin-left: 0; }
        }
        @media (max-width: 575.98px) {
            .priv-kpi-grid { grid-template-columns: 1fr; }
            .priv-page-title { font-size: 1.5rem; }
        }

        /* Privilege profile and points modals */
        #viewTravellerModal .modal-content,
        #addPointsModal .modal-content,
        #redeemPointsModal .modal-content {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .2);
        }
        #viewTravellerModal .modal-header,
        #addPointsModal .modal-header,
        #redeemPointsModal .modal-header {
            padding: 1rem 1.25rem;
            background: #fff;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
        }
        .priv-modal-title-wrap { display: flex; align-items: center; gap: .8rem; min-width: 0; }
        .priv-modal-title-icon {
            width: 40px; height: 40px; flex: 0 0 40px; display: inline-flex;
            align-items: center; justify-content: center; border-radius: 10px;
            background: #ffe4e6; color: #e11d48; font-size: 1rem;
        }
        .priv-modal-title-text h5 { color: #111827; font-size: 1rem; font-weight: 700; }
        .priv-modal-title-text .modal-sub { display: block; margin-top: .15rem; color: #6b7280; font-size: .74rem; }
        #viewTravellerModal .modal-header .close,
        #addPointsModal .modal-header .close,
        #redeemPointsModal .modal-header .close {
            width: 34px; height: 34px; margin: 0; padding: 0; border-radius: 8px;
            color: #6b7280; opacity: 1; font-size: 1.25rem; line-height: 1;
        }
        #viewTravellerModal .modal-header .close:hover,
        #addPointsModal .modal-header .close:hover,
        #redeemPointsModal .modal-header .close:hover { background: #f3f4f6; color: #111827; }

        #viewTravellerModal .modal-body { padding: 1.15rem; background: #f8fafc; }
        #viewTravellerModal .travellers-detail-card,
        #viewTravellerModal .points-history-card {
            padding: 1rem; border: 1px solid #e5e7eb; border-radius: 12px;
            background: #fff; box-shadow: none;
        }
        #viewTravellerModal .section-title {
            margin-bottom: .8rem; padding: 0; border: 0;
            color: #111827; font-size: .82rem; font-weight: 700;
            text-transform: none; letter-spacing: 0;
        }
        #viewTravellerModal .detail-table {
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        #viewTravellerModal .detail-table th {
            padding: .65rem .7rem; background: #f8fafc; border: 0;
            border-bottom: 1px solid #e5e7eb; color: #6b7280;
            font-size: .68rem; font-weight: 600; text-transform: none; letter-spacing: 0;
        }
        #viewTravellerModal .detail-table td {
            padding: .75rem .7rem; border: 0; color: #1f2937; font-size: .78rem;
        }
        #viewTravellerModal .priv-profile-actions {
            display: flex; flex-wrap: wrap; align-items: center; gap: .55rem; margin-bottom: .9rem;
        }
        #viewTravellerModal #btnShowAddPoints,
        #viewTravellerModal #btnShowRedeemPoints {
            height: 36px; display: inline-flex; align-items: center;
            padding: 0 .85rem; border-radius: 8px; font-size: .76rem; font-weight: 600;
        }
        #viewTravellerModal #btnShowAddPoints { background: #e11d2e; border-color: #e11d2e; }
        #viewTravellerModal #btnShowAddPoints:hover { background: #c81a28; border-color: #c81a28; }
        #viewTravellerModal #btnShowRedeemPoints {
            background: #fff; border-color: #d1d5db; color: #374151;
        }
        #viewTravellerModal #btnShowRedeemPoints:hover { background: #f9fafb; border-color: #9ca3af; color: #111827; }
        #viewTravellerModal #pointsHistoryTable { border: 1px solid #e5e7eb; }
        #viewTravellerModal #pointsHistoryTable thead th {
            padding: .65rem; background: #f8fafc; color: #4b5563;
            border-color: #e5e7eb; font-size: .68rem; text-transform: none; letter-spacing: 0;
        }
        #viewTravellerModal #pointsHistoryTable tbody td { padding: .65rem; border-color: #eef2f7; }

        #addPointsModal .modal-body,
        #redeemPointsModal .modal-body { padding: 1.15rem 1.25rem; background: #fff; }
        #addPointsModal .material-radio {
            gap: .55rem !important; margin-bottom: 1.1rem !important;
            padding: .35rem; border-radius: 10px; background: #f3f4f6;
        }
        #addPointsModal .material-radio .form-check {
            flex: 1 1 0; justify-content: center; min-height: 36px;
            padding: 0 .7rem !important; border: 1px solid transparent; border-radius: 8px;
            background: transparent;
        }
        #addPointsModal .material-radio .form-check:has(input:checked) {
            background: #fff; border-color: #fecdd3; box-shadow: 0 1px 3px rgba(15,23,42,.07);
        }
        #addPointsModal .material-radio label { color: #4b5563; font-size: .76rem; font-weight: 600; }
        #addPointsModal .material-line,
        #redeemPointsModal .material-line { margin-bottom: .9rem; }
        #addPointsModal .material-line label.small,
        #redeemPointsModal .material-line label.small {
            display: block; margin-bottom: .35rem; color: #4b5563;
            font-size: .7rem; font-weight: 600;
        }
        #addPointsModal .material-input,
        #redeemPointsModal .material-input {
            height: 40px; padding: .55rem .7rem; border: 1px solid #d1d5db;
            border-radius: 8px; background: #fff; color: #1f2937; font-size: .8rem;
            transition: border-color .15s, box-shadow .15s;
        }
        #addPointsModal .material-input:focus,
        #redeemPointsModal .material-input:focus,
        #redeemPointsModal #rdPoints:focus {
            border: 1px solid #e11d2e;
            box-shadow: 0 0 0 3px rgba(225,29,46,.1);
        }
        #addPointsModal .material-input.total-points { border-style: solid; background: #fffafb; }
        #addPointsModal .modal-footer,
        #redeemPointsModal .modal-footer,
        #viewTravellerModal .modal-footer {
            padding: .8rem 1.25rem; background: #fff !important; border-top: 1px solid #e5e7eb;
        }
        #addPointsModal .modal-footer .btn,
        #redeemPointsModal .modal-footer .btn,
        #viewTravellerModal .modal-footer .btn {
            min-height: 36px; border-radius: 8px; padding: .45rem .9rem;
            font-size: .76rem; font-weight: 600;
        }
        #addPointsModal .btn-priv-new,
        #redeemPointsModal .btn-redeem-confirm {
            background: #e11d2e; border: 1px solid #e11d2e; color: #fff;
            box-shadow: none;
        }
        #addPointsModal .btn-priv-new:hover,
        #redeemPointsModal .btn-redeem-confirm:hover {
            background: #c81a28; border-color: #c81a28; color: #fff;
        }
        @media (max-width: 767.98px) {
            #viewTravellerModal .modal-dialog { margin: .5rem; }
            #viewTravellerModal .detail-table { min-width: 880px; }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed privilege-page">
    <div class="wrapper">

        <?php include __DIR__ . '/includes/top-header.php'; ?>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include __DIR__ . '/includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-<?= $msg_is_error ? 'danger' : 'success'; ?> alert-dismissible fade show shadow-sm border-0" style="border-radius:10px;">
                            <?= htmlspecialchars($msg); ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php } ?>

                    <div class="priv-page-title-row">
                        <div class="priv-page-title-copy">
                            <h1 class="priv-page-title">Privilege Members</h1>
                            <p class="priv-page-subtitle">Manage member profiles, reward balances and loyalty activity</p>
                        </div>
                        <div class="priv-page-actions">
                            <button type="button" class="btn btn-priv-add" data-toggle="modal" data-target="#addTravellerModal">
                                <i class="fas fa-plus mr-2"></i>Add Member
                            </button>
                        </div>
                    </div>

                    <div class="priv-kpi-grid">
                        <div class="priv-kpi">
                            <span class="priv-kpi-icon"><i class="fas fa-users"></i></span>
                            <div>
                                <div class="priv-kpi-label">Total Members</div>
                                <div class="priv-kpi-value"><?= number_format($priv_stats['members']); ?></div>
                                <div class="priv-kpi-trend <?= $memberTrend < 0 ? 'is-down' : ''; ?>"><?= $memberTrend >= 0 ? '↑' : '↓'; ?> <?= number_format(abs($memberTrend), 1); ?>% <span>vs last month</span></div>
                            </div>
                        </div>
                        <div class="priv-kpi">
                            <span class="priv-kpi-icon"><i class="fas fa-coins"></i></span>
                            <div>
                                <div class="priv-kpi-label">Total Reward Points</div>
                                <div class="priv-kpi-value"><?= privilegeIndianNumber($priv_stats['points']); ?></div>
                                <div class="priv-kpi-trend <?= $pointsTrend < 0 ? 'is-down' : ''; ?>"><?= $pointsTrend >= 0 ? '↑' : '↓'; ?> <?= number_format(abs($pointsTrend), 1); ?>% <span>vs last month</span></div>
                            </div>
                        </div>
                        <div class="priv-kpi">
                            <span class="priv-kpi-icon"><i class="fas fa-user-plus"></i></span>
                            <div>
                                <div class="priv-kpi-label">This Month Enrollments</div>
                                <div class="priv-kpi-value"><?= number_format($priv_stats['month_enrolments']); ?></div>
                                <div class="priv-kpi-trend <?= $enrolmentTrend < 0 ? 'is-down' : ''; ?>"><?= $enrolmentTrend >= 0 ? '↑' : '↓'; ?> <?= number_format(abs($enrolmentTrend), 1); ?>% <span>vs last month</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="priv-directory">
                        <div class="priv-filter-bar">
                            <div class="priv-filter-search">
                                <i class="fas fa-search"></i>
                                <input type="search" id="searchBox" class="form-control" placeholder="Search by name, mobile or ID...">
                            </div>
                            <div class="priv-filter-field">
                                <label for="tierFilter">Membership Tier</label>
                                <select class="form-control" id="tierFilter">
                                    <option value="">All Tiers</option>
                                    <option value="Platinum">Platinum</option>
                                    <option value="Gold">Gold</option>
                                    <option value="Silver">Silver</option>
                                </select>
                            </div>
                            <div class="priv-filter-field">
                                <label for="statusFilter">Status</label>
                                <select class="form-control" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                </select>
                            </div>
                            <div class="priv-filter-field">
                                <label for="cityFilter">City</label>
                                <select class="form-control" id="cityFilter">
                                    <option value="">All Cities</option>
                                    <?php foreach ($privilegeCities as $cityName): ?>
                                        <option value="<?= htmlspecialchars($cityName, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($cityName); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="priv-filter-field">
                                <label>Points Range</label>
                                <div class="priv-points-filter">
                                    <input type="number" min="0" class="form-control" id="pointsMin" placeholder="Min">
                                    <input type="number" min="0" class="form-control" id="pointsMax" placeholder="Max">
                                </div>
                            </div>
                        </div>

                        <div class="priv-table-wrap">
                            <table id="privilegeTable" class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Member ID</th>
                                        <th>Member Name</th>
                                        <th>Membership Tier</th>
                                        <th>Mobile</th>
                                        <th>Available Points</th>
                                        <th>Last Activity</th>
                                        <th>Status</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($privilegeRows as $row): ?>
                                        <?php
                                        $rawName = trim((string) ($row['title'] ?? '') . ' ' . (string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                                        $fullName = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
                                        $initials = strtoupper(substr((string) ($row['first_name'] ?? ''), 0, 1) . substr((string) ($row['last_name'] ?? ''), 0, 1));
                                        if ($initials === '') {
                                            $initials = 'M';
                                        }
                                        $tier = privilegeTier($row['points'] ?? 0);
                                        $joinDate = !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—';
                                        $lastActivity = 'No activity';
                                        if (!empty($row['last_activity'])) {
                                            $daysAgo = max(0, (int) floor((time() - strtotime($row['last_activity'])) / 86400));
                                            $lastActivity = $daysAgo === 0 ? 'Today' : ($daysAgo === 1 ? '1 day ago' : $daysAgo . ' days ago');
                                        }
                                        $memberId = 'PM-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT);
                                        ?>
                                        <tr data-tier="<?= htmlspecialchars($tier['name']); ?>"
                                            data-city="<?= htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-points="<?= (int) ($row['points'] ?? 0); ?>">
                                            <td><span class="priv-member-id"><?= htmlspecialchars($memberId); ?></span></td>
                                            <td>
                                                <div class="priv-member-profile">
                                                    <span class="priv-avatar tone-<?= (int) $row['id'] % 4; ?>"><?= htmlspecialchars($initials); ?></span>
                                                    <div>
                                                        <div class="priv-member-name"><?= $fullName; ?></div>
                                                        <div class="priv-member-email"><?= htmlspecialchars($row['email'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="priv-tier <?= htmlspecialchars($tier['class']); ?>"><i class="<?= htmlspecialchars($tier['icon']); ?>"></i><?= htmlspecialchars($tier['name']); ?></span></td>
                                            <td><?= htmlspecialchars($row['mobile'] ?? '—'); ?></td>
                                            <td data-order="<?= (int) ($row['points'] ?? 0); ?>"><span class="priv-points-pill"><i class="fas fa-coins"></i><?= privilegeIndianNumber($row['points'] ?? 0); ?></span></td>
                                            <td><?= htmlspecialchars($lastActivity); ?></td>
                                            <td><span class="priv-status">Active</span></td>
                                            <td class="text-right">
                                                <div class="priv-list-actions">
                                                    <a href="javascript:void(0)" class="action-btn btn-view-traveller"
                                                        data-id="<?= (int) $row['id']; ?>" data-name="<?= $fullName; ?>"
                                                        data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-mobile="<?= htmlspecialchars($row['mobile'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-card="<?= htmlspecialchars($row['card_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-points="<?= (int) ($row['points'] ?? 0); ?>"
                                                        data-city="<?= htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-join-date="<?= htmlspecialchars($joinDate, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-lifetime-points="<?= (int) ($row['lifetime_points'] ?? 0); ?>"
                                                        data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="View member"><i class="fas fa-eye"></i></a>
                                                    <a href="javascript:void(0)" class="action-btn btn-edit-traveller"
                                                        data-id="<?= (int) $row['id']; ?>"
                                                        data-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-fname="<?= htmlspecialchars($row['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-lname="<?= htmlspecialchars($row['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-mobile="<?= htmlspecialchars($row['mobile'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-card="<?= htmlspecialchars($row['card_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-points="<?= (int) ($row['points'] ?? 0); ?>"
                                                        data-city="<?= htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        title="Edit member"><i class="fas fa-pen"></i></a>
                                                    <a href="javascript:void(0)" class="action-btn btn-print-card"
                                                        data-id="<?= (int) $row['id']; ?>" data-name="<?= $fullName; ?>"
                                                        data-card="<?= htmlspecialchars($row['card_no'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-points="<?= (int) ($row['points'] ?? 0); ?>"
                                                        title="Print card"><i class="fas fa-print"></i></a>
                                                    <a href="privilege.php?delete_id=<?= (int) $row['id']; ?>" class="action-btn action-delete"
                                                        title="Delete member" onclick="return confirm('Are you sure you want to delete this member?');"><i class="fas fa-trash-alt"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- ADD TRAVELLER MODAL -->
        <div class="modal fade privilege-modal" id="addTravellerModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="mb-0">Add privilege traveller</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body pt-2">

                            <!-- TITLE + NAME -->
                            <div class="form-group">
                                <div class="d-flex" style="gap: 10px;">
                                    <div style="width: 100px;">
                                        <label class="text-muted mb-1" style="font-size:12px;">Title</label>
                                        <select name="title" class="form-control form-control-sm" style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                                            <option>Mr</option>
                                            <option>Mrs</option>
                                            <option>Ms</option>
                                        </select>
                                    </div>
                                    <div class="flex-fill">
                                        <label class="text-muted mb-1" style="font-size:12px;">Name <span style="color:red;">*</span></label>
                                        <input type="text" name="first_name" id="addNameInput" class="form-control form-control-sm" maxlength="20" required
                                            style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                                        <small class="text-muted float-right" style="font-size:11px;"><span id="addNameCount">0</span> / 20</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden last_name (combined in name) -->
                            <input type="hidden" name="last_name" value="">

                            <div class="clearfix"></div>

                            <!-- EMAIL -->
                            <div class="form-group mt-3">
                                <label class="text-muted mb-1" style="font-size:12px;">Email</label>
                                <input type="email" name="email" class="form-control form-control-sm"
                                    style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                            </div>

                            <!-- MOBILE + ADDRESS -->
                            <div class="form-group mt-3">
                                <div class="d-flex" style="gap: 15px;">
                                    <div class="flex-fill">
                                        <label class="text-muted mb-1" style="font-size:12px;">Mobile No</label>
                                        <input type="text" name="mobile" class="form-control form-control-sm"
                                            style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                                    </div>
                                    <div class="flex-fill">
                                        <label class="text-muted mb-1" style="font-size:12px;">Address</label>
                                        <input type="text" name="address" class="form-control form-control-sm"
                                            style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label class="text-muted mb-1" style="font-size:12px;">City</label>
                                <input type="text" name="city" class="form-control form-control-sm"
                                    style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                            </div>

                            <!-- CARD NO + Welcome point -->
                            <div class="mt-3 p-3 priv-welcome-box">
                                <label class="text-muted mb-1" style="font-size:12px;">Card number</label>
                                <input type="text" name="card_no" class="form-control form-control-sm"
                                    style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                                <label class="text-muted mb-1 mt-3 d-block" style="font-size:12px;">Welcome points</label>
                                <input type="number" name="points" class="form-control form-control-sm" min="0" step="1" value="0"
                                    placeholder="0"
                                    style="border:none; border-bottom: 1px solid #ccc; border-radius:0; padding-left:0;">
                            </div>

                        </div>

                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary px-4" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_traveller" class="btn btn-priv-new px-4">Create traveller</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- EDIT TRAVELLER MODAL -->
        <div class="modal fade privilege-modal" id="editTravellerModal">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" name="edit_id" id="editId">
                        <div class="modal-header">
                            <h5 class="mb-0">Edit privilege traveller</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body">
                            <div class="row">

                                <div class="col-md-2 form-group">
                                    <label>Title</label>
                                    <select name="title" id="editTitle" class="form-control">
                                        <option>Mr</option>
                                        <option>Mrs</option>
                                        <option>Ms</option>
                                    </select>
                                </div>

                                <div class="col-md-5 form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" id="editFname" class="form-control" required>
                                </div>

                                <div class="col-md-5 form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" id="editLname" class="form-control">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" id="editEmail" class="form-control">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>Mobile No</label>
                                    <input type="text" name="mobile" id="editMobile" class="form-control">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>Card No</label>
                                    <input type="text" name="card_no" id="editCard" class="form-control">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>Points</label>
                                    <input type="number" name="points" id="editPoints" class="form-control">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>City</label>
                                    <input type="text" name="city" id="editCity" class="form-control">
                                </div>

                                <div class="col-md-6 form-group">
                                    <label>Address</label>
                                    <input type="text" name="address" id="editAddress" class="form-control">
                                </div>

                            </div>
                        </div>

                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit_traveller" class="btn btn-priv-new">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- VIEW TRAVELLER MODAL -->
        <div class="modal fade" id="viewTravellerModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header align-items-center">
                        <div class="priv-modal-title-wrap">
                            <span class="priv-modal-title-icon"><i class="fas fa-user"></i></span>
                            <div class="priv-modal-title-text">
                                <h5 class="modal-title mb-0">Traveller Profile</h5>
                                <small class="modal-sub">Member details, reward balance and points activity</small>
                            </div>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body pb-4">

                        <div class="travellers-detail-card">
                            <div class="section-title">Travellers Detail</div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0 detail-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Mobile No</th>
                                            <th>Card No</th>
                                            <th>City</th>
                                            <th>Join Date</th>
                                            <th class="text-right">Total Points</th>
                                            <th class="text-right">Lifetime Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td id="viewName" style="text-transform: uppercase;"></td>
                                            <td id="viewEmail"></td>
                                            <td id="viewMobile"></td>
                                            <td id="viewCard"></td>
                                            <td id="viewCity"></td>
                                            <td id="viewJoinDate"></td>
                                            <td id="viewPoints" class="text-right font-weight-bold"></td>
                                            <td id="viewLifetimePoints" class="text-right font-weight-bold"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="points-history-card">
                            <div class="section-title mb-3">Points History</div>
                            <div class="priv-profile-actions">
                                <button type="button" class="btn btn-primary btn-sm" id="btnShowAddPoints">
                                    <i class="fas fa-plus mr-1"></i> Add Points
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="btnShowRedeemPoints" style="background:#17a2b8;border-color:#17a2b8;">
                                    <i class="fas fa-minus-circle mr-1"></i> Redeem Points
                                </button>
                            </div>

                            <input type="hidden" id="txnTravellerId" value="">

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm mb-0" id="pointsHistoryTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice Number</th>
                                            <th>Type</th>
                                            <th>Name</th>
                                            <th>Tour Name</th>
                                            <th class="text-right">Points</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pointsHistoryBody">
                                        <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer py-2 bg-light border-top">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD POINTS (opens from traveller profile) -->
        <div class="modal fade points-txn-modal" id="addPointsModal" tabindex="-1" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="addPointsForm">
                        <input type="hidden" name="ajax_action" value="points_txn">
                        <input type="hidden" name="direction" value="add">
                        <input type="hidden" name="traveller_id" id="addTravellerIdHidden" value="">
                        <input type="hidden" name="point_category" id="addPointCategoryHidden" value="referral">
                        <div class="modal-header align-items-center">
                            <div class="priv-modal-title-wrap">
                                <span class="priv-modal-title-icon"><i class="fas fa-plus"></i></span>
                                <div class="priv-modal-title-text">
                                    <h5 class="modal-title mb-0">Add Points</h5>
                                    <small class="modal-sub">Credit reward points and record the transaction details</small>
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                        </div>
                        <div class="modal-body pt-2">
                            <div class="d-flex flex-wrap mb-4 material-radio" style="gap:1.25rem;">
                                <div class="form-check form-check-inline m-0 p-0 align-items-center d-flex">
                                    <input class="mr-2" type="radio" name="add_point_radio" id="radioReferral" value="referral" checked>
                                    <label class="mb-0" for="radioReferral">Referral</label>
                                </div>
                                <div class="form-check form-check-inline m-0 p-0 align-items-center d-flex">
                                    <input class="mr-2" type="radio" name="add_point_radio" id="radioSelfTour" value="self_tour">
                                    <label class="mb-0" for="radioSelfTour">Self Tour</label>
                                </div>
                                <div class="form-check form-check-inline m-0 p-0 align-items-center d-flex">
                                    <input class="mr-2" type="radio" name="add_point_radio" id="radioOther" value="other">
                                    <label class="mb-0" for="radioOther">Other</label>
                                </div>
                            </div>

                            <div id="addFieldsReferral" class="add-cat-block">
                                <div class="material-line">
                                    <label class="small">Invoice No</label>
                                    <input type="text" class="material-input" name="invoice_number" id="rfInvoice" autocomplete="off">
                                </div>
                                <div class="form-row">
                                    <div class="col-6 material-line">
                                        <label class="small">Pax Name</label>
                                        <input type="text" class="material-input" name="row_name" id="rfPaxName" autocomplete="off">
                                    </div>
                                    <div class="col-6 material-line">
                                        <label class="small">Pax Nos.</label>
                                        <input type="text" class="material-input rf-calc-trigger" name="pax_nos" id="rfPaxNos" inputmode="numeric" autocomplete="off">
                                    </div>
                                </div>
                                <div class="material-line">
                                    <label class="small">Mobile No</label>
                                    <input type="text" class="material-input" name="pax_mobile" id="rfMobile" autocomplete="off">
                                </div>
                                <div class="form-row">
                                    <div class="col-6 material-line">
                                        <label class="small">Tour Name <span class="text-danger">*</span></label>
                                        <input type="text" class="material-input" name="tour_name" id="rfTourName" autocomplete="off">
                                    </div>
                                    <div class="col-6 material-line">
                                        <label class="small">Tour Date</label>
                                        <input type="date" class="material-input" name="txn_date" id="rfTourDate" autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="col-6 material-line">
                                        <label class="small">Per Pax Points <span class="text-danger">*</span></label>
                                        <input type="number" min="1" class="material-input rf-calc-trigger" name="per_pax_points" id="rfPerPax" autocomplete="off">
                                    </div>
                                    <div class="col-6 material-line">
                                        <label class="small">Total Points <span class="text-danger">*</span></label>
                                        <input type="number" min="1" class="material-input total-points rf-total-trigger" name="points" id="rfTotalPts" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <div id="addFieldsSelfTour" class="add-cat-block d-none">
                                <div class="material-line">
                                    <label class="small">Invoice No</label>
                                    <input type="text" class="material-input" name="invoice_number" id="sfInvoice" disabled autocomplete="off">
                                </div>
                                <div class="form-row">
                                    <div class="col-6 material-line">
                                        <label class="small">Tour Name <span class="text-danger">*</span></label>
                                        <input type="text" class="material-input" name="tour_name" id="sfTourName" disabled autocomplete="off">
                                    </div>
                                    <div class="col-6 material-line">
                                        <label class="small">Tour Date</label>
                                        <input type="date" class="material-input" name="txn_date" id="sfTourDate" disabled autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="col-6 material-line">
                                        <label class="small">Pax Name</label>
                                        <input type="text" class="material-input" name="row_name" id="sfPaxName" disabled autocomplete="off">
                                    </div>
                                    <div class="col-6 material-line">
                                        <label class="small">Pax Nos.</label>
                                        <input type="text" class="material-input sf-calc-trigger" name="pax_nos" id="sfPaxNos" disabled inputmode="numeric" autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="col-6 material-line">
                                        <label class="small">Per Pax Points <span class="text-danger">*</span></label>
                                        <input type="number" min="1" class="material-input sf-calc-trigger" name="per_pax_points" id="sfPerPax" disabled autocomplete="off">
                                    </div>
                                    <div class="col-6 material-line">
                                        <label class="small">Total Points <span class="text-danger">*</span></label>
                                        <input type="number" min="1" class="material-input total-points sf-total-trigger" name="points" id="sfTotalPts" disabled autocomplete="off">
                                    </div>
                                </div>
                                <input type="hidden" name="pax_mobile" value="" disabled>
                            </div>

                            <div id="addFieldsOther" class="add-cat-block d-none">
                                <div class="material-line">
                                    <label class="small">Remark</label>
                                    <input type="text" class="material-input" name="remark" id="otRemark" disabled autocomplete="off">
                                </div>
                                <div class="material-line">
                                    <label class="small">Invoice No</label>
                                    <input type="text" class="material-input" name="invoice_number" id="otInvoice" disabled autocomplete="off">
                                </div>
                                <div class="material-line">
                                    <label class="small">Total Points <span class="text-danger">*</span></label>
                                    <input type="number" min="1" class="material-input" name="points" id="otTotalPts" disabled autocomplete="off">
                                </div>
                                <input type="hidden" name="row_name" value="" disabled>
                                <input type="hidden" name="tour_name" value="" disabled>
                                <input type="hidden" name="txn_date" id="otTxnDateHidden" disabled>
                                <input type="hidden" name="pax_nos" value="" disabled>
                                <input type="hidden" name="pax_mobile" value="" disabled>
                                <input type="hidden" name="per_pax_points" value="" disabled>
                            </div>

                            <p class="small text-danger mb-0 d-none" id="addPointsFormErr"></p>
                        </div>
                        <div class="modal-footer d-flex justify-content-end flex-wrap" style="gap:10px;">
                            <button type="button" class="btn btn-outline-secondary" id="addPointsModalCancelBtn">Cancel</button>
                            <button type="submit" class="btn btn-priv-new" id="addPointsModalSaveBtn">Save points</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- REDEEM POINTS -->
        <div class="modal fade points-txn-modal" id="redeemPointsModal" tabindex="-1" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="redeemPointsForm" autocomplete="off">
                        <input type="hidden" name="ajax_action" value="points_txn">
                        <input type="hidden" name="direction" value="redeem">
                        <input type="hidden" name="traveller_id" id="redeemTravellerIdHidden" value="">
                        <input type="hidden" name="point_category" value="">
                        <input type="hidden" name="txn_type" value="Redemption">
                        <input type="hidden" name="remark" value="">
                        <input type="hidden" name="pax_mobile" value="">
                        <input type="hidden" name="per_pax_points" value="">
                        <div class="modal-header align-items-center">
                            <div class="priv-modal-title-wrap">
                                <span class="priv-modal-title-icon"><i class="fas fa-minus"></i></span>
                                <div class="priv-modal-title-text">
                                    <h5 class="modal-title mb-0">Redeem Points</h5>
                                    <small class="modal-sub">Debit available points and save the redemption details</small>
                                </div>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                        </div>
                        <div class="modal-body pt-2">
                            <div class="form-row">
                                <div class="col-6 material-line">
                                    <label class="small redeem-label-primary">Points <span class="text-danger">*</span></label>
                                    <input type="number" min="1" class="material-input" name="points" id="rdPoints" required inputmode="numeric">
                                </div>
                                <div class="col-6 material-line">
                                    <label class="small">Invoice No</label>
                                    <input type="text" class="material-input" name="invoice_number" id="rdInvoice" autocomplete="off">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-6 material-line">
                                    <label class="small">Pax Name</label>
                                    <input type="text" class="material-input" name="row_name" id="rdPaxName" autocomplete="off">
                                </div>
                                <div class="col-6 material-line">
                                    <label class="small">Pax Nos.</label>
                                    <input type="text" class="material-input" name="pax_nos" id="rdPaxNos" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-6 material-line">
                                    <label class="small">Tour Name <span class="text-muted">*</span></label>
                                    <input type="text" class="material-input" name="tour_name" id="rdTourName" autocomplete="off" required>
                                </div>
                                <div class="col-6 material-line">
                                    <label class="small">Tour Date</label>
                                    <input type="date" class="material-input" name="txn_date" id="rdTourDate" autocomplete="off">
                                </div>
                            </div>
                            <p class="small text-danger mb-0 d-none" id="redeemPointsFormErr"></p>
                        </div>
                        <div class="modal-footer d-flex justify-content-end flex-wrap" style="gap:10px;">
                            <button type="button" class="btn btn-outline-secondary" id="redeemPointsModalCancelBtn">Cancel</button>
                            <button type="submit" class="btn btn-redeem-confirm" id="redeemPointsModalSaveBtn">Confirm redemption</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/includes/footer-links.php'; ?>

        <script>
            $(function () {

                var table = $('#privilegeTable').DataTable({
                    order: [],
                    dom: "lrtip",
                    pageLength: 10,
                    autoWidth: false,
                    columnDefs: [
                        { orderable: false, targets: 7 }
                    ],
                    language: {
                        search: "",
                        searchPlaceholder: "Search…",
                        emptyTable: "No members found",
                        info: "Showing _START_ to _END_ of _TOTAL_ members",
                        infoEmpty: "Showing 0 members"
                    }
                });

                $('#searchBox').on('input', function () {
                    table.search(this.value).draw();
                });

                $('#tierFilter').on('change', function () {
                    table.column(2).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
                });

                $('#cityFilter').on('change', function () {
                    table.draw();
                });

                $('#statusFilter').on('change', function () {
                    table.column(6).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
                });

                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if (!settings.nTable || settings.nTable.id !== 'privilegeTable') return true;
                    var min = parseInt($('#pointsMin').val(), 10);
                    var max = parseInt($('#pointsMax').val(), 10);
                    var row = table.row(dataIndex).node();
                    var points = parseInt($(row).attr('data-points'), 10) || 0;
                    var selectedCity = String($('#cityFilter').val() || '');
                    var rowCity = String($(row).attr('data-city') || '');
                    return (selectedCity === '' || rowCity === selectedCity)
                        && (isNaN(min) || points >= min)
                        && (isNaN(max) || points <= max);
                });

                $('#pointsMin, #pointsMax').on('input', function () {
                    table.draw();
                });

                function loadPointsHistory(travellerId) {
                    var $body = $('#pointsHistoryBody');
                    $body.html('<tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr>');
                    $.getJSON('privilege.php', { ajax_action: 'points_history', traveller_id: travellerId })
                        .done(function (res) {
                            if (!res.ok || !res.rows || res.rows.length === 0) {
                                $body.html('<tr><td colspan="6" class="text-center text-muted py-4">No history yet.</td></tr>');
                                return;
                            }
                            var html = '';
                            res.rows.forEach(function (row) {
                                var d = row.txn_date;
                                var dt = '';
                                try {
                                    var parts = d.split('-');
                                    if (parts.length === 3) dt = parts[2] + '/' + parts[1] + '/' + parts[0];
                                    else dt = d;
                                } catch (e) { dt = d; }
                                var ptsClass = row.direction === 'redeem' ? 'text-danger' : 'text-success';
                                html += '<tr>';
                                html += '<td>' + $('<div/>').text(dt).html() + '</td>';
                                html += '<td>' + $('<div/>').text(row.invoice_number || '').html() + '</td>';
                                var typDisp = row.txn_type || '';
                                if (row.remark) {
                                    typDisp = typDisp ? typDisp + ' · ' + String(row.remark) : String(row.remark);
                                }
                                html += '<td>' + $('<div/>').text(typDisp).html() + '</td>';
                                html += '<td>' + $('<div/>').text(row.row_name || '').html() + '</td>';
                                html += '<td>' + $('<div/>').text(row.tour_name || '').html() + '</td>';
                                html += '<td class="text-right font-weight-bold ' + ptsClass + '">' + $('<div/>').text(String(row.points)).html() + '</td>';
                                html += '</tr>';
                            });
                            $body.html(html);
                        })
                        .fail(function () {
                            $body.html('<tr><td colspan="6" class="text-center text-danger py-3">Could not load history.</td></tr>');
                        });
                }

                function syncBalanceUI(res, tid) {
                    if (!res || !res.ok || !tid) return;
                    $('#viewPoints').text(res.new_balance);
                    loadPointsHistory(tid);
                    var $viewBtn = $('a.btn-view-traveller[data-id="' + tid + '"]');
                    $viewBtn.attr('data-points', res.new_balance);
                    var $tr = $viewBtn.closest('tr');
                    $tr.find('a.btn-print-card').attr('data-points', res.new_balance);
                    $tr.attr('data-points', res.new_balance);
                    $tr.find('td').eq(4)
                        .attr('data-order', res.new_balance)
                        .html('<span class="priv-points-pill"><i class="fas fa-coins"></i>' + Number(res.new_balance).toLocaleString('en-IN') + '</span>');
                    table.row($tr).invalidate('dom').draw(false);
                }



                function syncAddCategoryUi() {
                    var cat = $('input[name="add_point_radio"]:checked').val() || 'referral';
                    $('#addPointCategoryHidden').val(cat);
                    $('#addFieldsReferral, #addFieldsSelfTour, #addFieldsOther').each(function () {
                        $(this).addClass('d-none');
                        $(this).find('input, select, textarea').prop('disabled', true);
                    });
                    var $block;
                    if (cat === 'referral') $block = $('#addFieldsReferral');
                    else if (cat === 'self_tour') $block = $('#addFieldsSelfTour');
                    else $block = $('#addFieldsOther');
                    $block.removeClass('d-none');
                    $block.find('input, select, textarea').prop('disabled', false);

                    $('#rfTourName, #sfTourName').prop('required', false);
                    $('#rfPerPax, #rfTotalPts, #sfPerPax, #sfTotalPts, #otTotalPts').prop('required', false);
                    if (cat === 'referral') {
                        $('#rfTourName, #rfPerPax, #rfTotalPts').prop('required', true);
                    } else if (cat === 'self_tour') {
                        $('#sfTourName, #sfPerPax, #sfTotalPts').prop('required', true);
                    } else {
                        $('#otTotalPts').prop('required', true);
                        $('#otTxnDateHidden').val(new Date().toISOString().slice(0, 10));
                    }
                    recalcRfTotal();
                    recalcSfTotal();
                }

                function recalcRfTotal() {
                    var p = parseInt($('#rfPerPax').val(), 10) || 0;
                    var nParsed = parseInt(String($('#rfPaxNos').val()).replace(/\D+/g, ''), 10);
                    var n = isNaN(nParsed) ? 0 : nParsed;
                    if (p > 0 && n > 0) $('#rfTotalPts').val(p * n);
                }

                function recalcSfTotal() {
                    var p = parseInt($('#sfPerPax').val(), 10) || 0;
                    var nParsed = parseInt(String($('#sfPaxNos').val()).replace(/\D+/g, ''), 10);
                    var n = isNaN(nParsed) ? 0 : nParsed;
                    if (p > 0 && n > 0) $('#sfTotalPts').val(p * n);
                }

                $('input[name="add_point_radio"]').on('change', syncAddCategoryUi);
                $(document).on('input change', '.rf-calc-trigger', recalcRfTotal);
                $(document).on('input change', '.sf-calc-trigger', recalcSfTotal);

                $('#addPointsModal').on('hidden.bs.modal', function () {
                    $('#viewTravellerModal').removeClass('backup-modal-dim').css({ opacity: 1 });
                }).on('show.bs.modal', function () {
                    $('#viewTravellerModal').addClass('backup-modal-dim').css({ opacity: 0.9 });
                });

                $('#redeemPointsModal').on('hidden.bs.modal', function () {
                    $('#viewTravellerModal').removeClass('backup-modal-dim').css({ opacity: 1 });
                }).on('show.bs.modal', function () {
                    $('#viewTravellerModal').addClass('backup-modal-dim').css({ opacity: 0.9 });
                });

                $('#btnShowAddPoints').on('click', function () {
                    var id = $('#txnTravellerId').val();
                    if (!id) return;
                    $('#addPointsForm')[0].reset();
                    $('#radioReferral').prop('checked', true);
                    $('#addTravellerIdHidden').val(id);
                    $('#addPointCategoryHidden').val('referral');
                    var iso = new Date().toISOString().slice(0, 10);
                    $('#rfTourDate, #sfTourDate').val(iso);
                    $('#otTxnDateHidden').val(iso);
                    var fullName = $('#viewName').text().trim();
                    $('#rfPaxName').val(fullName);
                    $('#sfPaxName').val(fullName);
                    $('#rfMobile').val($('#viewMobile').text().trim());
                    $('#addPointsFormErr').addClass('d-none').text('');
                    syncAddCategoryUi();
                    $('#addPointsModal').modal('show');
                });

                $('#addPointsModalCancelBtn, #addPointsModal button.close').on('click', function () {
                    $('#addPointsModal').modal('hide');
                });

                $('#addPointsForm').on('submit', function (e) {
                    e.preventDefault();
                    $('#addPointsFormErr').addClass('d-none').text('');
                    var cat = $('#addPointCategoryHidden').val();
                    if (cat === 'referral' && !$('#rfTourName').val().trim()) {
                        $('#addPointsFormErr').removeClass('d-none').text('Tour name is required.');
                        return;
                    }
                    if (cat === 'self_tour' && !$('#sfTourName').val().trim()) {
                        $('#addPointsFormErr').removeClass('d-none').text('Tour name is required.');
                        return;
                    }
                    var pts = 0;
                    if (cat === 'referral') pts = parseInt($('#rfTotalPts').val(), 10) || 0;
                    else if (cat === 'self_tour') pts = parseInt($('#sfTotalPts').val(), 10) || 0;
                    else pts = parseInt($('#otTotalPts').val(), 10) || 0;
                    if (pts < 1) {
                        $('#addPointsFormErr').removeClass('d-none').text('Enter total points.');
                        return;
                    }

                    var $save = $('#addPointsModalSaveBtn');
                    $save.prop('disabled', true);
                    $.ajax({
                        url: 'privilege.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json'
                    }).done(function (res) {
                        if (!res || !res.ok) {
                            $('#addPointsFormErr').removeClass('d-none').text((res && res.error) ? res.error : 'Save failed.');
                            return;
                        }
                        syncBalanceUI(res, $('#addTravellerIdHidden').val());
                        $('#addPointsModal').modal('hide');
                    }).fail(function () {
                        $('#addPointsFormErr').removeClass('d-none').text('Network error.');
                    }).always(function () {
                        $save.prop('disabled', false);
                    });
                });

                $('#btnShowRedeemPoints').on('click', function () {
                    var id = $('#txnTravellerId').val();
                    if (!id) return;
                    $('#redeemPointsForm')[0].reset();
                    $('#redeemTravellerIdHidden').val(id);
                    var iso = new Date().toISOString().slice(0, 10);
                    $('#rdTourDate').val(iso);
                    $('#rdPaxName').val($('#viewName').text().trim());
                    $('#rdInvoice, #rdTourName, #rdPaxNos, #rdPoints').val('');
                    $('#redeemPointsFormErr').addClass('d-none').text('');
                    $('#redeemPointsModal').modal('show');
                });

                $('#redeemPointsModalCancelBtn, #redeemPointsModal button.close').on('click', function () {
                    $('#redeemPointsModal').modal('hide');
                });

                $('#redeemPointsForm').on('submit', function (e) {
                    e.preventDefault();
                    $('#redeemPointsFormErr').addClass('d-none').text('');
                    var pts = parseInt($('#rdPoints').val(), 10) || 0;
                    if (pts < 1) {
                        $('#redeemPointsFormErr').removeClass('d-none').text('Enter points to redeem.');
                        return;
                    }
                    if (!$('#rdTourName').val().trim()) {
                        $('#redeemPointsFormErr').removeClass('d-none').text('Tour name is required.');
                        return;
                    }

                    var $save = $('#redeemPointsModalSaveBtn');
                    $save.prop('disabled', true);
                    $.ajax({
                        url: 'privilege.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json'
                    }).done(function (res) {
                        if (!res || !res.ok) {
                            $('#redeemPointsFormErr').removeClass('d-none').text((res && res.error) ? res.error : 'Redeem failed.');
                            return;
                        }
                        syncBalanceUI(res, $('#redeemTravellerIdHidden').val());
                        $('#redeemPointsModal').modal('hide');
                    }).fail(function () {
                        $('#redeemPointsFormErr').removeClass('d-none').text('Network error.');
                    }).always(function () {
                        $save.prop('disabled', false);
                    });
                });

                $(document).on('click', '.btn-view-traveller', function () {
                    var $el = $(this);
                    var id = $el.data('id');
                    var fullName = $el.data('name') || '';
                    $('#viewName').text(fullName);
                    $('#viewEmail').text($el.data('email') || '');
                    $('#viewMobile').text($el.data('mobile') || '');
                    $('#viewCard').text($el.data('card') || '');
                    $('#viewCity').text($el.data('city') || '—');
                    $('#viewJoinDate').text($el.attr('data-join-date') || '—');
                    $('#viewPoints').text($el.data('points') != null ? $el.data('points') : '0');
                    $('#viewLifetimePoints').text(Number($el.attr('data-lifetime-points') || 0).toLocaleString('en-IN'));
                    $('#txnTravellerId').val(id);
                    loadPointsHistory(id);
                    $('#viewTravellerModal').modal('show');
                });

                // Edit Traveller
                $(document).on('click', '.btn-edit-traveller', function () {
                    $('#editId').val($(this).data('id'));
                    $('#editTitle').val($(this).data('title'));
                    $('#editFname').val($(this).data('fname'));
                    $('#editLname').val($(this).data('lname'));
                    $('#editEmail').val($(this).data('email'));
                    $('#editMobile').val($(this).data('mobile'));
                    $('#editCard').val($(this).data('card'));
                    $('#editPoints').val($(this).data('points'));
                    $('#editCity').val($(this).data('city'));
                    $('#editAddress').val($(this).data('address'));
                    $('#editTravellerModal').modal('show');
                });

                // Print Card (simple print)
                $(document).on('click', '.btn-print-card', function () {
                    var name = $(this).data('name');
                    var card = $(this).data('card');
                    var points = $(this).data('points');

                    var printWin = window.open('', '_blank', 'width=500,height=350');
                    printWin.document.write('<html><head><title>Privilege Card</title>');
                    printWin.document.write('<style>');
                    printWin.document.write('body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; }');
                    printWin.document.write('.card { width: 400px; padding: 30px; border-radius: 12px; background: linear-gradient(135deg, #1a237e, #283593); color: #fff; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }');
                    printWin.document.write('.card h3 { margin: 0 0 5px; font-size: 14px; opacity: 0.8; letter-spacing: 2px; text-transform: uppercase; }');
                    printWin.document.write('.card h2 { margin: 0 0 20px; font-size: 20px; }');
                    printWin.document.write('.card .info { display: flex; justify-content: space-between; margin-top: 15px; }');
                    printWin.document.write('.card .label { font-size: 10px; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; }');
                    printWin.document.write('.card .value { font-size: 16px; font-weight: bold; margin-top: 4px; }');
                    printWin.document.write('@media print { body { background: #fff; } }');
                    printWin.document.write('</style></head><body>');
                    printWin.document.write('<div class="card">');
                    printWin.document.write('<h3>Multizone Travels</h3>');
                    printWin.document.write('<h2>Privilege Traveller Card</h2>');
                    printWin.document.write('<div style="border-top:1px solid rgba(255,255,255,0.3); padding-top:15px;">');
                    printWin.document.write('<div class="label">Cardholder Name</div>');
                    printWin.document.write('<div class="value">' + name + '</div>');
                    printWin.document.write('</div>');
                    printWin.document.write('<div class="info">');
                    printWin.document.write('<div><div class="label">Card Number</div><div class="value">' + card + '</div></div>');
                    printWin.document.write('<div><div class="label">Points</div><div class="value">' + points + '</div></div>');
                    printWin.document.write('</div>');
                    printWin.document.write('</div>');
                    printWin.document.write('</body></html>');
                    printWin.document.close();
                    setTimeout(function () { printWin.print(); }, 300);
                });

                // Name character counter
                $('#addNameInput').on('input', function () {
                    $('#addNameCount').text($(this).val().length);
                });

            });
        </script>

    </div>
</body>

</html>
