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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'create_user') {
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

        if ($password === '') {
            $password = '123456';
        }

        if (!in_array($userType, ['Employee', 'Manager', 'Admin'], true)) {
            $userType = 'Employee';
        }

        $profileImage = '';
        if (!empty($_FILES['profile_image']['name']) && !empty($_FILES['profile_image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed, true)) {
                $_SESSION['crm_user_flash'] = 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.';
                $_SESSION['crm_user_flash_type'] = 'danger';
                header('Location: users.php');
                exit;
            }

            $uploadDir = __DIR__ . '/../uploads/users/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = 'user_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadDir . $filename)) {
                $_SESSION['crm_user_flash'] = 'Could not upload profile image.';
                $_SESSION['crm_user_flash_type'] = 'danger';
                header('Location: users.php');
                exit;
            }
            $profileImage = $filename;
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

$flashMsg = (string) ($_SESSION['crm_user_flash'] ?? '');
$flashType = (string) ($_SESSION['crm_user_flash_type'] ?? 'success');
unset($_SESSION['crm_user_flash'], $_SESSION['crm_user_flash_type']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <base href="../">
    <title>CRM Users</title>
    <?php include __DIR__ . '/../includes/header-links.php'; ?>
    <style>
        .crm-users .content-wrapper>.content { background: #f4f6f9; }
        .crm-users .page-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
        .crm-users .page-title { font-size: 1.85rem; font-weight: 700; color: #111; margin: 0; }
        .crm-users .breadcrumbs { font-size: 0.875rem; color: #007bff; }
        .crm-users .breadcrumbs a { color: #007bff; }
        .crm-users .master-card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, .06); overflow: hidden; }
        .crm-users .master-card-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
        .crm-users .master-card-head h2 { margin: 0; font-size: 1.15rem; font-weight: 700; color: #222; }
        .crm-users .btn-add-user { background: #007bff; border: 0; color: #fff; font-weight: 600; padding: 0.45rem 1rem; border-radius: 4px; }
        .crm-users .btn-add-user:hover { background: #0069d9; color: #fff; }
        .crm-users .table-wrap { overflow-x: auto; }
        .crm-users table { width: 100%; margin: 0; border-collapse: collapse; font-size: 0.9rem; }
        .crm-users th { background: #212529; color: #fff; padding: 0.65rem 0.85rem; white-space: nowrap; }
        .crm-users td { padding: 0.6rem 0.85rem; border-top: 1px solid #dee2e6; vertical-align: middle; }
        .crm-users tbody tr:nth-child(even) { background: #f8f9fa; }
        .crm-users .avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid #ced4da; }
        .crm-users .avatar-fallback { width: 34px; height: 34px; border-radius: 50%; background: #e9ecef; color: #6c757d; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; border: 1px solid #ced4da; }
        .crm-users .user-modal-dialog { max-width: 760px; }
        .crm-users .user-modal-header { background: #007bff; color: #fff; }
        .crm-users .user-modal-header .close { color: #fff; opacity: 0.9; text-shadow: none; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper crm-users">
    <?php include __DIR__ . '/../includes/top-header.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <?php include __DIR__ . '/../includes/page-header.php'; ?>
        <section class="content">
            <div class="container-fluid">
                <div class="page-title-row">
                    <h1 class="page-title">Users</h1>
                    <nav class="breadcrumbs"><a href="dashboard.php">Home</a> / <a href="crm/users.php">Users</a></nav>
                </div>

                <?php if ($flashMsg !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php } ?>

                <div class="master-card">
                    <div class="master-card-head">
                        <h2>All Users</h2>
                        <button type="button" class="btn btn-add-user" data-toggle="modal" data-target="#addUserModal">
                            <i class="fas fa-plus mr-1"></i> Add User
                        </button>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:72px;">#</th>
                                    <th style="width:72px;">Photo</th>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th style="width:140px;">Type</th>
                                    <th style="width:180px;">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)) { ?>
                                    <tr><td colspan="6" class="text-center text-muted">No users found.</td></tr>
                                <?php } else { ?>
                                    <?php foreach ($users as $idx => $user) { ?>
                                        <tr>
                                            <td><?= (int) $idx + 1 ?></td>
                                            <td>
                                                <?php if (!empty($user['profile_image'])) { ?>
                                                    <img class="avatar" src="<?= htmlspecialchars('uploads/users/' . $user['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt="User">
                                                <?php } else { ?>
                                                    <span class="avatar-fallback"><i class="fas fa-user"></i></span>
                                                <?php } ?>
                                            </td>
                                            <td class="font-weight-bold"><?= htmlspecialchars((string) $user['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $user['user_type'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered user-modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header user-modal-header">
                        <h5 class="modal-title mb-0" id="addUserModalLabel">Add User</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Password <small class="text-muted">(default: 123456 if empty)</small></label>
                                <input type="text" name="password" class="form-control" placeholder="Enter password">
                            </div>
                            <div class="form-group col-md-4">
                                <label>User Type</label>
                                <select name="user_type" class="form-control">
                                    <option value="Employee">Employee</option>
                                    <option value="Manager">Manager</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Profile Image</label>
                                <input type="file" name="profile_image" class="form-control-file" accept="image/*">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer-links.php'; ?>
</div>
</body>
</html>
