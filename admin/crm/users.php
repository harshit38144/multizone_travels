<?php
require_once __DIR__ . '/bootstrap.php';

$conn->query("CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(120) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(180) NOT NULL,
    `user_type` VARCHAR(60) NOT NULL DEFAULT 'Employee',
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function crmUserNameTone(int $index): array
{
    $tones = [
        ['key' => 'red', 'color' => '#e11d2e'],
        ['key' => 'purple', 'color' => '#7c3aed'],
        ['key' => 'blue', 'color' => '#2563eb'],
        ['key' => 'orange', 'color' => '#ea580c'],
        ['key' => 'teal', 'color' => '#0d9488'],
        ['key' => 'indigo', 'color' => '#4f46e5'],
        ['key' => 'pink', 'color' => '#db2777'],
        ['key' => 'green', 'color' => '#16a34a'],
        ['key' => 'cyan', 'color' => '#0891b2'],
        ['key' => 'amber', 'color' => '#d97706'],
    ];
    return $tones[$index % count($tones)];
}

function crmUserToneForId(int $userId, int $fallbackIndex = 0): array
{
    $idx = $userId > 0 ? ($userId - 1) : $fallbackIndex;
    return crmUserNameTone(max(0, $idx));
}

function crmUserFormatCreated($datetime): string
{
    $datetime = trim((string) $datetime);
    if ($datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('d M Y, h:i A', $ts);
}

function crmUserUploadProfileImage(): array
{
    if (empty($_FILES['profile_image']['name']) || empty($_FILES['profile_image']['tmp_name'])) {
        return ['ok' => true, 'filename' => ''];
    }

    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'message' => 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.'];
    }

    $uploadDir = __DIR__ . '/../uploads/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'user_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
        return ['ok' => false, 'message' => 'Could not upload profile image.'];
    }

    return ['ok' => true, 'filename' => $filename];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'create_user' || $action === 'update_user' || $action === 'save_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $isUpdate = $id > 0;
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $userType = trim((string) ($_POST['user_type'] ?? 'Employee'));

        if ($username === '' || $fullName === '') {
            $_SESSION['crm_user_flash'] = 'Username and Full Name are required.';
            $_SESSION['crm_user_flash_type'] = 'danger';
            header('Location: users.php');
            exit;
        }

        if (!in_array($userType, ['Employee', 'Manager', 'Admin'], true)) {
            $userType = 'Employee';
        }

        $upload = crmUserUploadProfileImage();
        if (!$upload['ok']) {
            $_SESSION['crm_user_flash'] = $upload['message'];
            $_SESSION['crm_user_flash_type'] = 'danger';
            header('Location: users.php');
            exit;
        }
        $profileImage = (string) ($upload['filename'] ?? '');

        if ($isUpdate && $id > 0) {
            $check = $conn->prepare('SELECT `id`, `password`, `profile_image` FROM `users` WHERE `id` = ? AND `is_deleted` = 0 LIMIT 1');
            if (!$check) {
                $_SESSION['crm_user_flash'] = 'Could not load user for update.';
                $_SESSION['crm_user_flash_type'] = 'danger';
                header('Location: users.php');
                exit;
            }
            $check->bind_param('i', $id);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$existing) {
                $_SESSION['crm_user_flash'] = 'User not found.';
                $_SESSION['crm_user_flash_type'] = 'danger';
                header('Location: users.php');
                exit;
            }

            $newPassword = $password !== '' ? $password : (string) ($existing['password'] ?? '');
            $newImage = $profileImage !== '' ? $profileImage : (string) ($existing['profile_image'] ?? '');

            $stmt = $conn->prepare('UPDATE `users` SET `username` = ?, `password` = ?, `full_name` = ?, `user_type` = ?, `profile_image` = ? WHERE `id` = ? AND `is_deleted` = 0');
            if (!$stmt) {
                $_SESSION['crm_user_flash'] = 'Could not prepare user update.';
                $_SESSION['crm_user_flash_type'] = 'danger';
                header('Location: users.php');
                exit;
            }
            $stmt->bind_param('sssssi', $username, $newPassword, $fullName, $userType, $newImage, $id);
            $ok = $stmt->execute();
            $errno = $conn->errno;
            $stmt->close();

            if ($ok) {
                $_SESSION['crm_user_flash'] = 'User updated successfully.';
                $_SESSION['crm_user_flash_type'] = 'success';
            } else {
                $_SESSION['crm_user_flash'] = $errno === 1062 ? 'Username already exists.' : 'Could not update user.';
                $_SESSION['crm_user_flash_type'] = 'danger';
            }
            header('Location: users.php');
            exit;
        }

        if ($password === '') {
            $password = '123456';
        }

        $stmt = $conn->prepare("INSERT INTO `users` (`username`, `password`, `full_name`, `user_type`, `profile_image`, `is_deleted`) VALUES (?, ?, ?, ?, ?, 0)");
        if (!$stmt) {
            $_SESSION['crm_user_flash'] = 'Could not prepare user insert.';
            $_SESSION['crm_user_flash_type'] = 'danger';
            header('Location: users.php');
            exit;
        }

        $stmt->bind_param('sssss', $username, $password, $fullName, $userType, $profileImage);
        $ok = $stmt->execute();
        $errno = $conn->errno;
        $stmt->close();

        if ($ok) {
            $_SESSION['crm_user_flash'] = 'User created successfully.';
            $_SESSION['crm_user_flash_type'] = 'success';
        } else {
            $_SESSION['crm_user_flash'] = $errno === 1062 ? 'Username already exists.' : 'Could not create user.';
            $_SESSION['crm_user_flash_type'] = 'danger';
        }
        header('Location: users.php');
        exit;
    }
}

