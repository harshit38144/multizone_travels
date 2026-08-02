<?php
session_start();
include 'connection.php';

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
    <title>Quotation List</title>
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

                    <!-- PAGE HEADER -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <a href="generate_quotation.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Generate Quotation
                            </a>
                        </div>

                        <div class="col-md-3 offset-md-3">
                            <select class="form-control">
                                <option>2025-2026</option>
                                <option>2024-2025</option>
                                <option>2023-2024</option>
                            </select>
                        </div>
                    </div>

                    <!-- QUOTATION LIST -->
                    <div class="card">

                        <div class="card-header">
                            <h3 class="card-title">Quotation List</h3>
                        </div>

                        <div class="card-body">

                            <table id="quotationTable" class="table table-bordered table-striped">

                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Guest Name</th>
                                        <th>Destination</th>
                                        <th>Status</th>
                                        <th>Book</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php

                                    $sql = "SELECT * FROM quotations ORDER BY id DESC";
                                    $run = mysqli_query($conn, $sql);

                                    $i = 1;

                                    while ($row = mysqli_fetch_assoc($run)) {

                                        ?>

                                        <tr>

                                            <td>
                                                <?php echo $i++; ?>
                                            </td>

                                            <td>
                                                <?php echo date('d-m-Y', strtotime($row['created_at'])); ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?php echo $row['guest_name']; ?>
                                                </strong><br>
                                                <?php echo $row['mobile']; ?>
                                            </td>

                                            <td>
                                                <?php echo $row['destination']; ?><br>
                                                <?php echo date('d/m/Y', strtotime($row['travel_date'])); ?>
                                            </td>

                                            <td>
                                                <span class="badge badge-warning">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>

                                            <td>
                                                <a href="booking.php?id=<?php echo $row['id']; ?>"
                                                    class="btn btn-sm btn-secondary">
                                                    Book
                                                </a>
                                            </td>

                                            <td>

                                                <div class="dropdown">

                                                    <button class="btn btn-sm btn-light dropdown-toggle"
                                                        data-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-right">

                                                        <a class="dropdown-item"
                                                            href="view_quotation.php?id=<?php echo $row['id']; ?>">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>

                                                        <a class="dropdown-item"
                                                            href="edit_quotation.php?id=<?php echo $row['id']; ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>

                                                        <a class="dropdown-item text-danger"
                                                            onclick="return confirm('Delete this quotation?')"
                                                            href="delete_quotation.php?id=<?php echo $row['id']; ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>

                                                    </div>

                                                </div>

                                            </td>

                                        </tr>

                                    <?php } ?>

                                </tbody>

                            </table>

                        </div>
                    </div>

                </div>
            </section>

        </div>

        <?php include 'includes/copyright.php'; ?>

    </div>

    <?php include 'includes/footer-links.php'; ?>

    <script>

        $(document).ready(function () {

            $('#quotationTable').DataTable({
                responsive: true,
                ordering: false
            });

        });

    </script>

</body>

</html>