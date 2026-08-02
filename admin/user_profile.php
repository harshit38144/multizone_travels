<?php
session_start();
if ($_SESSION['role'] != '1') {
    header('location:index.php');
    exit;
}
include 'connection.php';

$admin_id = $_SESSION['id'];

// Handle Form Submit
if (isset($_POST['update_profile'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    
    if (!empty($password)) {
        // Update with password
        $sql = "UPDATE admin SET name='$name', email='$email', password='$password' WHERE id=$admin_id";
    } else {
        // Update without password
        $sql = "UPDATE admin SET name='$name', email='$email' WHERE id=$admin_id";
    }
    
    if ($conn->query($sql)) {
        // Update session variables
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        
        $_SESSION['msg'] = "Profile updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "Error updating profile: " . $conn->error;
        $_SESSION['msg_type'] = "danger";
    }
    
    header("Location: user_profile.php");
    exit;
}

$msg = "";
$msg_type = "success";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    $msg_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : 'success';
    unset($_SESSION['msg'], $_SESSION['msg_type']);
}

// Fetch current admin details
$adminData = $conn->query("SELECT * FROM admin WHERE id=$admin_id")->fetch_assoc();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Profile</title>
    <?php include 'includes/header-links.php'; ?>

    <style>
        .page-bg { background-color: #f4f6f9; }
        
        .card-header-purple {
            background: linear-gradient(135deg, #6a11cb 0%, #7b5ea7 50%, #a855f7 100%);
            color: #fff;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #a855f7;
            box-shadow: 0 0 0 0.2rem rgba(168, 85, 247, 0.25);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(106, 17, 203, 0.3);
            color: white;
        }
        
        .profile-icon {
            font-size: 80px;
            color: #a855f7;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed page-bg">
    <div class="wrapper">
        <?php include 'includes/top-header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2 align-items-center">
                        <div class="col-sm-6">
                            <h1 class="m-0 text-dark"><i class="fas fa-user-circle mr-2"></i>User Profile</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                                <li class="breadcrumb-item active">User Profile</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <?php if($msg != ""): ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
                            <i class="fas <?= $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                            <?= $msg ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card main-card">
                                <div class="card-header-purple">
                                    <h3 class="card-title m-0"><i class="fas fa-user-edit mr-2"></i>Update Profile Details</h3>
                                </div>
                                <div class="card-body p-4">
                                    <div class="text-center">
                                        <i class="fas fa-user-circle profile-icon"></i>
                                        <h4 class="mb-4"><?= htmlspecialchars($adminData['name']) ?></h4>
                                    </div>
                                    
                                    <form action="user_profile.php" method="POST">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                        </div>
                                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($adminData['name']) ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                        </div>
                                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($adminData['email']) ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label class="form-label">New Password</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                                        </div>
                                                        <input type="password" name="password" id="adminPassword" class="form-control" placeholder="Leave blank to keep current password">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Show/Hide Password">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-primary" type="button" id="generatePassword" title="Generate Random Password">
                                                                <i class="fas fa-random"></i> Generate
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted mt-1 d-block"><i class="fas fa-info-circle mr-1"></i>Only fill this if you want to change your password.</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-4">
                                            <div class="col-md-12 text-center">
                                                <button type="submit" name="update_profile" class="btn btn-save">
                                                    <i class="fas fa-save mr-2"></i>Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        
        <?php include 'includes/footer-links.php'; ?>
        <script>
            document.getElementById('togglePassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('adminPassword');
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });

            document.getElementById('generatePassword').addEventListener('click', function() {
                const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
                let password = "";
                for (let i = 0; i < 12; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                
                const passwordInput = document.getElementById('adminPassword');
                passwordInput.value = password;
                passwordInput.type = 'text'; // Show the generated password so the admin can copy it
                
                const icon = document.getElementById('togglePassword').querySelector('i');
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            });
        </script>
    </div>
</body>
</html>