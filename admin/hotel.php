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
    <title>Hotel CRM</title>

    <?php include 'includes/header-links.php'; ?>

    <!-- DataTables -->
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

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

        .table tbody tr:nth-child(even) {
            background: #f9fbff;
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
                        <div class="alert alert-success"><?= $msg; ?></div>
                    <?php } ?>

                    <div class="card">

                        <!-- HEADER -->
                        <div class="card-header d-flex justify-content-between align-items-center">

                            <div class="d-flex align-items-center" style="gap:15px;">
                                <h5 class="mb-0">Hotel</h5>
                                <input type="text" id="searchSupplier" class="form-control form-control-sm search-box"
                                    placeholder="Search by name">
                            </div>

                            <div>
                                <a href="#" class="btn btn-add btn-sm" data-toggle="modal"
                                    data-target="#addSupplierModal">
                                    <i class="fas fa-plus"></i> Add New
                                </a>
                                <button class="btn btn-light btn-sm">Import</button>
                                <button class="btn btn-light btn-sm">Export</button>
                            </div>

                        </div>

                        <div class="card-body">
                            <table id="supplierTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
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
                                    $sql = "SELECT * FROM hotels ORDER BY id DESC";
                                    $res = mysqli_query($conn, $sql);

                                    while ($row = mysqli_fetch_assoc($res)) {
                                        ?>

                                        <tr>

                                            <!-- NAME -->
                                            <td>
                                                <strong><?= htmlspecialchars($row['hotel_name']); ?></strong><br>
                                                <?php if (!empty($row['photo'])) { ?>
                                                    <img src="uploads/hotels/<?= $row['photo']; ?>" width="50"
                                                        style="border-radius:5px;">
                                                <?php } ?>
                                            </td>

                                            <!-- CATEGORY -->
                                            <td><?= htmlspecialchars($row['category']); ?></td>

                                            <!-- DESTINATION -->
                                            <td><?= htmlspecialchars($row['destination']); ?></td>

                                            <!-- PRICE (dummy or future field) -->
                                            <td>
                                                <a href="#">Update (<?= rand(0, 5); ?>)</a>
                                            </td>

                                            <!-- STATUS -->
                                            <td>
                                                <?php if ($row['status'] == 'Active') { ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php } else { ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php } ?>
                                            </td>

                                            <!-- BY -->
                                            <td><?= !empty($row['contact_person']) ? htmlspecialchars($row['contact_person']) : "Admin"; ?>
                                            </td>

                                            <!-- DATE -->
                                            <td><?= date('d-m-Y', strtotime($row['created_at'])); ?></td>

                                            <!-- ACTION -->
                                            <td>
                                                <a href="edit_hotel.php?id=<?= $row['id']; ?>" class="edit-btn">
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
        <!-- ADD HOTEL MODAL -->
        <div class="modal fade" id="addSupplierModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius:10px;">

                    <form action="action.php" method="POST" enctype="multipart/form-data">

                        <!-- HEADER -->
                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Add Hotel</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <!-- BODY -->
                        <div class="modal-body">
                            <div class="row">

                                <!-- HOTEL NAME -->
                                <div class="col-md-12 mb-3">
                                    <label>Hotel name</label>
                                    <input type="text" name="hotel_name" class="form-control">
                                </div>

                                <!-- CATEGORY -->
                                <div class="col-md-6 mb-3">
                                    <label>Category</label>
                                    <select name="category" class="form-control">
                                        <option>Select</option>
                                        <option>3 Star</option>
                                        <option>4 Star</option>
                                        <option>5 Star</option>
                                    </select>
                                </div>

                                <!-- DESTINATION -->
                                <div class="col-md-6 mb-3">
                                    <label>Destination</label>
                                    <input type="text" name="destination" class="form-control">
                                </div>

                                <!-- DETAILS -->
                                <div class="col-md-12 mb-3">
                                    <label>Hotel Details</label>
                                    <textarea name="details" rows="4" class="form-control"></textarea>
                                </div>

                                <!-- PHOTO -->
                                <div class="col-md-6 mb-3">
                                    <label>Hotel Photo*</label>
                                    <input type="file" name="photo" class="form-control">
                                </div>

                                <!-- CONTACT PERSON -->
                                <div class="col-md-6 mb-3">
                                    <label>Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control">
                                </div>

                                <!-- EMAIL -->
                                <div class="col-md-6 mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>

                                <!-- PHONE -->
                                <div class="col-md-6 mb-3">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>

                                <!-- ADDRESS -->
                                <div class="col-md-6 mb-3">
                                    <label>Hotel Address</label>
                                    <input type="text" name="address" class="form-control">
                                </div>

                                <!-- STATUS -->
                                <div class="col-md-6 mb-3">
                                    <label>Status*</label>
                                    <select name="status" class="form-control">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>

                                <!-- HOTEL LINK -->
                                <div class="col-md-12 mb-3">
                                    <label>Hotel Link</label>
                                    <input type="text" name="hotel_link" class="form-control">
                                </div>

                            </div>
                        </div>

                        <!-- FOOTER -->
                        <div class="modal-footer">
                            <button type="submit" name="add_hotel" class="btn btn-primary px-4">
                                Save
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>

        <script>
            $(document).ready(function () {

                // COUNTRY API
                $.get("https://countriesnow.space/api/v0.1/countries", function (res) {

                    let options = '<option></option>';

                    res.data.forEach(function (c) {
                        options += `<option value="${c.country}">${c.country}</option>`;
                    });

                    $("#country").html(options);

                    $('#country').select2({
                        placeholder: "Search Country",
                        dropdownParent: $('#addSupplierModal')
                    });

                });

                // DATATABLE
                var table = $('#supplierTable').DataTable();

                $('#searchSupplier').keyup(function () {
                    table.search(this.value).draw();
                });

            });
        </script>
        <?php include 'includes/footer-links.php'; ?>
</body>

</html>