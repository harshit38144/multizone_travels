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
    <title>Day Itinerary</title>

    <?php include 'includes/header-links.php'; ?>
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">

    <style>
        .search-box {
            width: 220px;
        }

        .btn-add {
            background: #8cc152;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
        }

        .edit-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #d9efe1;
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

        .thumb {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            object-fit: cover;
            margin-right: 10px;
        }

        .title-cell {
            display: flex;
            align-items: center;
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
                                <h5 class="mb-0">Day Itinerary</h5>
                                <input type="text" id="searchDay" class="form-control form-control-sm search-box"
                                    placeholder="Search by name">
                            </div>

                            <button class="btn btn-add btn-sm" data-toggle="modal" data-target="#addDayModal">
                                <i class="fas fa-plus"></i> Add New
                            </button>

                        </div>

                        <!-- TABLE -->
                        <div class="card-body">

                            <table id="dayTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Detail</th>
                                        <th>Status</th>
                                        <th>By</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php
                                    $sql = "SELECT * FROM day_itinerary WHERE is_deleted=0 ORDER BY id DESC";
                                    $res = mysqli_query($conn, $sql);

                                    while ($row = mysqli_fetch_assoc($res)) {
                                        ?>

                                        <tr>

                                            <td>
                                                <div class="title-cell">
                                                    <img src="uploads/day/<?= !empty($row['photo']) ? $row['photo'] : 'default.png'; ?>"
                                                        class="thumb upload-trigger" data-id="<?= $row['id']; ?>"
                                                        style="cursor:pointer;">
                                                    <?= htmlspecialchars($row['title']); ?>
                                                </div>
                                            </td>

                                            <td>
                                                <?= substr($row['details'], 0, 80); ?>...
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
                                                <a href="edit_day.php?id=<?= $row['id']; ?>" class="edit-btn">
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

        <!-- MODAL -->
        <div class="modal fade" id="addDayModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius:12px;">

                    <form action="action.php" method="POST" enctype="multipart/form-data">

                        <!-- HEADER -->
                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Add Day Itinerary</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body">
                            <div class="row">

                                <!-- DESTINATION -->
                                <div class="col-md-12 mb-3">
                                    <label>Destination</label>
                                    <input type="text" name="destination" class="form-control">
                                </div>

                                <!-- TITLE -->
                                <div class="col-md-12 mb-3">
                                    <label>Title</label>
                                    <input type="text" name="title" class="form-control">
                                </div>

                                <!-- DETAILS -->
                                <div class="col-md-12 mb-3">
                                    <label>Details</label>
                                    <textarea name="details" rows="5" class="form-control"></textarea>
                                </div>

                                <!-- STATUS -->
                                <div class="col-md-4 mb-3">
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
                            <button type="submit" name="add_day" class="btn"
                                style="background:#8cc152; color:#fff; border-radius:20px; padding:6px 20px;">
                                Save
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
        <input type="file" id="hiddenFileInput" style="display:none;">
        <?php include 'includes/footer-links.php'; ?>
        <script>
            $(document).on('click', '.upload-trigger', function () {

                let id = $(this).data('id');

                $('#hiddenFileInput').data('id', id).click();
            });

            $('#hiddenFileInput').on('change', function () {

                let file = this.files[0];
                let id = $(this).data('id');

                let formData = new FormData();
                formData.append('photo', file);
                formData.append('id', id);
                formData.append('update_day_image', true);

                $.ajax({
                    url: 'action.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function (res) {
                        location.reload(); // refresh after upload
                    }
                });

            });
        </script>


        <script>
            $(function () {
                var table = $('#dayTable').DataTable();

                $('#searchDay').keyup(function () {
                    table.search(this.value).draw();
                });
            });
        </script>

    </div>
</body>

</html>