<?php
session_start();
include 'connection.php';

$msg = "";
$msg_type = "";
$valid_token = false;
$admin_id = 0;

if (isset($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    $current_time = date('Y-m-d H:i:s');
    
    $query = "SELECT id FROM admin WHERE reset_token='$token' AND reset_token_expire >= '$current_time'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $valid_token = true;
        $row = $result->fetch_assoc();
        $admin_id = $row['id'];
    } else {
        $msg = "Invalid or expired password reset token.";
        $msg_type = "danger";
    }
} else {
    header("Location: index.php");
    exit;
}

if (isset($_POST['reset_password_submit'])) {
    $token = mysqli_real_escape_string($conn, $_POST['token']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password']);
    
    if ($password !== $confirm_password) {
        $msg = "Passwords do not match.";
        $msg_type = "danger";
        $valid_token = true; // Keep form visible
    } else {
        // Verify token again
        $current_time = date('Y-m-d H:i:s');
        $query = "SELECT id FROM admin WHERE reset_token='$token' AND reset_token_expire >= '$current_time'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $admin_id = $row['id'];
            
            // Update password and clear token
            $update = "UPDATE admin SET password='$password', reset_token=NULL, reset_token_expire=NULL WHERE id=$admin_id";
            if ($conn->query($update)) {
                // Log it
                mysqli_query($conn, "INSERT INTO admin_log_history (admin_id, message) VALUES ($admin_id, 'Password successfully reset via token')");
                
                $_SESSION['msg'] = "Password has been successfully reset. You can now login.";
                header("Location: index.php");
                exit;
            } else {
                $msg = "Error updating password.";
                $msg_type = "danger";
                $valid_token = true;
            }
        } else {
            $msg = "Invalid or expired password reset token.";
            $msg_type = "danger";
            $valid_token = false;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reset Password - Multizone Travels</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php include 'includes/header-links.php'; ?>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"; }
    .login-page { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); position: relative; overflow: hidden; }
    
    /* Animated background elements */
    .floating-element { position: absolute; background: rgba(255, 255, 255, 0.1); border-radius: 50%; pointer-events: none; }
    .element1 { width: 300px; height: 300px; top: -100px; left: -100px; animation: float 20s infinite; }
    .element2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation: float 15s infinite reverse; }
    .element3 { width: 150px; height: 150px; top: 50%; left: 10%; animation: float 18s infinite 2s; }
    
    @keyframes float {
      0%, 100% { transform: translate(0, 0) rotate(0deg); }
      25% { transform: translate(50px, 50px) rotate(90deg); }
      50% { transform: translate(0, 100px) rotate(180deg); }
      75% { transform: translate(-50px, 50px) rotate(270deg); }
    }
    
    .login-box { width: 450px; margin: 7% auto; position: relative; z-index: 2; }
    .card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 30px 40px 20px; text-align: center; }
    .card-header h2 { color: #2c3e50; font-weight: 700; margin: 0; font-size: 28px; }
    .card-body { padding: 40px; }
    .form-control { border-radius: 10px; padding: 12px 20px; border: 1px solid #e1e8ed; background: #f8f9fa; }
    .form-control:focus { border-color: #764ba2; box-shadow: 0 0 0 0.2rem rgba(118, 75, 162, 0.25); background: white; }
    .btn-login { width: 100%; padding: 12px; background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 16px; margin-top: 20px; cursor: pointer; transition: all 0.3s ease; }
    .btn-login:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4); }
    .error-message { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 15px; border-radius: 15px; margin-bottom: 20px; text-align: center; font-weight: 500; }
    .success-message { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 15px; border-radius: 15px; margin-bottom: 20px; text-align: center; font-weight: 500; }
  </style>
</head>
<body class="login-page">
  <div class="floating-element element1"></div>
  <div class="floating-element element2"></div>
  <div class="floating-element element3"></div>

  <div class="login-box">
    <div class="card">
      <div class="card-header">
        <h2>Set New Password</h2>
      </div>
      <div class="card-body">
        <?php if ($msg != ""): ?>
          <div class="<?php echo $msg_type == 'danger' ? 'error-message' : 'success-message'; ?>">
            <i class="fas <?php echo $msg_type == 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
            <?php echo $msg; ?>
          </div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
        <form action="" method="post">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
          
          <div class="form-group">
            <label style="font-weight: 600; color: #495057;"><i class="fas fa-lock"></i> New Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password" required>
          </div>
          
          <div class="form-group mt-3">
            <label style="font-weight: 600; color: #495057;"><i class="fas fa-check-double"></i> Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
          </div>
          
          <button type="submit" name="reset_password_submit" class="btn-login">
            Update Password <i class="fas fa-arrow-right ml-2"></i>
          </button>
        </form>
        <?php else: ?>
          <div class="text-center mt-4">
            <a href="index.php" class="btn btn-secondary" style="border-radius: 10px; padding: 10px 20px;">Return to Login</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>