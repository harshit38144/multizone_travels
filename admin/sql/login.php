<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once('connection.php'); // make sure connection exists

if (isset($_POST['adminlogin'])) {

    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $pass = mysqli_real_escape_string($conn, $_POST['pass']);

    $query = "SELECT * FROM admin WHERE name='$name' AND password='$pass'";
    $run = mysqli_query($conn, $query);

    // CHECK QUERY ERROR
    if (!$run) {
        die("Query Failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($run) > 0) {

        $data = mysqli_fetch_assoc($run);

        $_SESSION['id'] = $data['id'];
        $_SESSION['name'] = $data['name'];
        $_SESSION['email'] = $data['email'];
        $_SESSION['role'] = $data['role'];

        // LOGIN LOG
        $admin_id = $data['id'];
        $msg = "Login: admin logged in.";

        mysqli_query(
            $conn,
            "INSERT INTO admin_log_history (admin_id, message)
             VALUES ('$admin_id','$msg')"
        );

        header('Location: dashboard.php');
        exit;

    } else {

        // FAILED LOGIN LOG
        $msg = "Failed Login Attempt: username ($name)";

        mysqli_query(
            $conn,
            "INSERT INTO admin_log_history (admin_id, message)
             VALUES (0,'$msg')"
        );

        $_SESSION['msg'] = 'Invalid Username or Password!';
        $_SESSION['msg_type'] = 'error';
        header("Location: index.php");
        exit;
    }
}

if (isset($_POST['forgot_password'])) {
    $email = mysqli_real_escape_string($conn, $_POST['reset_email']);
    
    $query = "SELECT * FROM admin WHERE email='$email'";
    $run = mysqli_query($conn, $query);
    
    if ($run && mysqli_num_rows($run) > 0) {
        $data = mysqli_fetch_assoc($run);
        $admin_id = $data['id'];
        $admin_name = $data['name'];
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update token in database
        $update_query = "UPDATE admin SET reset_token='$token', reset_token_expire='$expiry' WHERE id=$admin_id";
        mysqli_query($conn, $update_query);
        
        $reset_link = "http://{$_SERVER['HTTP_HOST']}/multizone_travels/admin/reset_password.php?token=$token";
        
        // Send email using PHPMailer
        require '../vendor/autoload.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'cliffmediarnc@gmail.com';
            $mail->Password   = 'rjur diwp ssla ujhl';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('cliffmediarnc@gmail.com', 'Multizone Travels Admin');
            $mail->addAddress($email, $admin_name);

            $mail->isHTML(true);
            $mail->Subject = 'Admin Password Reset - Multizone Travels';
            
            $email_content = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e8ed; border-radius: 8px;'>
                <h2 style='color: #2c3e50; text-align: center; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>Password Reset Request</h2>
                <p>Hello <strong>{$admin_name}</strong>,</p>
                <p>You have requested to reset your password for the Multizone Travels Admin Panel.</p>
                <p>Please click the button below to set a new password. This link will expire in 1 hour.</p>
                <div style='text-align: center; margin-top: 30px; margin-bottom: 30px;'>
                    <a href='{$reset_link}' style='background-color: #3498db; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reset Password</a>
                </div>
                <p style='color: #7f8c8d; font-size: 14px;'>If you did not request this, please ignore this email. Your password will remain unchanged.</p>
            </div>
            ";
            
            $mail->Body    = $email_content;
            $mail->AltBody = "Hello {$admin_name},\n\nYou have requested to reset your password. Please copy and paste this link into your browser to set a new password: \n\n{$reset_link}\n\nThis link will expire in 1 hour.";

            $mail->send();
            
            $log_msg = "Password Reset Link Sent to email ($email)";
            mysqli_query($conn, "INSERT INTO admin_log_history (admin_id, message) VALUES ('$admin_id','$log_msg')");
            
            $_SESSION['msg'] = "A password reset link has been sent to your email address.";
            
        } catch (\Exception $e) {
            $log_msg = "Password Reset Email Failed for email ($email). Error: {$mail->ErrorInfo}";
            mysqli_query($conn, "INSERT INTO admin_log_history (admin_id, message) VALUES ('$admin_id','$log_msg')");
            
            $_SESSION['msg'] = "Failed to send reset email. Please contact support.";
        }
        
    } else {
        // Still show the same message to prevent email enumeration
        $log_msg = "Failed Password Reset Attempt for unknown email ($email)";
        mysqli_query($conn, "INSERT INTO admin_log_history (admin_id, message) VALUES (0,'$log_msg')");
        
        $_SESSION['msg'] = "If that email is in our system, a password reset link has been sent.";
    }
    
    header("Location: index.php");
    exit;
}
?>