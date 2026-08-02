<?php
/**
 * Privilege member OTP: send_otp | verify_otp (JSON POST, same-origin session).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method.']);
    exit;
}

require_once dirname(__DIR__) . '/admin/connection.php';
require_once dirname(__DIR__) . '/includes/privilege_send_mail.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = isset($input['action']) ? trim((string) $input['action']) : '';
$csrf = isset($input['csrf']) ? (string) $input['csrf'] : '';

if (empty($_SESSION['priv_csrf']) || !hash_equals($_SESSION['priv_csrf'], $csrf)) {
    echo json_encode(['ok' => false, 'message' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS `privilege_login_otp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(191) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

function priv_normalize_email(string $e): string
{
    return strtolower(trim($e));
}

if ($action === 'send_otp') {
    $emailRaw = isset($input['email']) ? (string) $input['email'] : '';
    $email = priv_normalize_email($emailRaw);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id FROM privilege_travellers WHERE LOWER(TRIM(email)) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        echo json_encode(['ok' => false, 'message' => 'No privilege account is registered with this email.']);
        exit;
    }
    $traveller = $res->fetch_assoc();
    $stmt->close();

    $lim = $conn->prepare('SELECT COUNT(*) FROM privilege_login_otp WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)');
    $lim->bind_param('s', $email);
    $lim->execute();
    $rateCnt = 0;
    $lim->bind_result($rateCnt);
    $lim->fetch();
    $lim->close();
    if ($rateCnt > 0) {
        echo json_encode(['ok' => false, 'message' => 'Please wait a minute before requesting another code.']);
        exit;
    }

    $otp = (string) random_int(100000, 999999);
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 600);

    $del = $conn->prepare('DELETE FROM privilege_login_otp WHERE email = ?');
    $del->bind_param('s', $email);
    $del->execute();
    $del->close();

    $ins = $conn->prepare('INSERT INTO privilege_login_otp (email, otp_hash, expires_at) VALUES (?, ?, ?)');
    $ins->bind_param('sss', $email, $hash, $expires);
    if (!$ins->execute()) {
        $ins->close();
        echo json_encode(['ok' => false, 'message' => 'Could not create login code. Try again later.']);
        exit;
    }
    $ins->close();

    $brand = trim((string) ($siteSettings['site_title'] ?? 'Multizone Travels'));
    if ($brand === '') {
        $brand = 'Multizone Travels';
    }
    $safeBrand = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');
    $subject = $brand . ' - Your privilege login code';
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:Segoe UI,Arial,sans-serif;line-height:1.6;color:#1e293b;">'
        . '<p>Your one-time login code is:</p>'
        . '<p style="font-size:28px;font-weight:700;letter-spacing:6px;color:#0f172a;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="color:#64748b;font-size:14px;">This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>'
        . '<p style="color:#94a3b8;font-size:12px;">— ' . $safeBrand . '</p></body></html>';
    $alt = "Your login code is: {$otp}\nValid 10 minutes.\n";

    if (!privilege_portal_send_mail($emailRaw, $subject, $html, $alt)) {
        echo json_encode(['ok' => false, 'message' => 'Could not send email. Check SMTP settings or try again later.']);
        exit;
    }

    $_SESSION['priv_otp_email'] = $email;
    echo json_encode(['ok' => true, 'message' => 'We sent a 6-digit code to your email.']);
    exit;
}

if ($action === 'verify_otp') {
    $email = priv_normalize_email(isset($input['email']) ? (string) $input['email'] : '');
    $otp = preg_replace('/\D/', '', isset($input['otp']) ? (string) $input['otp'] : '');
    if ($email === '' || strlen($otp) !== 6) {
        echo json_encode(['ok' => false, 'message' => 'Enter the email and 6-digit code from your inbox.']);
        exit;
    }

    $stmt = $conn->prepare('SELECT id, otp_hash FROM privilege_login_otp WHERE email = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($otp, $row['otp_hash'])) {
        echo json_encode(['ok' => false, 'message' => 'Invalid or expired code. Request a new code if needed.']);
        exit;
    }

    $del = $conn->prepare('DELETE FROM privilege_login_otp WHERE email = ?');
    $del->bind_param('s', $email);
    $del->execute();
    $del->close();

    $stmt = $conn->prepare('SELECT id, email, title, first_name, last_name, mobile, card_no, points, city, address FROM privilege_travellers WHERE LOWER(TRIM(email)) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$t) {
        echo json_encode(['ok' => false, 'message' => 'Account not found.']);
        exit;
    }

    $_SESSION['privilege_traveller_id'] = (int) $t['id'];
    $_SESSION['privilege_traveller_email'] = $t['email'];
    unset($_SESSION['priv_otp_email']);

    echo json_encode(['ok' => true, 'redirect' => 'privilege_profile.php']);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
