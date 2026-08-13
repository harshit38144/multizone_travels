<?php
session_start();
if ($_SESSION['role'] != '1') {
    header('location:index.php');
}
include 'connection.php';

$conn->query("CREATE TABLE IF NOT EXISTS `image_master` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Active',
  `created_by` VARCHAR(100) DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
    <title>Image Master</title>

    <?php include 'includes/header-links.php'; ?>
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

    <style>
        .search-box {
            width: 220px;
        }

        .btn-add {
            background: #4e73df;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
        }

        .edit-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #d4edda;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #28a745;
        }

        .badge-success {
            background: #e6f7ec;
            color: #28a745;
            border: 1px solid #28a745;
            padding: 5px 12px;
        }

        .badge-danger {
            background: #fdecea;
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 5px 12px;
        }

        .table img {
            width: 70px;
            border-radius: 6px;
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
                        <div class="alert alert-success"><?= $msg; ?></div>
                    <?php } ?>

                    <div class="card">

                        <!-- HEADER -->
                        <div class="card-header d-flex justify-content-between align-items-center">

                            <div class="d-flex align-items-center" style="gap:15px;">
                                <h5 class="mb-0">Image Master</h5>
                                <input type="text" id="searchImage" class="form-control form-control-sm search-box"
                                    placeholder="Search image">
                            </div>

                            <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addImageModal">
                                <i class="fas fa-plus"></i> Add New
                            </button>
                            <button class="btn btn-add btn-sm" onclick="window.location.href='e-ticket-master.php'">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>

                        </div>

                        <!-- TABLE -->
                        <div class="card-body">

                            <table id="imageTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Preview</th>
                                        <th>Status</th>
                                        <th>Last Update</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php

                                    $sql = "SELECT * FROM image_master WHERE is_deleted=0 ORDER BY id DESC";
                                    $res = mysqli_query($conn, $sql);

                                    while ($row = mysqli_fetch_assoc($res)) {

                                        ?>

                                        <tr>

                                            <td><?= htmlspecialchars($row['name']); ?></td>

                                            <td>
                                                <img src="uploads/images/<?= $row['image']; ?>">
                                            </td>

                                            <td>
                                                <?php if ($row['status'] == "Active") { ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php } ?>
                                            </td>

                                            <td>
                                                <?= !empty($row['updated_at']) ? date('d-m-Y', strtotime($row['updated_at'])) : '-' ?>
                                            </td>

                                            <td>
                                                <div style="display:flex; gap:8px;">

                                                    <a href="edit_image_master.php?id=<?= $row['id']; ?>" class="edit-btn">
                                                        <i class="fas fa-pen"></i>
                                                    </a>

                                                    <a href="action.php?delete_image_master=<?= $row['id']; ?>"
                                                        class="edit-btn" style="background:#fdecea;color:#dc3545"
                                                        onclick="return confirm('Delete this image?')">

                                                        <i class="fas fa-trash"></i>

                                                    </a>

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

        <!-- ADD IMAGE MODAL -->

        <div class="modal fade" id="addImageModal">
            <div class="modal-dialog">
                <div class="modal-content">

                    <form action="action.php" method="POST" enctype="multipart/form-data">

                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title font-weight-bold">Add Image</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body pt-2">

                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Image</label>
                                <input type="file" name="image" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="form-control">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>

                        </div>

                        <div class="modal-footer border-0 pt-0">
                            <button type="submit" name="add_image_master" class="btn btn-primary">
                                Save
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>

        <script>

            $(function () {

                var table = $('#imageTable').DataTable();

                $('#searchImage').keyup(function () {
                    table.search(this.value).draw();
                });

            });

        </script>

    </div>
</body>

</html>