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

                    <div class="row">

                        <!-- Important Information -->
                        <div class="col-lg-4 col-12">
                            <div class="small-box bg-info">

                                <div class="inner">
                                    <h4>Important Info</h4>
                                    <p>Manage ticket important information</p>
                                </div>

                                <div class="icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>

                                <a href="important_info.php" class="small-box-footer">

                                    Manage <i class="fas fa-arrow-circle-right"></i>

                                </a>

                            </div>
                        </div>


                        <!-- Banner Image -->
                        <div class="col-lg-4 col-12">
                            <div class="small-box bg-success">

                                <div class="inner">
                                    <h4>Banner Image</h4>
                                    <p>Change ticket banner image</p>
                                </div>

                                <div class="icon">
                                    <i class="fas fa-image"></i>
                                </div>

                                <a href="image_master.php" class="small-box-footer">

                                    Manage <i class="fas fa-arrow-circle-right"></i>

                                </a>

                            </div>
                        </div>

                    </div>

                </div>
            </section>
        </div>

        <?php include 'includes/copyright.php'; ?>
    </div>

    <?php include 'includes/footer-links.php'; ?>

</body>

</html>