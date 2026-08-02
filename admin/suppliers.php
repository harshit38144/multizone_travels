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
    <title>Suppliers</title>

    <?php include 'includes/header-links.php'; ?>

    <!-- DataTables -->
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        .supplier-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .supplier-header h5 {
            font-weight: 600;
        }

        .search-box {
            width: 200px;
        }

        .btn-add {
            background: #4f6df5;
            color: #fff;
            border-radius: 20px;
            padding: 6px 16px;
            font-weight: 500;
        }

        .btn-add:hover {
            color: #fff;
            opacity: 0.9;
        }

        .edit-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #d9efe1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f9d55;
            border: none;
        }

        .edit-btn:hover {
            background: #c6e8d6;
        }

        table.dataTable tbody tr {
            background: #f9fbff;
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
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $msg; ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php } ?>

                    <div class="card">

                        <!-- HEADER -->
                        <div class="card-header">

                            <div class="supplier-header">

                                <h5 class="mb-0">Suppliers</h5>

                                <input type="text" id="searchSupplier" class="form-control form-control-sm search-box"
                                    placeholder="Search by name">

                                <a href="#" class="btn btn-add btn-sm" data-toggle="modal"
                                    data-target="#addSupplierModal">
                                    <i class="fas fa-plus"></i> Add New
                                </a>

                            </div>

                        </div>


                        <div class="card-body">

                            <table id="supplierTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>Category</th>
                                        <th>Contact</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Location</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <th width="80">Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php

                                    $sql = "SELECT * FROM suppliers WHERE is_deleted = 0 ORDER BY id DESC";
                                    $res = mysqli_query($conn, $sql);

                                    while ($row = mysqli_fetch_assoc($res)) {

                                        ?>

                                        <tr>

                                            <!-- COMPANY -->
                                            <td>
                                                <strong><?= htmlspecialchars($row['company']); ?></strong>
                                            </td>

                                            <!-- CATEGORY -->
                                            <td>
                                                <?= !empty($row['category']) ? htmlspecialchars($row['category']) : "-"; ?>
                                            </td>

                                            <!-- CONTACT -->
                                            <td>
                                                <?= htmlspecialchars($row['contact_person']); ?><br>
                                                <small class="text-muted">
                                                    <?= !empty($row['designation']) ? $row['designation'] : ""; ?>
                                                </small>
                                            </td>

                                            <!-- EMAIL -->
                                            <td>
                                                <?= !empty($row['email']) ? htmlspecialchars($row['email']) : "<span class='text-muted'>N/A</span>"; ?>
                                            </td>

                                            <!-- MOBILE -->
                                            <td>
                                                <?= !empty($row['mobile']) ? htmlspecialchars($row['code'] . " " . $row['mobile']) : "<span class='text-muted'>N/A</span>"; ?>
                                            </td>

                                            <!-- LOCATION -->
                                            <td>
                                                <?= !empty($row['city']) ? htmlspecialchars($row['city']) : ""; ?>
                                                <?= !empty($row['country']) ? ", " . htmlspecialchars($row['country']) : ""; ?>
                                            </td>

                                            <!-- CREATED DATE -->
                                            <td>
                                                <?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : "-"; ?>
                                            </td>

                                            <!-- STATUS -->
                                            <td>
                                                <span class="badge badge-success">Active</span>
                                            </td>

                                            <!-- ACTION -->
                                            <td>
                                                <a href="edit_supplier.php?id=<?= $row['id']; ?>" class="edit-btn"
                                                    title="Edit">
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

        <!-- ADD SUPPLIER MODAL -->
        <div class="modal fade" id="addSupplierModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <form action="action.php" method="POST">

                        <div class="modal-header">
                            <h5 class="modal-title font-weight-bold">Add Supplier</h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <div class="row">

                                <!-- CATEGORY -->
                                <div class="col-md-6 mb-3">
                                    <label>Category</label>
                                    <select name="category" class="form-control">
                                        <option value="">Select Category</option>
                                        <option value="Visa">Visa</option>
                                        <option value="Flight">Flight</option>
                                        <option value="Hotel">Hotel</option>
                                        <option value="Forex">Forex</option>
                                        <option value="Transport">Transport</option>
                                        <option value="Land">Land</option>

                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label>Country</label>
                                    <select name="country" class="form-control" id="country"
                                        style="width:100%;"></select>
                                </div>

                                <!-- CITY -->
                                <div class="col-md-6 mb-3">
                                    <label>City</label>
                                    <select name="city" id="city" class="form-control">
                                        <option value="">Select City</option>
                                        <option value="Ranchi">Ranchi</option>
                                        <option value="Delhi">Delhi</option>
                                        <option value="Mumbai">Mumbai</option>
                                    </select>
                                </div>

                                <!-- COMPANY -->
                                <div class="col-md-6 mb-3">
                                    <label>Company Name</label>
                                    <input type="text" name="company" class="form-control">
                                </div>

                                <!-- DESIGNATION -->
                                <div class="col-md-6 mb-3">
                                    <label>Designation</label>
                                    <select name="designation" class="form-control">
                                        <option value="">Select Designation</option>
                                        <option value="Sales Executive">Sales Executive</option>
                                        <option value="Owner">Owner</option>
                                        <option value="Holiday Consultant">Holiday Consultant</option>
                                    </select>
                                </div>

                                <!-- TITLE -->
                                <div class="col-md-2 mb-3">
                                    <label>Title</label>
                                    <select name="title" class="form-control">
                                        <option value="Mr.">Mr.</option>
                                        <option value="Mrs.">Mrs.</option>
                                        <option value="Miss">Miss</option>
                                    </select>
                                </div>

                                <!-- NAME -->
                                <div class="col-md-4 mb-3">
                                    <label>Name</label>
                                    <input type="text" name="name" class="form-control">
                                </div>

                                <!-- MOBILE -->
                                <div class="col-md-6 mb-3">
                                    <label>Mobile</label>
                                    <div class="d-flex">
                                        <input type="text" name="code" value="+91" class="form-control"
                                            style="max-width:80px;">
                                        <input type="text" name="mobile" class="form-control ml-2">
                                    </div>
                                </div>

                                <!-- EMAIL -->
                                <div class="col-md-6 mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>

                                <!-- ADDRESS -->
                                <div class="col-md-6 mb-3">
                                    <label>Address</label>
                                    <input type="text" name="address" class="form-control">
                                </div>

                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" name="add_supplier" class="btn btn-primary">
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

                // ✅ LOAD COUNTRIES
                $.get("https://countriesnow.space/api/v0.1/countries", function (res) {

                    let countries = res.data.map(c => c.country).sort();

                    let options = '<option></option>'; // 🔥 FIXED HERE

                    countries.forEach(function (country) {
                        let selected = (country === "India") ? "selected" : "";
                        options += `<option value="${country}" ${selected}>${country}</option>`;
                    });

                    $("#country").html(options);

                    // ✅ SELECT2 PERFECT CONFIG
                    $('#country').select2({
                        placeholder: "🔍 Type or select country",
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#addSupplierModal'),
                        minimumInputLength: 0 // shows all on click
                    });

                });

                // ✅ CITY LOAD
                $("#country").on("change", function () {

                    let country = $(this).val();

                    $("#city").html('<option>Loading...</option>');

                    $.ajax({
                        url: "https://countriesnow.space/api/v0.1/countries/cities",
                        method: "POST",
                        contentType: "application/json",
                        data: JSON.stringify({ country: country }),

                        success: function (res) {

                            let options = '<option value="">Select City</option>';

                            res.data.forEach(function (city) {
                                options += `<option value="${city}">${city}</option>`;
                            });

                            $("#city").html(options);
                        }
                    });

                });

            });
        </script>

        <?php include 'includes/footer-links.php'; ?>

        <!-- DataTables -->
        <script>
            setTimeout(function () {
                $(".alert").fadeOut("slow");
            }, 3000);
        </script>

        <script>

            $(function () {

                var table = $('#supplierTable').DataTable({
                    responsive: true,
                    lengthChange: false,
                    info: false,
                    paging: true
                });


                $('#searchSupplier').on('keyup', function () {
                    table.search(this.value).draw();
                });

            });

        </script>

        <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script> -->

</body>

</html>