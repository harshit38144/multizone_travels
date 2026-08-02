<?php
session_start();
if ($_SESSION['role'] != '1') {
    header('location:index.php');
}
include 'connection.php';

if (!isset($_GET['id']) || $_GET['id'] == '') {
    header('location:show_user.php');
    exit;
}

$id = intval($_GET['id']);

$sql = "SELECT * FROM users WHERE id = $id AND is_deleted = 0";
$res = mysqli_query($conn, $sql);

if (mysqli_num_rows($res) == 0) {
    header('location:show_user.php');
    exit;
}

$user = mysqli_fetch_assoc($res);

$msg = "";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Edit User</title>
    <?php include 'includes/header-links.php'; ?>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include 'includes/top-header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <?php include 'includes/page-header.php'; ?>

        <section class="content">
            <div class="container-fluid">

                <div class="card">

                    <!-- HEADER -->
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <h5 class="mb-0">Edit User</h5>
                            <a href="show_user.php" class="font-weight-bold text-primary">
                                Back to Users
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <form action="action.php" method="POST" enctype="multipart/form-data">

                            <input type="hidden" name="user_id" value="<?= $user['id']; ?>">

                            <!-- Profile Image -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">Profile Image</label>
                                <div class="col-md-10">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="file" name="profile_image" class="form-control" id="profileImageInput" accept="image/*">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary" type="button" id="browseBtn">
                                                        <i class="fas fa-folder-open"></i> Browse
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if (!empty($user['profile_image'])): ?>
                                                <img src="uploads/users/<?= htmlspecialchars($user['profile_image']); ?>" 
                                                     alt="Profile" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 80px; height: 80px; background-color: #e0e0e0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user" style="color: #999; font-size: 32px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Username -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="col-md-10">
                                    <input type="text" name="username" class="form-control"
                                           value="<?= htmlspecialchars($user['username']); ?>" readonly>
                                    <small class="form-text text-muted">Username cannot be changed</small>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">Password</label>
                                <div class="col-md-10">
                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password" id="passwordInput">
                                    <small class="form-text text-muted">Leave blank if you don't want to change the password</small>
                                </div>
                            </div>

                            <!-- Show Password -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label"></label>
                                <div class="col-md-10">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="showPasswordCheck">
                                        <label class="custom-control-label" for="showPasswordCheck">
                                            Show Password
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Full Name -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <div class="col-md-10">
                                    <input type="text" name="full_name" class="form-control"
                                           value="<?= htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                            </div>

                            <!-- User Type -->
                            <div class="form-group row">
                                <label class="col-md-2 col-form-label">User Type</label>
                                <div class="col-md-10">
                                    <select name="user_type" class="form-control">
                                        <option value="Employee" <?= $user['user_type'] == 'Employee' ? 'selected' : ''; ?>>Employee</option>
                                        <option value="Manager" <?= $user['user_type'] == 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Admin" <?= $user['user_type'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="form-group row">
                                <div class="col-md-10 offset-md-2 text-right">
                                    <button type="submit" name="update_user" class="btn btn-primary px-4">
                                        Update
                                    </button>
                                    <a href="show_user.php" class="btn btn-secondary px-4 ml-2">
                                        Cancel
                                    </a>
                                </div>
                            </div>

                        </form>
                    </div>

                </div>

            </div>
        </section>
    </div>

    <?php include 'includes/copyright.php'; ?>
</div>

<?php include 'includes/footer-links.php'; ?>

<script>
    // Browse button functionality
    document.getElementById('browseBtn').addEventListener('click', function() {
        document.getElementById('profileImageInput').click();
    });

    // Show/Hide Password functionality
    document.getElementById('showPasswordCheck').addEventListener('change', function() {
        var passwordInput = document.getElementById('passwordInput');
        if (this.checked) {
            passwordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
        }
    });
</script>

</body>
</html>
