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
    <title>Room Type</title>

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

        .table td {
            vertical-align: middle;
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
                                <h5 class="mb-0">Room Type</h5>
                                <input type="text" id="searchRoom" class="form-control form-control-sm search-box"
                                    placeholder="Search by name">
                            </div>

                            <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addRoomModal">
                                <i class="fas fa-plus"></i> Add New
                            </button>

                        </div>

                        <!-- TABLE -->
                        <div class="card-body">

                            <table id="roomTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>By</th>
                                        <th>Last Update</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php
                                    $sql = "SELECT * FROM room_type WHERE is_deleted=0 ORDER BY id DESC";
                                    $res = mysqli_query($conn, $sql);

                                    while ($row = mysqli_fetch_assoc($res)) {
                                        ?>

                                        <tr>

                                            <td><?= htmlspecialchars($row['name']); ?></td>

                                            <td>
                                                <?php if ($row['status'] == 'Active') { ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php } ?>
                                            </td>

                                            <td><?= !empty($row['created_by']) ? $row['created_by'] : "Admin"; ?></td>

                                            <td>
                                                <?= !empty($row['updated_at']) ? date('d-m-Y', strtotime($row['updated_at'])) : '-'; ?>
                                            </td>

                                            <td>
                                                <a href="edit_room_type.php?id=<?= $row['id']; ?>" class="edit-btn">
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

        <!-- ADD ROOM TYPE MODAL -->
        <div class="modal fade" id="addRoomModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content custom-modal">

                    <form action="action.php" method="POST">

                        <!-- HEADER -->
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title font-weight-bold">Add Room Type</h5>
                            <button type="button" class="close custom-close" data-dismiss="modal">&times;</button>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body pt-2">

                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control custom-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Status*</label>
                                <select name="status" class="form-control custom-input">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="modal-footer border-0 pt-0">
                            <button type="submit" name="add_room_type" class="btn save-btn">
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
                var table = $('#roomTable').DataTable();

                $('#searchRoom').keyup(function () {
                    table.search(this.value).draw();
                });
            });
        </script>

    </div>
</body>

</html>