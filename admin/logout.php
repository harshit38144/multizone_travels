<?php
session_start();
include 'connection.php';

$admin_id = $_SESSION['id'] ?? 0;

mysqli_query($conn,
  "INSERT INTO admin_log_history (admin_id,message)
   VALUES ('$admin_id','Logout: admin logged out.')"
);

unset($_SESSION['mz_tabs_token']);
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Signing out…</title>
  <script>
    try { sessionStorage.removeItem('mz.admin.tabs.v1'); } catch (e) {}
    window.location.replace('index.php');
  </script>
  <meta http-equiv="refresh" content="0;url=index.php">
</head>
<body>
  <p>Signing out… <a href="index.php">Continue</a></p>
</body>
</html>
<?php
exit;
