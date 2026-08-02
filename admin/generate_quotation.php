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
    <title>Quotation Generator</title>
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

                    <div class="card">

                        <div class="card-header">
                            <h3 class="card-title">Quotation Generator</h3>
                        </div>

                        <div class="card-body">

                            <form method="post" action="save_quotation.php">

                                <!-- ================= GUEST INFORMATION ================= -->

                                <h5 class="mb-3">Guest Information</h5>

                                <div class="row">

                                    <div class="col-md-3">
                                        <label>Guest Name</label>
                                        <input type="text" name="guest_name" class="form-control">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Reference Name</label>
                                        <input type="text" name="reference_name" class="form-control">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Mobile No</label>
                                        <input type="text" name="mobile" class="form-control">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>

                                </div>

                                <hr>

                                <!-- ================= TOUR INFORMATION ================= -->

                                <h5 class="mb-3">Tour Information</h5>

                                <div class="row">

                                    <div class="col-md-3">
                                        <label>Destination</label>
                                        <input type="text" name="destination" class="form-control">
                                    </div>

                                    <div class="col-md-3">
                                        <label>Tentative Date</label>
                                        <input type="date" name="travel_date" class="form-control">
                                    </div>

                                    <div class="col-md-2">
                                        <label>No of Nights</label>
                                        <input type="number" name="nights" class="form-control" value="0">
                                    </div>

                                    <div class="col-md-2">
                                        <label>No of Adults</label>
                                        <input type="number" name="adults" class="form-control" value="1">
                                    </div>

                                    <div class="col-md-2">
                                        <label>No Of Children</label>
                                        <input type="number" name="children" class="form-control" value="0">
                                    </div>

                                </div>

                                <hr>

                                <!-- ================= FLIGHT / TRAIN ================= -->

                                <h5 class="mb-3">Flight/Train Details</h5>

                                <div class="mb-3">

                                    <button type="button" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plane"></i> Search Flight
                                    </button>

                                    <span class="mx-2">OR</span>

                                    <button type="button" class="btn btn-primary btn-sm">
                                        <i class="fas fa-train"></i> Search Train
                                    </button>

                                    <span class="mx-2">OR</span>

                                    <button type="button" class="btn btn-primary btn-sm" id="addTransport">
                                        <i class="fas fa-plus"></i> Add Flight /Train
                                    </button>

                                </div>

                                <div id="transportContainer"></div>

                                <hr>

                                <!-- ================= HOTEL ================= -->

                                <h5 class="mb-3">Hotel Details</h5>

                                <button type="button" class="btn btn-primary btn-sm mb-3" id="addHotel">
                                    <i class="fas fa-plus"></i> Add Hotel
                                </button>

                                <div id="hotelContainer"></div>

                                <hr>

                                <!-- ================= ITINERARY ================= -->

                                <div class="card card-outline card-secondary">

                                    <div class="card-header">
                                        <h5 class="card-title">Itinerary</h5>
                                    </div>

                                    <div class="card-body">

                                        <textarea name="itinerary" class="form-control" rows="5"></textarea>

                                    </div>
                                </div>

                                <br>

                                <!-- ================= INCLUSION ================= -->

                                <div class="card card-outline card-secondary">

                                    <div class="card-header">
                                        <h5 class="card-title">Inclusion</h5>
                                    </div>

                                    <div class="card-body">

                                        <textarea name="inclusion" class="form-control" rows="5"></textarea>

                                    </div>
                                </div>

                                <br>

                                <!-- ================= EXCLUSION ================= -->

                                <div class="card card-outline card-secondary">

                                    <div class="card-header">
                                        <h5 class="card-title">Exclusion</h5>
                                    </div>

                                    <div class="card-body">

                                        <textarea name="exclusion" class="form-control" rows="5"></textarea>

                                    </div>
                                </div>

                                <br>

                                <!-- ================= PAYMENT POLICY ================= -->

                                <div class="card card-outline card-secondary">

                                    <div class="card-header">
                                        <h5 class="card-title">Payment Policy</h5>
                                    </div>

                                    <div class="card-body">

                                        <textarea name="payment_policy" class="form-control" rows="5"></textarea>

                                    </div>
                                </div>

                                <br>

                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Quotation
                                </button>

                            </form>

                        </div>
                    </div>

                </div>
            </section>

        </div>

        <?php include 'includes/copyright.php'; ?>

    </div>

    <?php include 'includes/footer-links.php'; ?>

    <script>

        let hotelIndex = 0;

        $("#addHotel").click(function () {

            hotelIndex++;

            $("#hotelContainer").append(`

<div class="card mb-3">
<div class="card-body">

<div class="row">

<div class="col-md-4">
<label>Hotel Name</label>
<input type="text" name="hotel_name[]" class="form-control">
</div>

<div class="col-md-3">
<label>Room Type</label>
<input type="text" name="room_type[]" class="form-control">
</div>

<div class="col-md-3">
<label>Nights</label>
<input type="number" name="hotel_nights[]" class="form-control">
</div>

<div class="col-md-2">
<label>Price</label>
<input type="number" name="hotel_price[]" class="form-control">
</div>

</div>

</div>
</div>

`);

        });

    </script>

</body>

</html>