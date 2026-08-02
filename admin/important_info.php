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

                    <div class="card card-primary">

                        <div class="card-header">
                            <h3 class="card-title">Important Information</h3>
                        </div>

                        <form method="post" action="action.php">

                            <div class="card-body">

                                <?php
                                $q = mysqli_query($conn, "SELECT * FROM ticket_settings LIMIT 1");
                                $data = mysqli_fetch_assoc($q);
                                ?>

                                <textarea name="important_info" id="editor" class="form-control"
                                    rows="10"><?php echo $data['important_info']; ?></textarea>

                            </div>

                            <div class="card-footer">
                                <button type="submit" name="save_eticket_info" class="btn btn-primary">
                                    Save Information
                                </button>
                            </div>

                        </form>

                    </div>

                </div>
            </section>
        </div>

        <?php include 'includes/copyright.php'; ?>
    </div>

    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>

    <script>
        CKEDITOR.replace('editor');
    </script>

    <script>
        document.querySelector('form').addEventListener('submit', function () {
            for (var instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
        });
    </script>

    <?php include 'includes/footer-links.php'; ?>

</body>

</html>