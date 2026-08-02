<?php
session_start();
include __DIR__ . '/admin/connection.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['privilege_traveller_id'], $_SESSION['privilege_traveller_email'], $_SESSION['priv_otp_email']);
    header('Location: privilege_login.php');
    exit;
}

$tid = isset($_SESSION['privilege_traveller_id']) ? (int) $_SESSION['privilege_traveller_id'] : 0;
if ($tid <= 0) {
    header('Location: privilege_login.php');
    exit;
}

$stmt = $conn->prepare('SELECT id, title, first_name, last_name, email, mobile, card_no, points, city, address FROM privilege_travellers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $tid);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$t) {
    unset($_SESSION['privilege_traveller_id'], $_SESSION['privilege_traveller_email']);
    header('Location: privilege_login.php');
    exit;
}

$_SESSION['privilege_traveller_email'] = $t['email'];
$fullName = trim($t['title'] . ' ' . $t['first_name'] . ' ' . $t['last_name']);

$history = [];
$histStmt = $conn->prepare('SELECT txn_date, invoice_number, txn_type, row_name, tour_name, direction, points, remark FROM privilege_points_history WHERE traveller_id = ? ORDER BY txn_date DESC, id DESC LIMIT 200');
if ($histStmt) {
    $histStmt->bind_param('i', $tid);
    $histStmt->execute();
    $hr = $histStmt->get_result();
    while ($hr && ($row = $hr->fetch_assoc())) {
        $history[] = $row;
    }
    $histStmt->close();
}

$pageTitle = 'My privilege profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Multizone Travels</title>
    <?php include __DIR__ . '/headerlinks.php'; ?>
    <link rel="stylesheet" href="css/privilege-portal.css">
</head>
<body>
    <?php include __DIR__ . '/header1.php'; ?>
    <?php include __DIR__ . '/enquiry_modal.php'; ?>

    <main>
        <section class="page-header">
            <div class="page-header-overlay"></div>
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="page-title" data-aos="fade-up">Privilege profile</h1>
                        <nav aria-label="breadcrumb" data-aos="fade-up" data-aos-delay="100">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item active">Privilege</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="privilege_profile.php?logout=1" class="btn btn-outline-light btn-sm">Log out</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5 bg-light">
            <div class="container">
                <div class="row mb-4">
                    <div class="col-lg-10 mx-auto">
                        <div class="priv-profile-summary mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <p class="text-uppercase small text-muted mb-1 fw-bold">Member</p>
                                    <h2 class="h4 mb-1 text-dark"><?= htmlspecialchars(strtoupper($fullName)) ?></h2>
                                    <p class="mb-0 text-muted small"><?= htmlspecialchars($t['email']) ?> · Card <?= htmlspecialchars($t['card_no']) ?></p>
                                    <?php if (!empty($t['mobile'])): ?>
                                        <p class="mb-0 text-muted small"><?= htmlspecialchars($t['mobile']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <span class="text-muted small d-block mb-1">Current balance</span>
                                    <span class="points-pill"><i class="fas fa-star"></i> <?= (int) $t['points'] ?> pts</span>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm" style="border-radius:14px;">
                            <div class="card-header bg-white border-bottom py-3" style="border-radius:14px 14px 0 0;">
                                <strong class="text-uppercase small text-secondary">Points history</strong>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Invoice</th>
                                                <th>Type</th>
                                                <th>Name</th>
                                                <th>Tour</th>
                                                <th class="text-end">Points</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($history)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4">No transactions yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($history as $h): ?>
                                                    <?php
                                                    $d = $h['txn_date'] ?? '';
                                                    $dt = $d;
                                                    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
                                                        $dt = $m[3] . '/' . $m[2] . '/' . $m[1];
                                                    }
                                                    $typ = trim((string) ($h['txn_type'] ?? ''));
                                                    if (!empty($h['remark'])) {
                                                        $typ = $typ ? $typ . ' · ' . $h['remark'] : (string) $h['remark'];
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($dt) ?></td>
                                                        <td><?= htmlspecialchars((string) ($h['invoice_number'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars($typ) ?></td>
                                                        <td><?= htmlspecialchars((string) ($h['row_name'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string) ($h['tour_name'] ?? '')) ?></td>
                                                        <td class="text-end fw-bold <?= ($h['direction'] ?? '') === 'redeem' ? 'text-danger' : 'text-success' ?>"><?= (int) ($h['points'] ?? 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <p class="text-center text-muted small mt-4 mb-0">Questions? Contact us from <a href="index.php">home</a> or use <strong>Enquire Now</strong>.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
    <?php include __DIR__ . '/footerlinks.php'; ?>
</body>
</html>
