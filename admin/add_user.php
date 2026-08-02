<?php
session_start();
if ($_SESSION['role'] != '1') {
    header('location:index.php');
}
include 'connection.php';

$msg = "";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}
if ($msg != "") {
    echo "<script>alert('$msg')</script>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Add User</title>
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

                        <!-- 🔥 FIXED HEADER -->
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <h5 class="mb-0">Add User</h5>
                                <a href="show_user.php" class="font-weight-bold text-primary">
                                    Show User
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <form action="action.php" method="POST" enctype="multipart/form-data">

                                <!-- Profile Image -->
                                <div class="form-group row">
                                    <label class="col-md-2 col-form-label">Profile Image</label>
                                    <div class="col-md-10">
                                        <div class="input-group">
                                            <input type="file" name="profile_image" class="form-control" id="profileImageInput" accept="image/*">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-secondary" type="button" id="browseBtn">
                                                    <i class="fas fa-folder-open"></i> Browse
                                                </button>
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
                                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                                    </div>
                                </div>

                                <!-- Password -->
                                <div class="form-group row">
                                    <label class="col-md-2 col-form-label">Password</label>
                                    <div class="col-md-10">
                                        <input type="password" name="password" class="form-control" placeholder="Password" id="passwordInput">
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
                                        <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                                    </div>
                                </div>

                                <!-- User Type -->
                                <div class="form-group row">
                                    <label class="col-md-2 col-form-label">User Type</label>
                                    <div class="col-md-10">
                                        <select name="user_type" class="form-control">
                                            <option value="Employee">Employee</option>
                                            <option value="Manager">Manager</option>
                                            <option value="Admin">Admin</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="form-group row">
                                    <div class="col-md-10 offset-md-2 text-right">
                                        <button type="submit" name="add_user" class="btn btn-secondary px-4">
                                            Submit
                                        </button>
                                        <button type="reset" class="btn btn-info px-4 ml-2">
                                            Reset
                                        </button>
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
