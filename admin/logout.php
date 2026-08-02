<?php
session_start();
include 'connection.php';

$admin_id = $_SESSION['id'] ?? 0;

mysqli_query($conn,
  "INSERT INTO admin_log_history (admin_id,message)
   VALUES ('$admin_id','Logout: admin logged out.')"
);

session_destroy();
header("Location: index.php");
exit;