$users = [];
$res = $conn->query("SELECT `id`, `username`, `full_name`, `user_type`, `profile_image`, `created_at`
    FROM `users`
    WHERE `is_deleted` = 0
    ORDER BY `id` DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

$totalUsers = count($users);
$flashMsg = (string) ($_SESSION['crm_user_flash'] ?? '');
$flashType = (string) ($_SESSION['crm_user_flash_type'] ?? 'success');
unset($_SESSION['crm_user_flash'], $_SESSION['crm_user_flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>CRM Users</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-users .content-wrapper { background: #f3f4f6; }
        .crm-users .content-wrapper > .content { background: #f3f4f6; padding-top: 1.1rem; }

        .crm-users .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .crm-users .page-title {
            margin: 0;
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .crm-users .page-subtitle {
            margin: 0.35rem 0 0;
            font-size: 0.92rem;
            color: #64748b;
            font-weight: 500;
        }
        .crm-users .breadcrumbs {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 500;
            padding-top: 0.35rem;
        }
        .crm-users .breadcrumbs a { color: #94a3b8; text-decoration: none; }
        .crm-users .breadcrumbs a:hover { color: #64748b; }
        .crm-users .breadcrumbs .crumb-current { color: #38bdf8; }

        .crm-users .ls-card {
            background: #fff;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            margin-bottom: 1.25rem;
        }
        .crm-users .ls-card-hd {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1.1rem 1.25rem 0.85rem;
        }
        .crm-users .ls-card-hd-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .crm-users .ls-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fee2e2;
            color: #e11d2e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .crm-users .ls-card-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        .crm-users .ls-card-sub {
            margin: 0.15rem 0 0;
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .crm-users .ls-toolbar {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-wrap: wrap;
        }
        .crm-users .ls-search {
            position: relative;
            min-width: 220px;
        }
        .crm-users .ls-search .form-control {
            padding-right: 2.4rem;
            height: 40px;
            border-radius: 10px;
            border-color: #e2e8f0;
            box-shadow: none !important;
        }
        .crm-users .ls-search .form-control:focus {
            border-color: #fca5a5;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.1) !important;
        }
        .crm-users .ls-search > i {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            pointer-events: none;
        }
        .crm-users .btn-ls-filter {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .crm-users .btn-ls-filter:hover,
        .crm-users .btn-ls-filter.is-on {
            background: #fff5f5;
            border-color: #fecaca;
            color: #e11d2e;
        }
        .crm-users .btn-ls-add {
            height: 40px;
            border-radius: 10px;
            border: 0;
            background: #e11d2e;
            color: #fff !important;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0 1rem;
            box-shadow: 0 6px 16px rgba(225, 29, 46, 0.22);
        }
        .crm-users .btn-ls-add:hover {
            background: #c91020;
            color: #fff !important;
        }

        .crm-users .table-wrap { overflow-x: auto; }
        .crm-users table.ls-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .crm-users table.ls-table thead th {
            background: transparent;
            color: #94a3b8;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.75rem 1.15rem;
            border-bottom: 1px solid #eef2f7;
            white-space: nowrap;
        }
        .crm-users table.ls-table tbody td {
            padding: 0.95rem 1.15rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: #0f172a;
            background: #fff;
        }
        .crm-users table.ls-table tbody tr:last-child td { border-bottom: 0; }
        .crm-users table.ls-table tbody tr:hover td { background: #fafbfc; }
        .crm-users .col-serial {
            width: 56px;
            color: #94a3b8 !important;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .crm-users .user-cell {
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 0;
        }
        .crm-users .avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .crm-users .avatar-fallback {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
            border: 1px solid transparent;
        }
        .crm-users .avatar-fallback.tone-red { background: #fee2e2; color: #e11d2e; }
        .crm-users .avatar-fallback.tone-purple { background: #ede9fe; color: #7c3aed; }
        .crm-users .avatar-fallback.tone-blue { background: #dbeafe; color: #2563eb; }
        .crm-users .avatar-fallback.tone-orange { background: #ffedd5; color: #ea580c; }
        .crm-users .avatar-fallback.tone-teal { background: #ccfbf1; color: #0d9488; }
        .crm-users .avatar-fallback.tone-indigo { background: #e0e7ff; color: #4f46e5; }
        .crm-users .avatar-fallback.tone-pink { background: #fce7f3; color: #db2777; }
        .crm-users .avatar-fallback.tone-green { background: #dcfce7; color: #16a34a; }
        .crm-users .avatar-fallback.tone-cyan { background: #cffafe; color: #0891b2; }
        .crm-users .avatar-fallback.tone-amber { background: #fef3c7; color: #d97706; }

        .crm-users .user-name {
            font-weight: 700;
            font-size: 0.92rem;
            line-height: 1.25;
        }
        .crm-users .user-username {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .crm-users .type-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            height: 28px;
            padding: 0 0.7rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.78rem;
        }
        .crm-users .type-pill.is-admin { background: #fee2e2; color: #e11d2e; }
        .crm-users .type-pill.is-manager { background: #dbeafe; color: #2563eb; }
        .crm-users .type-pill.is-employee { background: #f1f5f9; color: #475569; }

        .crm-users .ls-updated {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #475569;
            font-size: 0.88rem;
            white-space: nowrap;
        }
        .crm-users .ls-updated i { color: #94a3b8; }

        .crm-users .ls-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: nowrap;
        }
        .crm-users .btn-user-edit {
            height: 34px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0 0.75rem;
            background: #fff;
            border: 1px solid #bfdbfe;
            color: #2563eb !important;
        }
        .crm-users .btn-user-edit:hover {
            background: #eff6ff;
            color: #1d4ed8 !important;
        }

        .crm-users .user-current-image {
            display: none;
            margin-top: 0.55rem;
            align-items: center;
            gap: 0.65rem;
        }
        .crm-users .user-current-image.is-on {
            display: flex;
        }
        .crm-users .user-current-image img {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
        .crm-users .user-current-image span {
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 500;
        }

        .crm-users .ls-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding: 0.95rem 1.25rem 1.1rem;
            border-top: 1px solid #eef2f7;
        }
        .crm-users .ls-footer-summary {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 500;
        }
        .crm-users .ls-pager {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .crm-users .ls-pager .btn {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.82rem;
        }
        .crm-users .ls-pager .btn:hover:not(:disabled) {
            background: #f8fafc;
            color: #0f172a;
        }
        .crm-users .ls-pager .btn.is-current {
            background: #e11d2e;
            border-color: #e11d2e;
            color: #fff !important;
        }
        .crm-users .ls-pager .btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        .crm-users .ls-empty {
            text-align: center;
            color: #94a3b8;
            padding: 2rem 1rem !important;
            font-weight: 500;
        }

        /* Add User modal — match lead source form language */
        .crm-users .user-modal-dialog { max-width: 640px; }
        .crm-users .user-modal-shell {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
        }
        .crm-users .user-modal-hd {
            background: linear-gradient(115deg, #9a121f 0%, #e11d2e 100%);
            color: #fff;
            border-bottom: 0;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .crm-users .user-modal-hd .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }
        .crm-users .user-modal-hd .modal-title .user-modal-ico {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: rgba(255,255,255,0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        .crm-users .user-modal-hd .close {
            color: #fff;
            opacity: 0.9;
            text-shadow: none;
            margin: -0.5rem -0.5rem -0.5rem auto;
        }
        .crm-users .user-modal-bd { padding: 1.25rem 1.35rem 0.5rem; background: #fff; }
        .crm-users .user-modal-bd .ls-label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.84rem;
            font-weight: 600;
            color: #334155;
        }
        .crm-users .user-modal-bd .ls-label .req { color: #e11d2e; }
        .crm-users .user-modal-bd .ls-input-icon {
            position: relative;
        }
        .crm-users .user-modal-bd .ls-input-icon > i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            pointer-events: none;
            z-index: 2;
        }
        .crm-users .user-modal-bd .ls-input-icon .form-control {
            padding-left: 2.35rem;
        }
        .crm-users .user-modal-bd .form-control {
            height: 42px;
            border-radius: 10px;
            border-color: #e2e8f0;
            font-size: 0.9rem;
            color: #0f172a;
            box-shadow: none !important;
        }
        .crm-users .user-modal-bd .form-control:focus {
            border-color: #fca5a5;
            box-shadow: 0 0 0 3px rgba(225, 29, 46, 0.1) !important;
        }
        .crm-users .user-modal-bd .form-group { margin-bottom: 1rem; }
        .crm-users .user-modal-bd .custom-file-label,
        .crm-users .user-modal-bd .custom-file-input + .custom-file-label {
            height: 42px;
            border-radius: 10px;
            border-color: #e2e8f0;
            line-height: 28px;
        }
        .crm-users .user-modal-bd .form-text {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.35rem;
        }
        .crm-users .user-modal-ft {
            border-top: 1px solid #eef2f7;
            padding: 0.95rem 1.25rem 1.1rem;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .crm-users .btn-user-cancel {
            height: 42px;
            min-width: 96px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0 1rem;
        }
        .crm-users .btn-user-cancel:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .crm-users .btn-user-save {
            height: 42px;
            border-radius: 10px;
            border: 0;
            background: #e11d2e;
            color: #fff !important;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0 1.25rem;
            box-shadow: 0 6px 16px rgba(225, 29, 46, 0.22);
        }
        .crm-users .btn-user-save:hover {
            background: #c91020;
            color: #fff !important;
        }

        @media (max-width: 767.98px) {
            .crm-users .ls-toolbar { width: 100%; }
            .crm-users .ls-search { flex: 1 1 auto; min-width: 0; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-users">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="page-title-row">
                    <div>
                        <h1 class="page-title">Users</h1>
                        <p class="page-subtitle">Manage CRM login users, roles, and profile details.</p>
                    </div>
                    <nav class="breadcrumbs">
                        <a href="dashboard.php">Home</a> /
                        <a href="crm/users.php">Users</a> /
                        <span class="crumb-current">All Users</span>
                    </nav>
                </div>

                <?php if ($flashMsg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
                        <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                <?php } ?>

                <div class="ls-card">
                    <div class="ls-card-hd">
                        <div class="ls-card-hd-left">
                            <span class="ls-card-icon"><i class="fas fa-users"></i></span>
                            <div>
                                <h2 class="ls-card-title">All Users</h2>
                                <p class="ls-card-sub">List of all active users in the CRM.</p>
                            </div>
                        </div>
                        <div class="ls-toolbar">
                            <div class="ls-search">
                                <input type="search" class="form-control js-user-search" placeholder="Search users..." autocomplete="off">
                                <i class="fas fa-search"></i>
                            </div>
                            <button type="button" class="btn btn-ls-filter js-user-filter" title="Filter by type" aria-pressed="false">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button type="button" class="btn btn-ls-add js-user-add">
                                <i class="fas fa-plus mr-1"></i> Add User
                            </button>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="ls-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th style="width:56px;">#</th>
                                    <th>User</th>
                                    <th>Username</th>
                                    <th style="width:140px;">Type</th>
                                    <th style="width:210px;">Created</th>
                                    <th style="width:120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)) { ?>
                                    <tr class="js-user-empty-row">
                                        <td colspan="6" class="ls-empty">No users found.</td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($users as $idx => $user) {
                                        $tone = crmUserToneForId((int) ($user['id'] ?? 0), $idx);
                                        $fullName = (string) ($user['full_name'] ?? '');
                                        $username = (string) ($user['username'] ?? '');
                                        $userType = (string) ($user['user_type'] ?? 'Employee');
                                        $profileImage = (string) ($user['profile_image'] ?? '');
                                        $initial = strtoupper(substr(trim($fullName) !== '' ? $fullName : $username, 0, 1));
                                        $typeClass = 'is-employee';
                                        if (strcasecmp($userType, 'Admin') === 0) {
                                            $typeClass = 'is-admin';
                                        } elseif (strcasecmp($userType, 'Manager') === 0) {
                                            $typeClass = 'is-manager';
                                        }
                                        ?>
                                        <tr
                                            class="js-user-row"
                                            data-name="<?= htmlspecialchars(strtolower($fullName . ' ' . $username), ENT_QUOTES, 'UTF-8') ?>"
                                            data-type="<?= htmlspecialchars(strtolower($userType), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <td class="col-serial js-user-serial"><?= (int) $idx + 1 ?></td>
                                            <td>
                                                <span class="user-cell">
                                                    <?php if ($profileImage !== '') { ?>
                                                        <img class="avatar" src="<?= htmlspecialchars('uploads/users/' . $profileImage, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                                    <?php } else { ?>
                                                        <span class="avatar-fallback tone-<?= htmlspecialchars($tone['key'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php } ?>
                                                    <span class="user-name" style="color: <?= htmlspecialchars($tone['color'], ENT_QUOTES, 'UTF-8') ?>;">
                                                        <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="user-username" style="color: <?= htmlspecialchars($tone['color'], ENT_QUOTES, 'UTF-8') ?>;">
                                                    <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="type-pill <?= $typeClass ?>"><?= htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td>
                                                <span class="ls-updated">
                                                    <i class="far fa-calendar-alt"></i>
                                                    <?= htmlspecialchars(crmUserFormatCreated($user['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="ls-actions">
                                                    <button
                                                        type="button"
                                                        class="btn btn-user-edit js-user-edit"
                                                        data-id="<?= (int) $user['id'] ?>"
                                                        data-full-name="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-username="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-user-type="<?= htmlspecialchars($userType, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-profile-image="<?= htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8') ?>"
                                                    ><i class="fas fa-pen"></i> Edit</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <tr class="js-user-empty-row" style="display:none;">
                                        <td colspan="6" class="ls-empty">No users match your filters.</td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ls-footer">
                        <div class="ls-footer-summary js-user-summary">
                            Showing <?= $totalUsers > 0 ? '1' : '0' ?> to <?= (int) $totalUsers ?> of <?= (int) $totalUsers ?> entries
                        </div>
                        <div class="ls-pager">
                            <button type="button" class="btn js-user-page-prev" disabled aria-label="Previous page"><i class="fas fa-chevron-left"></i></button>
                            <button type="button" class="btn is-current js-user-page-num">1</button>
                            <button type="button" class="btn js-user-page-next" disabled aria-label="Next page"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered user-modal-dialog" role="document">
            <div class="modal-content user-modal-shell">
                <form method="post" enctype="multipart/form-data" id="addUserForm">
                    <input type="hidden" name="action" id="userFormAction" value="create_user">
                    <input type="hidden" name="id" id="userFormId" value="0">
                    <div class="modal-header user-modal-hd">
                        <h5 class="modal-title" id="addUserModalLabel">
                            <span class="user-modal-ico js-user-modal-ico"><i class="fas fa-user-plus"></i></span>
                            <span class="js-user-modal-title">Add User</span>
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body user-modal-bd">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="ls-label" for="userFullName">Full Name <span class="req">*</span></label>
                                <div class="ls-input-icon">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" name="full_name" id="userFullName" class="form-control" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="ls-label" for="userUsername">Username <span class="req">*</span></label>
                                <div class="ls-input-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="username" id="userUsername" class="form-control" placeholder="Enter username" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="ls-label" for="userPassword">Password</label>
                                <div class="ls-input-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="text" name="password" id="userPassword" class="form-control" placeholder="Enter password">
                                </div>
                                <div class="form-text js-user-password-hint">Default is <strong>123456</strong> if left empty.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="ls-label" for="userType">User Type</label>
                                <div class="ls-input-icon">
                                    <i class="fas fa-user-tag"></i>
                                    <select name="user_type" id="userType" class="form-control">
                                        <option value="Employee">Employee</option>
                                        <option value="Manager">Manager</option>
                                        <option value="Admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="ls-label" for="userProfileImage">Profile Image</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="userProfileImage" name="profile_image" accept="image/*">
                                <label class="custom-file-label" for="userProfileImage">Choose image…</label>
                            </div>
                            <div class="user-current-image js-user-current-image">
                                <img src="" alt="Current profile" class="js-user-current-image-src">
                                <span>Current image — upload a new file to replace it.</span>
                            </div>
                            <div class="form-text">Optional. JPG, PNG, WEBP, or GIF.</div>
                        </div>
                    </div>
                    <div class="modal-footer user-modal-ft">
                        <button type="button" class="btn btn-user-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-user-save js-user-save-btn"><i class="fas fa-save mr-1"></i> <span>Create User</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
<script>
(function () {
    var perPage = 10;
    var currentPage = 1;
    var typeFilter = '';

    function resetUserForm() {
        jQuery('#userFormAction').val('create_user');
        jQuery('#userFormId').val('0');
        jQuery('#userFullName').val('');
        jQuery('#userUsername').val('');
        jQuery('#userPassword').val('').attr('placeholder', 'Enter password');
        jQuery('#userType').val('Employee');
        jQuery('#userProfileImage').val('');
        jQuery('#userProfileImage').next('.custom-file-label').text('Choose image…');
        jQuery('.js-user-current-image').removeClass('is-on').find('.js-user-current-image-src').attr('src', '');
        jQuery('.js-user-password-hint').html('Default is <strong>123456</strong> if left empty.');
        jQuery('.js-user-modal-title').text('Add User');
        jQuery('.js-user-modal-ico').html('<i class="fas fa-user-plus"></i>');
        jQuery('.js-user-save-btn span').text('Create User');
    }

    function openCreateUserModal() {
        resetUserForm();
        jQuery('#addUserModal').modal('show');
    }

    function openEditUserModal($btn) {
        resetUserForm();
        var id = parseInt($btn.attr('data-id'), 10) || 0;
        var image = ($btn.attr('data-profile-image') || '').trim();

        jQuery('#userFormAction').val('update_user');
        jQuery('#userFormId').val(String(id));
        jQuery('#userFullName').val($btn.attr('data-full-name') || '');
        jQuery('#userUsername').val($btn.attr('data-username') || '');
        jQuery('#userPassword').val('').attr('placeholder', 'Leave blank to keep current password');
        jQuery('#userType').val($btn.attr('data-user-type') || 'Employee');
        jQuery('.js-user-password-hint').html('Leave blank to keep the <strong>current password</strong>.');
        jQuery('.js-user-modal-title').text('Edit User');
        jQuery('.js-user-modal-ico').html('<i class="fas fa-user-edit"></i>');
        jQuery('.js-user-save-btn span').text('Update User');

        if (image) {
            jQuery('.js-user-current-image').addClass('is-on')
                .find('.js-user-current-image-src').attr('src', 'uploads/users/' + image);
        }

        jQuery('#addUserModal').modal('show');
    }

    function filteredRows() {
        var q = (jQuery('.js-user-search').val() || '').toString().trim().toLowerCase();
        return jQuery('#usersTable tbody tr.js-user-row').filter(function () {
            var $row = jQuery(this);
            var name = ($row.attr('data-name') || '');
            var type = ($row.attr('data-type') || '');
            if (typeFilter && type !== typeFilter) {
                return false;
            }
            if (q && name.indexOf(q) === -1) {
                return false;
            }
            return true;
        });
    }

    function renderList() {
        var $all = jQuery('#usersTable tbody tr.js-user-row');
        var $rows = filteredRows();
        var total = $rows.length;
        var pages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > pages) currentPage = pages;
        if (currentPage < 1) currentPage = 1;

        $all.hide();
        var start = (currentPage - 1) * perPage;
        var end = start + perPage;
        $rows.each(function (i) {
            var show = i >= start && i < end;
            jQuery(this).toggle(show);
            if (show) {
                jQuery(this).find('.js-user-serial').text(i + 1);
            }
        });

        var from = total === 0 ? 0 : start + 1;
        var to = total === 0 ? 0 : Math.min(end, total);
        jQuery('.js-user-summary').text('Showing ' + from + ' to ' + to + ' of ' + total + ' entries');
        jQuery('.js-user-page-num').text(String(currentPage));
        jQuery('.js-user-page-prev').prop('disabled', currentPage <= 1);
        jQuery('.js-user-page-next').prop('disabled', currentPage >= pages || total === 0);

        var hasStaticEmpty = $all.length === 0;
        jQuery('#usersTable tbody tr.js-user-empty-row').toggle(!hasStaticEmpty && total === 0);
    }

    jQuery('.js-user-search').on('input', function () {
        currentPage = 1;
        renderList();
    });

    jQuery('.js-user-filter').on('click', function () {
        var cycle = ['', 'admin', 'manager', 'employee'];
        var idx = cycle.indexOf(typeFilter);
        typeFilter = cycle[(idx + 1) % cycle.length];
        var on = typeFilter !== '';
        jQuery(this).toggleClass('is-on', on).attr('aria-pressed', on ? 'true' : 'false');
        jQuery(this).attr('title', on ? ('Showing ' + typeFilter) : 'Filter by type');
        currentPage = 1;
        renderList();
    });

    jQuery('.js-user-page-prev').on('click', function () {
        if (currentPage > 1) {
            currentPage -= 1;
            renderList();
        }
    });
    jQuery('.js-user-page-next').on('click', function () {
        currentPage += 1;
        renderList();
    });

    jQuery('#userProfileImage').on('change', function () {
        var name = (this.files && this.files[0] && this.files[0].name) ? this.files[0].name : 'Choose image…';
        jQuery(this).next('.custom-file-label').text(name);
    });

    jQuery(document).on('click', '.js-user-edit', function () {
        openEditUserModal(jQuery(this));
    });

    jQuery(document).on('click', '.js-user-add', function (e) {
        e.preventDefault();
        openCreateUserModal();
    });

    jQuery('#addUserModal').on('shown.bs.modal', function () {
        jQuery('#userFullName').trigger('focus');
    }).on('hidden.bs.modal', function () {
        resetUserForm();
    });

    renderList();
})();
</script>
</body>
</html>
