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
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Activity</title>

    <?php include 'includes/header-links.php'; ?>

    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

    <style>
        .search-box {
            width: 220px;
        }

        .btn-add {
            background: #4f6df5;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
        }

        .edit-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #d9efe1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: green;
        }

        .badge-success {
            background: #e6f7ec;
            color: #28a745;
            border: 1px solid #28a745;
            padding: 6px 14px;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <?php include 'includes/top-header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <?php include 'includes/page-header.php'; ?>

            <section class="content">
                <div class="container-fluid">

                    <?php if (!empty($msg)) { ?>
                        <div class="alert alert-success">
                            <?= $msg; ?>
                        </div>
                    <?php } ?>

                    <div class="card">

                        <!-- HEADER -->
                        <div class="card-header d-flex justify-content-between align-items-center">

                            <div class="d-flex align-items-center" style="gap:15px;">
                                <h5 class="mb-0">Activity</h5>
                                <input type="text" id="searchActivity" class="form-control form-control-sm search-box"
                                    placeholder="Search by name">
                            </div>

                            <div>
                                <a href="#" class="btn btn-add btn-sm" data-toggle="modal"
                                    data-target="#addActivityModal">
                                    <i class="fas fa-plus"></i> Add New
                                </a>
                                <button class="btn btn-light btn-sm">Import</button>
                                <button class="btn btn-light btn-sm">Export</button>
                                <button class="btn btn-light btn-sm">Download Import Format</button>
                            </div>

                        </div>

                        <!-- TABLE -->
                        <div class="card-body">

                            <table id="activityTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Destination</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>By</th>
                                        <th>Last Update</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php
                                    $sql = "SELECT * FROM activities ORDER BY id DESC";
                                    $res = mysqli_query($conn, $sql);

                                    while ($row = mysqli_fetch_assoc($res)) {
                                        ?>

                                        <tr>

                                            <td>
                                                <?= htmlspecialchars($row['name']); ?>
                                            </td>

                                            <td>
                                                <?= htmlspecialchars($row['destination']); ?>
                                            </td>

                                            <td>
                                                <a href="#">Update (
                                                    <?= rand(0, 1); ?>)
                                                </a>
                                            </td>

                                            <td>
                                                <?php if ($row['status'] == 'Active') { ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php } ?>
                                            </td>

                                            <td>
                                                <?= !empty($row['created_by']) ? $row['created_by'] : "Admin"; ?>
                                            </td>

                                            <td>
                                                <?= date('d-m-Y', strtotime($row['created_at'])); ?>
                                            </td>

                                            <td>
                                                <a href="edit_activity.php?id=<?= $row['id']; ?>" class="edit-btn">
                                                    <i class="fas fa-pen"></i>
                                                </a>
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

        <div class="modal fade" id="addActivityModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius:12px;">

                    <form action="action.php" method="POST" enctype="multipart/form-data">

                        <!-- HEADER -->
                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Add Activity</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body">
                            <div class="row">

                                <!-- NAME -->
                                <div class="col-md-12 mb-3">
                                    <label>Activity name</label>
                                    <input type="text" name="name" class="form-control">
                                </div>

                                <!-- DESTINATION -->
                                <div class="col-md-12 mb-3">
                                    <label>Destination</label>
                                    <input type="text" name="destination" class="form-control">
                                </div>

                                <!-- DETAILS -->
                                <div class="col-md-12 mb-3">
                                    <label>Activity Details</label>
                                    <textarea name="details" rows="5" class="form-control"></textarea>
                                </div>

                                <!-- PHOTO -->
                                <div class="col-md-6 mb-3">
                                    <label>Activity Photo*</label>
                                    <input type="file" name="photo" class="form-control">
                                </div>

                                <!-- STATUS -->
                                <div class="col-md-6 mb-3">
                                    <label>Status*</label>
                                    <select name="status" class="form-control">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>

                            </div>
                        </div>

                        <!-- FOOTER -->
                        <div class="modal-footer">
                            <button type="submit" name="add_activity" class="btn btn-success px-4"
                                style="border-radius:20px;">
                                Save
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- SCRIPTS -->
        <?php include 'includes/footer-links.php'; ?>

        <script>
            $(function () {

                var table = $('#activityTable').DataTable();

                $('#searchActivity').keyup(function () {
                    table.search(this.value).draw();
                });

            });
        </script>

    </div>
</body>

</html>