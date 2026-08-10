<?php
include "connection.php";

$editData = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $res = mysqli_query($conn, "SELECT * FROM saved_tickets WHERE id=$edit_id");
    if ($res) {
        $editData = mysqli_fetch_assoc($res);
    }
}

$banner = "img/a8.png";

$sql = "SELECT image FROM image_master 
WHERE status='Active' 
AND is_deleted=0
LIMIT 1";

$res = mysqli_query($conn, $sql);

if (mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $banner = "uploads/images/" . $row['image'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi Zone Travels - Flight E-Ticket</title>
    <?php include 'includes/header-links.php'; ?>
    <style>
        /* A4 Sheet Style - exactly like image */
        .ticket {
            max-width: 210mm;
            width: 100%;
            background: white;
            padding: 15px 20px 20px 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin: 0 auto;
        }

        /* Header section */
        .header {
            /* display: flex; */
            /* justify-content: space-between; */
            /* align-items: center; */
            border-bottom: 2px solid #e31b23;
            padding-bottom: 12px;
            margin-bottom: 18px;
            /* text-align: center; */
        }

        /* .header .col-md-4 {
            display: flex;
            justify-content: center;
            align-items: center;
        } */

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a2e;
        }

        .logo span {
            color: #e31b23;
        }

        .booked-on {
            font-size: 13px;
            color: #333;
        }

        /* Support Row - exactly as image */
        .support-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            align-items: stretch;
        }

        .support-box {
            text-align: center;
            flex: 1;
            border-right: 1px solid #ccc;
            padding: 5px 0;
        }

        .support-box:last-child {
            border-right: none;
        }

        .support-box .title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #e31b23;
        }

        .phone {
            font-size: 18px;
            font-weight: bold;
            color: #000000ff;
            white-space: nowrap;
            display: inline-block;
        }

        #pnrBox {
            border: 2px solid #999;
            padding: 4px 35px;
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            color: #e31b23;
            background: white;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Flight Section Title */
        .flight-title {
            background: #e31b23;
            color: white;
            padding: 3px 12px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            /* margin-top: 5px; */
            align-items: center;
        }

        .verify-text {
            font-size: 11px;
            font-weight: normal;
        }

        /* Passenger Table */
        .passenger-head {
            background: #666;
            color: white;
            padding: 3px 12px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 15px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th,
        .data-table td {
            /* border: 1px solid #ccc; */
            padding: 3px 10px;
            text-align: left;
        }

        .data-table th {
            background: #d2d2d2;
            font-weight: bold;
        }

        #passengerTable .col-sr-no {
            width: 42px;
            max-width: 42px;
            white-space: nowrap;
            text-align: center;
            padding-left: 4px;
            padding-right: 4px;
        }

        /* New Flight Details Card Styles */
        .flight-container {
            margin-top: 10px;
            border: 1px solid #e31b23;
        }

        .flight-card {
            font-family: 'Inter', sans-serif;
            border-bottom: 1px solid #eee;
        }

        .flight-card:last-child {
            border-bottom: none;
        }

        .flight-card-header {
            background: #d2d2d2;
            display: grid;
            grid-template-columns: 150px 1fr 1fr auto;
            gap: 8px 20px;
            align-items: center;
            padding: 4px 15px;
            font-size: 13px;
        }

        .flight-card-header .flight-num {
            color: #e31b23;
            font-weight: bold;
            padding-left: 10px;
        }

        .flight-card-header .header-label {
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            padding-left: 6px;
        }

        .flight-card-body {
            padding: 4px 15px;
            display: grid;
            grid-template-columns: 150px 1fr 1fr auto;
            gap: 8px 20px;
            align-items: flex-start;
        }

        .flight-info-group {
            min-width: 0;
        }

        .flight-info-group.airline-info {
            max-width: 125px;
            padding-left: 10px;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .flight-info-group.location-info {
            padding-left: 8px;
        }

        .airline-info .airline-name {
            font-weight: bold;
            font-size: 14px;
            line-height: 1.25;
            margin-bottom: 2px;
        }

        .airline-info .terminal {
            font-size: 11px;
            color: #666;
        }

        .airline-info .flight-code {
            font-weight: bold;
            font-size: 13px;
            margin-top: 2px;
        }

        .location-info .city {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .location-info .datetime {
            font-weight: bold;
            font-size: 13px;
        }

        .flight-meta-right {
            text-align: left;
            border-left: 1px solid #ccc;
            padding-left: 12px;
            min-width: 88px;
        }

        .flight-meta-right .stops {
            font-size: 13px;
            color: #333;
        }

        .flight-meta-right .duration {
            font-size: 12px;
            color: #666;
        }

        .baggage-info {
            padding: 0 15px 3px 25px;
            font-size: 11px;
            color: #555;
        }

        /* Two column layout for Payment & Contact */
        .two-columns {
            display: flex;
            gap: 45px;
            margin: 20px 0;
            justify-content: space-between;
        }

        .payment-box,
        .contact-box {
            flex: 1;
            border: 1px solid #ccc;
            padding: 3px 15px;
            max-width: 100%;
        }

        .data-table th {
            font-weight: 500 !important;
        }

        /* .data-table td {
            font-weight: bold;
        } */

        .box-header {
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            padding-bottom: 6px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        .amount-line {
            /* padding: 5px 0; */
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }

        .total-line {
            margin-top: 8px;
            padding-top: 6px;
            font-weight: bold;
            border-top: 1px dashed #aaa;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
        }

        .contact-detail {
            font-size: 13px;
            margin: 8px 0;
            display: flex;
    justify-content: space-between;
        }

        .contact-detail strong {
            font-weight: 600;
        }

        /* Important Information */
        .important-title {
            font-weight: bold;
            margin: 18px 0 8px 0;
            font-size: 14px;
        }

        .important-list {
            font-size: 12.5px;
            padding-left: 0px;
            color: #222;
            line-height: 1.45;
        }

        .important-list li {
            margin: 5px 0;
        }

        /* Hotel Banner - exactly like image */
        .hotel-banner {
            /* background: #3d3d3d; */
            /* background-image: url(img/a8.png); */
            background-size: cover;
            background-position: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            margin: 20px 0;
            color: white;
            position: relative;
            min-height: 150px;
        }

        .hotel-banner::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
        }

        .hotel-left {
            position: relative;
            z-index: 2;
        }

        .hotel-images {
            position: relative;
            z-index: 2;
        }

        .hotel-left h2 {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.2;
        }

        .hotel-left h2 span {
            color: #f5a623;
        }

        .hotel-call {
            background: #f5a623;
            display: inline-block;
            margin-top: 12px;
            padding: 8px 15px;
            font-weight: 600;
        }

        .hotel-call span {
            background: black;
            color: white;
            padding: 4px 8px;
            margin-right: 10px;
            border-radius: 4px;
            display: inline-block;
            vertical-align: middle;
        }

        #bannerMobile {
            display: inline-block;
            vertical-align: middle;
        }

        /* .hotel-images {
            display: flex;
            gap: 10px;
        } */

        .hotel-images img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border: 3px solid white;
        }

        /* Footer */
        .footer {
            background: #e31b23;
            color: white;
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
        }

        .footer-left {
            font-weight: 600;
            font-size: 15px;
        }

        .footer-right {
            font-size: 13px;
        }

        /* Disclaimer */
        .disclaimer {
            font-size: 9px;
            text-align: center;
            margin-top: 12px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 8px;
        }

        /* Control Panel (simple, no extra colors) */
        .control-panel {
            max-width: 210mm;
            margin: 25px auto 0;
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ccc;
            font-family: 'Open Sans', sans-serif;
        }

        .control-panel h4 {
            margin-bottom: 12px;
            color: #333;
            font-size: 16px;
        }

        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .form-group {
            flex: 1;
            min-width: 140px;
        }

        .form-group label {
            /* font-size: 12px; */
            display: block;
            margin-bottom: 3px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #aaa;
            font-size: 12px;
        }

        button {
            background: #e31b23;
            color: white;
            border: none;
            padding: 6px 15px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 20px;
        }

        .flight-result {
            margin-top: 15px;
            max-height: 200px;
            overflow-y: auto;
        }

        .flight-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .flight-table th,
        .flight-table td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }

        .select-flight {
            background: #e31b23;
            color: white;
            border: none;
            padding: 3px 8px;
            cursor: pointer;
            font-size: 10px;
        }

        .banner-card {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: 0.3s;
        }

        .banner-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .banner-card:hover {
            transform: scale(1.05);
            border: 3px solid #e31b23;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .banner-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            text-align: center;
            padding: 5px;
            font-size: 12px;
        }

        .selected-banner {
            border: 3px solid #28a745 !important;
        }

        #ticket {
            width: 100%;
            max-width: 210mm;
            margin: auto;
        }

        .ticket {
            width: 100%;
            max-width: 210mm;
        }

        .passenger-head,
        .flight-title,
        .support-row,
        .two-columns {
            page-break-inside: avoid;
        }

        .ticket {
            padding-bottom: 40px;
        }

        .ticket {
            padding: 15px;
            box-sizing: border-box;
        }

        @media print {

            /* Hide elements that shouldn't print */
            nav,
            .main-header,
            .main-sidebar,
            .main-footer,
            footer {
                display: none !important;
            }

            /* Hide the Control Panel column */
            .row>.col-md-6:first-child {
                display: none !important;
            }

            /* Make the Ticket column full width */
            .row>.col-md-6:last-child {
                flex: 0 0 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Remove layout margins/paddings */
            .wrapper,
            .content-wrapper,
            .content,
            .container-fluid,
            body,
            html {
                margin: 0 !important;
                padding: 0 !important;
                background-color: white !important;
            }

            .ticket {
                margin: 0 auto !important;
                /* Slightly more space from the top edge for printing */
                padding: 8px 8px 18mm 8px !important;
                box-sizing: border-box !important;
                box-shadow: none !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                display: block !important;
            }

            /* Tighten up spacing to fit on exactly one page */
            .header {
                padding-bottom: 5px !important;
                margin-bottom: 10px !important;
            }

            .support-row {
                margin-bottom: 10px !important;
            }

            .two-columns {
                margin: 10px 0 !important;
                gap: 25px !important;
            }

            .hotel-banner {
                margin: 10px 0 !important;
                padding: 10px !important;
                min-height: 110px !important;
            }

            .passenger-head {
                margin-top: 10px !important;
            }

            .important-title {
                margin: 10px 0 5px 0 !important;
            }

            .footer {
                position: fixed !important;
                left: 0mm !important;
                right: 0mm !important;
                bottom: 0 !important;
                padding: 5px 8px !important;
                box-sizing: border-box !important;
                width: 100% !important;
                margin-top: 0 !important;
                z-index: 9999 !important;
            }

            .important-list {
                font-size: 11px !important;
                margin-bottom: 5px !important;
            }

            .important-list li {
                margin: 2px 0 !important;
            }

            body {
                zoom: 1 !important;
            }

            .flight-title,
            .hotel-banner,
            .passenger-head,
            .header,
            .flight-card-header,
            .data-table th,
            .footer {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }


            .fa-trash {
                display: none;
            }

        }

        @page {
            size: A4 portrait;
            margin: 6mm 8mm 12mm 8mm;
        }

        .hotel-banner {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .ticket,
        .support-row,
        .two-columns,
        .hotel-banner,
        .passenger-head,
        .flight-title {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* body {
            overflow-x: hidden;
        } */

        body {
            overflow-x: visible;
            font-family: 'Open Sans', sans-serif;
        }

        #pnrInput {
            text-transform: uppercase;
        }

        .flight-filter-btn,
        .flight-sort-btn {
            margin: 0px;
        }
    </style>
    <link rel="stylesheet" href="plugins/jquery-ui/jquery-ui.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">



        <div class="">
            <?php include 'includes/page-header.php'; ?>

            <style>
                .datess input {
                    font-size: 14px;
                }

                .onlywidth input {
                    width: 80%;
                }

                input[type="date"]::-webkit-calendar-picker-indicator {
                    opacity: 0;
                    display: none;
                }

                /* .custom-date {
                    background: url('img/calendar.png') no-repeat right 10px center;
                    background-size: 18px;
                    padding-right: 35px;
                } */

                .date-wrapper {
                    position: relative;
                }

                .date-wrapper input {
                    padding-right: 40px;
                    /* space for icon */
                }

                .calendar-icon {
                    position: absolute;
                    right: 48px;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 18px;
                    cursor: pointer;
                }

                .icononward {
                    right: 12px !important;
                }

                .card-body {
                    background: #e5e5e5 !important;
                    border: 2px solid #c5c5c5 !important;
                }
            </style>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- CONTROL PANEL -->
                            <div class="control-panel-wrapper"
                                style="background: #f4f6f9; padding: 20px; font-family: 'Segoe UI', Arial, sans-serif; border: 1px solid #ddd; margin-bottom: 30px;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="row">
                                            <div class="col-md-12 form-group onlywidth datess">
                                                <label>Booking Date</label>
                                                <div class="date-wrapper">
                                                    <input type="date" class="form-control" id="bookingDate"
                                                        value="<?php echo date('Y-m-d'); ?>">
                                                    <img src="img/calendar.png" class="calendar-icon"
                                                        onclick="openPicker('bookingDate')">
                                                </div>
                                            </div>
                                            <div class="col-md-12 form-group onlywidth">
                                                <label>PNR</label>
                                                <input type="text" class="form-control" id="pnrInput" placeholder="">
                                            </div>
                                            <div class="col-md-12">
                                                <div class="form-group onlywidth mb-3">
                                                    <label>No of Pax</label>
                                                    <input type="number" class="form-control" id="paxCount" value="1"
                                                        min="1">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <!-- Search Flight Box -->
                                        <div class="card mb-3"
                                            style="box-shadow: none; border-radius: 0; background: transparent;">
                                            <div class="card-header"
                                                style="background:#f4f6f9; font-weight:bold; border: 1px solid #ddd; border-bottom: none; border-radius: 0;">
                                                Search Flight
                                            </div>
                                            <div class="card-body"
                                                style="background:#fff; border: 1px solid #ddd; padding: 15px;">
                                                <div class="row mb-2">

                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio"
                                                                name="flightType" id="domestic" value="domestic"
                                                                checked>
                                                            <label class="form-check-label"
                                                                for="domestic">Domestic</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio"
                                                                name="flightType" id="international"
                                                                value="international">
                                                            <label class="form-check-label"
                                                                for="international">International</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mb-2">
                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="oneway" value="oneway" checked>
                                                            <label class="form-check-label" for="oneway">One
                                                                way</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="roundtrip" value="roundtrip">
                                                            <label class="form-check-label" for="roundtrip">Round
                                                                Trip</label>
                                                        </div>
                                                    </div>
                                                </div>



                                                <!-- <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <div class="form-check form-check-inline">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="oneway" value="oneway" checked>
                                                            <label class="form-check-label" for="oneway">One way</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-check form-check-inline"
                                                            style="margin-left: 26px;">
                                                            <input class="form-check-input" type="radio" name="tripType"
                                                                id="roundtrip" value="roundtrip">
                                                            <label class="form-check-label" for="roundtrip">Round
                                                                Trip</label>
                                                        </div>
                                                    </div>
                                                </div> -->

                                                <div class="row">
                                                    <div class="col-md-3 form-group datess">
                                                        <label>Onward Date</label>
                                                        <div class="date-wrapper">
                                                            <input type="date" class="form-control" id="apiDate"
                                                                value="<?php echo date('Y-m-d'); ?>"
                                                                min="<?php echo date('Y-m-d'); ?>"
                                                                style="padding: 6px 5px;">
                                                            <img src="img/calendar.png" class="calendar-icon icononward"
                                                                onclick="openPicker('apiDate')">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 form-group" id="returnDateContainer"
                                                        style="display:none;">
                                                        <label>Return Date</label>
                                                        <div class="date-wrapper datess">
                                                            <input type="date" class="form-control" id="apiReturnDate"
                                                                value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                                                min="<?php echo date('Y-m-d'); ?>"
                                                                style="padding: 6px 5px;">
                                                            <img src="img/calendar.png" class="calendar-icon icononward"
                                                                onclick="openPicker('apiReturnDate')">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 form-group" style="position:relative;">
                                                        <label>From</label>
                                                        <input type="text" class="form-control" id="apiFrom"
                                                            autocomplete="off" placeholder="Start typing a city...">
                                                        <div id="apiFromSuggest"
                                                            style="display:none; position:absolute; z-index:1000; width:95%; background:#fff; border:1px solid #ccc; border-radius:4px; max-height:250px; overflow-y:auto; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 form-group" style="position:relative;">
                                                        <label>To</label>
                                                        <input type="text" class="form-control" id="apiTo"
                                                            autocomplete="off" placeholder="Start typing a city...">
                                                        <div id="apiToSuggest"
                                                            style="display:none; position:absolute; z-index:1000; width:95%; background:#fff; border:1px solid #ccc; border-radius:4px; max-height:250px; overflow-y:auto; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                                        </div>
                                                    </div>

                                                </div>
                                                <!-- <div class="row mb-3">
                                                    <div class="col-md-6 form-group">
                                                        <label>Onward Date</label>
                                                        <input type="date" class="form-control" id="apiDate"
                                                            value="2026-04-18">
                                                    </div>
                                                </div> -->

                                                <button class="btn btn-primary" id="searchFlightsBtn"
                                                    style="background-color:#6ba2c7; border-color:#6ba2c7; margin: 0px;">Search</button>
                                            </div>
                                        </div>

                                        <!-- API FLIGHT RESULTS (Now shifted to a Popup Modal but we keep a structural anchor here if needed) -->
                                        <!-- <div class="row mb-3">
                                            <div class="col-md-12 text-center" id="searchLoadingIndicator"
                                                style="display:none;">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Searching for flights...</p>
                                            </div>
                                        </div> -->
                                    </div>
                                </div>



                                <!-- <div class="form-group mb-3">
                                    <label>No of Pax</label>
                                    <input type="number" class="form-control" id="paxCount" value="1" min="1"
                                        style="width: 100%;">
                                </div> -->

                                <!-- Pax Details Box -->
                                <div id="paxDetailsContainer"
                                    style="background:#ebebeb; padding:15px; border:1px solid #ddd; margin-bottom:15px;">
                                    <!-- Dynamically generated pax rows go here -->
                                </div>

                                <!-- Fare details -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="row">

                                            <div class="col-md-12">
                                                <div class="form-group row m-0">
                                                    <label class="col-md-4 col-form-label">Base</label>

                                                    <div class="col-md-4">
                                                        <input type="text" class="form-control"
                                                            style="text-align: right;height: 28px;" id="baseInput">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group row m-0">
                                                    <label class="col-md-4 col-form-label">Taxes & Fees</label>

                                                    <div class="col-md-4">
                                                        <input type="text" class="form-control"
                                                            style="text-align: right;height: 28px;" id="taxInput">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group row m-0">
                                                    <label class="col-md-4 col-form-label">Total</label>

                                                    <div class="col-md-4">
                                                        <input type="text" class="form-control"
                                                            style="text-align: right;height: 28px;" id="totalInput">
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row">

                                            <div class="col-md-12">
                                                <div class="form-group row">
                                                    <label class="col-md-3 col-form-label">Mobile No</label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" id="mobileInput"
                                                            value="9709100140">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group row">
                                                    <label class="col-md-3 col-form-label">Email Id</label>
                                                    <div class="col-md-9">
                                                        <input type="text" class="form-control" id="emailinput"
                                                            value="info@multizonetravels.com">
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>

                                <!-- Mobile No -->
                                <!-- <div class="row mb-3">
                                    <div class="col-md-4 form-group">
                                        <label>Mobile No</label>
                                        <input type="text" class="form-control" id="mobileInput" value="9709100140">
                                    </div>
                                </div> -->

                                <!-- Checkboxes -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="hideFare">
                                            <label class="form-check-label" for="hideFare">Hide Fare</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="nonRefundable">
                                            <label class="form-check-label" for="nonRefundable">Non Refundable</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="useStaticBanner"
                                                checked>
                                            <label class="form-check-label" for="useStaticBanner">Use Static
                                                Banner</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Image Upload -->
                                <div class="row">

                                    <!-- Upload From Device -->
                                    <div class="col-md-6 form-group">
                                        <label>Upload Banner</label>

                                        <div class="input-group">
                                            <div class="custom-file">
                                                <input type="file" class="custom-file-input" id="bannerUpload"
                                                    accept="image/*">
                                                <label class="custom-file-label" for="bannerUpload">Choose Image</label>
                                            </div>

                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-secondary m-0"
                                                    onclick="uploadBanner()">Upload</button>
                                            </div>
                                        </div>

                                        <small class="text-muted">592 × 158 px recommended</small>
                                    </div>


                                    <!-- Select From Master -->
                                    <div class="col-md-6 form-group">
                                        <label>Select From Image Master</label>

                                        <button class="btn btn-info m-0" data-toggle="modal" data-target="#bannerModal">
                                            Choose From Master
                                        </button>

                                    </div>

                                </div>

                                <button class="btn btn-success" onclick="saveTicketOnPrint()"
                                    style="margin-left: 0;">Print Ticket</button>

                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- MAIN TICKET - EXACTLY LIKE IMAGE -->
                            <div class="ticket" id="ticket">
                                <!-- Header -->
                                <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="text-align: left; flex: 1;">
                                        <div class="logo"><img src="img/awer.png" width="150px" alt=""></div>
                                    </div>
                                    <div style="text-align: center; flex: 1;">
                                        <div class="booked-on">Booked on: <span id="displayDate"><?php echo date('d-m-Y'); ?></span></div>
                                    </div>
                                    <div style="text-align: right; flex: 1; display: flex; justify-content: flex-end;">
                                        <img id="pnrBarcode" src="" style="display:none; max-width: 200px; height: 52px; width: 175px;" alt="Barcode">
                                    </div>
                                </div>

                                <!-- Support & PNR Row -->
                                <div class="support-row">
                                    <div class="support-box" id="airlineLogoBox"
                                        style="display:none; align-items: center; justify-content: center; border-right: 1px solid #ccc;">
                                        <img id="airlineLogo" src="" style="max-height: 45px; max-width: 110px;">
                                    </div>
                                    <div class="support-box">
                                        <div class="title">24x7 Support</div>
                                        <div class="phone">📞 +91 97094 00140</div>
                                    </div>
                                    <div class="support-box" style="border-right: none;">
                                        <div class="title" style="margin-left: -40px;">Agency</div>
                                        <div class="phone">📞 +91 97080 00140</div>
                                    </div>
                                    <div class="support-box" id="pnrBox"
                                        style="flex-direction: column; margin-left: 10px; flex: 0.5;">
                                        <div class="title" style="color: #e31b23;">PNR</div>
                                        <div class="phone" id="pnrText" style="font-size: 24px;"></div>
                                    </div>
                                </div>

                                <!-- Onward Flight Details -->
                                <div class="flight-title onward-flight-title">
                                    ✈ Onward Flight Details
                                    <span class="verify-text">*Please verify flight times with the airlines prior to
                                        departure</span>
                                </div>
                                <div class="flight-container" id="flightDetailsContainer">
                                    <!-- Flight details will be injected here -->
                                </div>

                                <!-- Return Flight Details -->
                                <div class="flight-title return-flight-title" style="display:none; margin-top: 15px;">
                                    ✈ Return Flight Details
                                    <span class="verify-text">*Please verify flight times with the airlines prior to
                                        departure</span>
                                </div>
                                <div class="flight-container" id="returnFlightDetailsContainer" style="display:none;">
                                    <!-- Return Flight details will be injected here -->
                                </div>

                                <!-- Passenger Details Table -->
                                <div class="passenger-head"><img src="img/group.png" width="17px" class="mb-1" alt=""> Passenger(s) Details</div>
                                <table class="data-table" id="passengerTable">
                                    <thead id="passengerTableHead">
                                        <tr style="background-color: #d2d2d2;">
                                            <th class="col-sr-no">Sr No.</th>
                                        </tr>
                                    </thead>
                                    <tbody id="passengerBody">
                                        <tr>
                                            <td class="col-sr-no">1</td>
                                            <td>MR</td>
                                            <td>Adult</td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Payment & Contact Details -->
                                <div class="two-columns">
                                    <div class="payment-box">
                                        <div class="box-header">
                                            Payment Details
                                            <span>Amount (INR)</span>
                                        </div>
                                        <div class="amount-line"><span>Base Fare</span><span id="baseAmount">0</span>
                                        </div>
                                        <div class="amount-line"><span>Taxes & Fees</span><span id="taxAmount">0</span>
                                        </div>
                                        <div class="total-line"><span>Total</span><span id="totalAmount">0</span></div>
                                    </div>
                                    <div class="contact-box">
                                        <div class="box-header">Customer Contact Details</div>
                                        <div class="contact-detail">Contact No: <span
                                                id="contactNo">9709100140</span>
                                        </div>
                                        <div class="contact-detail">Email: <span
                                                id="emailId">info@multizonetravels.com</span></div>
                                    </div>
                                </div>

                                <div id="nonRefundableText"
                                    style="display:none; font-weight: bold; margin-top: 18px; font-size: 14px;">
                                    <span style="color: #333;">Note:</span> <span style="color: #e31b23;">This ticket is
                                        100% Non-Refundable & Non-Changeable</span>
                                </div>
                                <!-- Important Information -->
                                <div class="important-title">Important Information</div>

                                <?php
                                $q = mysqli_query($conn, "SELECT important_info FROM ticket_settings LIMIT 1");
                                $data = mysqli_fetch_assoc($q);
                                ?>

                                <ul class="important-list">

                                    <?php echo $data['important_info']; ?>

                                </ul>

                                <!-- Looking for Hotels Banner -->
                                <div class="hotel-banner" id="hotelBanner"
                                    style="background-image:url('<?php echo $banner; ?>');">

                                    <div class="hotel-left">
                                        <h2>LOOKING FOR <br><span id="hotelCity">Mumbai</span> HOTELS?</h2>

                                        <div class="hotel-call">
                                            <span>Call now</span>
                                            <b id="bannerMobile">9709400140</b>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="footer">
                                    <div class="footer-left">FLIGHTS | HOTELS | HOLIDAYS | VISA | FOREX</div>
                                    <div class="footer-right">Dibadih, Doranda, Ranchi-834002</div>
                                </div>
                                <!-- <div class="disclaimer">This is a computer generated E-Ticket. No signature required.</div> -->
                            </div>
                        </div>
                    </div>


                </div>
            </section>
        </div>



        <!-- Banner Select Modal -->

        <div class="modal fade" id="bannerModal">

            <div class="modal-dialog modal-lg">

                <div class="modal-content">

                    <div class="modal-header">
                        <h5>Select Banner</h5>
                        <button class="close" data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <?php

                            $sql = "SELECT image FROM image_master 
        WHERE status='Active' 
        AND is_deleted=0";
                            $res = mysqli_query($conn, $sql);

                            while ($row = mysqli_fetch_assoc($res)) {

                                $img = "uploads/images/" . $row['image'];

                                ?>

                                <div class="col-md-3 mb-4">

                                    <div class="banner-card banner-option" data-img="<?php echo $img; ?>">
                                        <img src="<?php echo $img; ?>">
                                        <div class="banner-overlay">
                                            Select
                                        </div>
                                    </div>

                                </div>

                            <?php } ?>

                        </div>

                    </div>

                </div>

            </div>

        </div> <!-- /.modal -->

        <!-- Flight Search Results Modal -->
        <div class="modal fade" id="flightsModal" tabindex="-1" role="dialog" aria-labelledby="flightsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document" style="max-width: 900px;">
                <div class="modal-content">
                    <div class="modal-header text-center d-block position-relative"
                        style="border-bottom: 2px solid #f4f6f9;">
                        <h5 class="modal-title w-100" id="flightsModalTitle" style="font-size:1.1rem; color:#555;">
                            IXR - DEL | 18/04/2026
                        </h5>
                        <button type="button" class="close position-absolute" data-dismiss="modal" aria-label="Close"
                            style="right: 15px; top: 15px;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body"
                        style="background-color: #f4f6f9; max-height: 70vh; overflow-y: auto; padding: 20px;">
                        <div id="flightsModalBody">
                            <!-- Flight Cards injected here dynamically -->
                        </div>
                    </div>
                    <div class="modal-footer" style="background-color: #fff; border-top: 1px solid #eee;">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>
    </div>

    <script>
        function openPicker(id) {
            const input = document.getElementById(id);

            if (input && input.showPicker) {
                input.showPicker(); // Chrome, Edge
            } else if (input) {
                input.focus(); // fallback
            }
        }
    </script>


    <script>

        $(document).on("click", ".banner-option", function () {

            var img = $(this).data("img");

            // uncheck static banner
            $("#useStaticBanner").prop("checked", false);

            // apply selected banner
            $("#hotelBanner").css(
                "background-image",
                "url('" + img + "')"
            );

            // hide the hotel text
            $(".hotel-left").hide();

            // highlight selected banner
            $(".banner-card").removeClass("selected-banner");
            $(this).addClass("selected-banner");

            // close modal smoothly
            setTimeout(function () {
                $("#bannerModal").modal("hide");
            }, 200);

        });

        // $("#pnrInput").keyup(function () {
        //     var val = $(this).val();
        //     $("#pnrText").text(val);
        //     if (val.length > 0) {
        //         // Dynamically fetch barcode from free API similar to user's implementation
        //         $("#pnrBarcode").attr("src", "https://barcode.tec-it.com/barcode.ashx?data=" + encodeURIComponent(val) + "&code=Code128&dpi=96").show();
        //     } else {
        //         $("#pnrBarcode").hide();
        //     }
        // });

        $("#pnrInput").on("input", function () {

            var val = $(this).val().toUpperCase().trim();
            $(this).val(val);

            // Show PNR text
            $("#pnrText").text(val);

            if (val.length > 0) {

                var barcodeURL =
                    "https://bwipjs-api.metafloor.com/?bcid=code128&text=" +
                    encodeURIComponent(val) +
                    "&scale=3&rotate=N&height=10";

                $("#pnrBarcode").attr("src", barcodeURL).show();

            } else {
                $("#pnrBarcode").hide();
            }

        });

        $("#mobileInput").keyup(function () {
            $("#contactNo").text($(this).val());
        });
        $("#emailinput").keyup(function () {
            $("#emailId").text($(this).val());
        });

        $("#baseInput, #taxInput, #totalInput, #bookingDate").on("input change keyup", function () {
            updateTicketData();
        });

        $("#hideFare, #nonRefundable").on("change", function () {
            updateTicketData();
        });

        $("#baseInput, #totalInput").on("input keyup", function () {
            if ($("#totalInput").val().trim() !== "") {
                var base = parseFloat($("#baseInput").val()) || 0;
                var total = parseFloat($("#totalInput").val()) || 0;
                var tax = total - base;
                $("#taxInput").val(tax >= 0 ? tax : 0);
                updateTicketData();
            }
        });

        function uploadBanner() {

            var input = document.getElementById("bannerUpload");

            if (!input.files || !input.files[0]) {
                alert("Please select an image first.");
                return;
            }

            $("#useStaticBanner").prop("checked", false);

            var reader = new FileReader();

            reader.onload = function (e) {

                $("#hotelBanner").css(
                    "background-image",
                    "url('" + e.target.result + "')"
                );

                $(".hotel-left").hide();

            };

            reader.readAsDataURL(input.files[0]);
        }

        $("#bannerUpload").on("change", function () {

            var fileName = $(this).val().split("\\").pop();
            $(this).next(".custom-file-label").html(fileName);

        });

        $("#mobileInput2").on("input", function () {

            $("#bannerMobile").text($(this).val());

        });

        function getPaxRowHtml(i) {
            var lblType = i === 1 ? "<label>Type</label>" : "";
            var lblInitial = i === 1 ? "<label>Initial</label>" : "";
            var lblName = i === 1 ? "<label>Full Name</label>" : "";
            var lblMeal = i === 1 ? "<label>Meal</label>" : "";
            var lblSeat = i === 1 ? "<label>Seat</label>" : "";
            var lblTicket = i === 1 ? "<label>Ticket</label>" : "";
            var lblServices = i === 1 ? "<label>Services</label>" : "";

            var actionBtn = i === 1
                ? `<button type="button" class="btn btn-sm btn-success add-pax-btn" style="padding: 0 8px; height: 28px;margin: 0px;padding-bottom: 32px;font-size: 21px;" title="Add Passenger">+</button>`
                : `<button type="button" class="btn btn-sm btn-danger remove-pax-btn" style="padding: 0 10px; height: 28px;margin: 0px;padding-bottom: 32px;font-size: 21px;" title="Remove Passenger">-</button>`;

            return `
            <div class="pax-group" id="paxGroup_${i}" style="margin-bottom: 10px;">
                <div class="row pax-row align-items-center mb-2">
                   
                    <div class="col-md-2" style="max-width: 12.666667%;">
                        ${lblType}
                        <select class="form-control pax-type" style="padding: 0px; width: 70px;">
                            <option>Adult</option>
                            <option>Child</option>
                            <option>Infant</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        ${lblInitial}
                        <select class="form-control pax-initial" style="padding: 0px; width: 51px;">
                            <option>Mr</option>
                            <option>Mrs</option>
                            <option>Ms</option>
                            <option>Mstr</option>
                            <option>Miss</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        ${lblName}
                        <input type="text" class="form-control pax-name" style="padding: 0px 0px 0px 6px; width: 158px;">
                    </div>
                    <div class="col-md-1">
                        ${lblMeal}
                        <input type="text" class="form-control pax-meal" style="padding: 0px 0px 0px 6px; width: 52px;">
                    </div>
                     <div class="col-md-1">
                        ${lblSeat}
                        <input type="text" class="form-control pax-seat" style="padding: 0px 0px 0px 6px;">
                    </div>
                    <div class="col-md-2">
                        ${lblTicket}
                        <input type="text" class="form-control pax-ticket" style="padding: 0px 0px 0px 6px; width: 100px;">
                    </div>
                    <div class="col-md-2">
                        ${lblServices}
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <input type="text" class="form-control pax-services" style="padding: 0px 0px 0px 6px;">
                            ${actionBtn}
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function renderPaxForms() {
            var count = parseInt($("#paxCount").val()) || 1;
            var currentCount = $(".pax-group").length;

            if (currentCount === 0) {
                var html = '';
                for (var i = 1; i <= count; i++) {
                    html += getPaxRowHtml(i);
                }
                $("#paxDetailsContainer").html(html);
            } else {
                if (count > currentCount) {
                    for (var i = currentCount + 1; i <= count; i++) {
                        $("#paxDetailsContainer").append(getPaxRowHtml(i));
                    }
                } else if (count < currentCount) {
                    for (var i = currentCount; i > count; i--) {
                        $(".pax-group").last().remove();
                    }
                }
            }
        }

        $("#paxCount").on("change input", function () {
            renderPaxForms();
            updateTicketData();
        });

        $(document).on('click', '.add-pax-btn', function () {
            var currentCount = parseInt($("#paxCount").val()) || 1;
            $("#paxCount").val(currentCount + 1).trigger('change');
        });

        $(document).on('click', '.remove-pax-btn', function () {
            $(this).closest('.pax-group').remove();
            var newCount = $(".pax-group").length;
            $("#paxCount").val(newCount);
            updateTicketData();
        });

        // Initial render
        renderPaxForms();

        // Real-time update for passenger details
        $(document).on("input change", ".pax-name, .pax-initial, .pax-type, .pax-meal, .pax-seat, .pax-ticket, .pax-services", function () {
            updateTicketData();
        });

        function upperCaseText(value) {
            return ((value || "") + "").toUpperCase();
        }

        $(document).on("input blur", ".pax-meal, .pax-seat, .pax-services, .pax-ticket", function () {
            var formatted = upperCaseText($(this).val());
            if ($(this).val() !== formatted) {
                $(this).val(formatted);
                updateTicketData();
            }
        });

        // Push data to ticket before download
        function updateTicketData() {
            // Update booking date
            var bDate = $("#bookingDate").val();
            if (bDate) {
                var p = bDate.split('-');
                if (p.length === 3) {
                    $("#displayDate").text(p[2] + "-" + p[1] + "-" + p[0]);
                } else {
                    $("#displayDate").text(bDate);
                }
            } else {
                $("#displayDate").text("<?php echo date('d-m-Y'); ?>");
            }

            // Build Pax Table on e-ticket only — show columns you actually filled (form unchanged)
            var paxRows = [];
            $(".pax-group").each(function (index) {
                var initial = (($(this).find(".pax-initial").val() || "") + "").trim();
                var name = (($(this).find(".pax-name").val() || "") + "").trim();
                var type = (($(this).find(".pax-type").val() || "") + "").trim();
                var ticket = upperCaseText((($(this).find(".pax-ticket").val() || "") + "").trim());
                var seat = upperCaseText((($(this).find(".pax-seat").val() || "") + "").trim());
                var meal = upperCaseText((($(this).find(".pax-meal").val() || "") + "").trim());
                var services = upperCaseText((($(this).find(".pax-services").val() || "") + "").trim());
                var fullName = name ? (initial + " " + name).trim() : "";

                paxRows.push({
                    sr: String(index + 1),
                    name: fullName,
                    type: type,
                    ticket: ticket,
                    seat: seat,
                    meal: meal,
                    services: services
                });
            });

            var smsParts = [];
            if (paxRows.some(function (r) { return (r.seat || "").trim() !== ""; })) {
                smsParts.push({ key: 'seat', label: 'Seat' });
            }
            if (paxRows.some(function (r) { return (r.meal || "").trim() !== ""; })) {
                smsParts.push({ key: 'meal', label: 'Meal' });
            }
            if (paxRows.some(function (r) { return (r.services || "").trim() !== ""; })) {
                smsParts.push({ key: 'services', label: 'Services' });
            }

            var colDefs = [
                { key: 'sr', label: 'Sr No.', always: true },
                { key: 'name', label: 'Passenger(s) Name' },
                { key: 'type', label: 'Type' },
                { key: 'ticket', label: 'Ticket No' }
            ];
            if (smsParts.length > 0) {
                colDefs.push({
                    key: 'seatMealServices',
                    label: smsParts.map(function (p) { return p.label; }).join('/'),
                    smsParts: smsParts
                });
            }

            var activeCols = colDefs.filter(function (col) {
                if (col.always) return true;
                if (col.key === 'seatMealServices') return true;
                return paxRows.some(function (row) {
                    return (row[col.key] || "").trim() !== "";
                });
            });

            var headHtml = '<tr style="background-color: #d2d2d2;">';
            activeCols.forEach(function (col) {
                var thClass = col.key === 'sr' ? ' class="col-sr-no"' : '';
                headHtml += '<th' + thClass + '>' + col.label + '</th>';
            });
            headHtml += '</tr>';
            $("#passengerTableHead").html(headHtml);

            var tbody = $("#passengerBody");
            tbody.empty();
            if (paxRows.length === 0) {
                tbody.append('<tr><td colspan="' + activeCols.length + '" class="text-muted">—</td></tr>');
            } else {
                paxRows.forEach(function (row) {
                    var tr = '<tr>';
                    activeCols.forEach(function (col) {
                        var cell = "";
                        if (col.key === 'seatMealServices' && col.smsParts) {
                            var vals = [];
                            col.smsParts.forEach(function (p) {
                                if ((row[p.key] || "").trim() !== "") {
                                    vals.push(upperCaseText(row[p.key]));
                                }
                            });
                            cell = vals.join(' / ');
                        } else {
                            cell = row[col.key] || "";
                        }
                        if (col.key === 'sr') {
                            tr += '<td class="col-sr-no">' + cell + '</td>';
                        } else if (col.key === 'name') {
                            tr += '<td style="font-weight:bold;text-transform:uppercase;">' + cell + '</td>';
                        } else if (col.key === 'ticket') {
                            tr += '<td style="text-transform:uppercase;">' + String(cell).toUpperCase() + '</td>';
                        } else {
                            tr += '<td>' + cell + '</td>';
                        }
                    });
                    tr += '</tr>';
                    tbody.append(tr);
                });
            }

            // Handle Checkboxes
            if ($("#hideFare").is(":checked")) {
                $(".payment-box").hide();
            } else {
                $(".payment-box").show();
                var bVal = Math.round(parseFloat($("#baseInput").val()) || 0);
                var tVal = Math.round(parseFloat($("#taxInput").val()) || 0);
                var totVal = Math.round(parseFloat($("#totalInput").val()) || 0);
                $("#baseAmount").text(bVal.toLocaleString('en-IN'));
                $("#taxAmount").text(tVal.toLocaleString('en-IN'));
                $("#totalAmount").text(totVal.toLocaleString('en-IN'));
            }

            var onwardTitle = "✈ Onward Flight Details";
            var returnTitle = "✈ Return Flight Details";
            var badgeHtml = "";
            if ($("#nonRefundable").is(":checked")) {
                badgeHtml = " ";
                $("#nonRefundableText").show();
            } else {
                $("#nonRefundableText").hide();
            }
            $(".onward-flight-title").html(onwardTitle + badgeHtml + "<span class='verify-text'>*Please verify flight times with the airlines prior to departure</span>");
            $(".return-flight-title").html(returnTitle + badgeHtml + "<span class='verify-text'>*Please verify flight times with the airlines prior to departure</span>");



        }

        var staticBanner = "img/static-banner.jpg";

        $(document).ready(function () {

            if ($("#useStaticBanner").is(":checked")) {

                $("#hotelBanner").css(
                    "background-image",
                    "url('" + staticBanner + "')"
                );
                $(".hotel-left").show();

            } else {
                $(".hotel-left").hide();
            }

        });

        $("#useStaticBanner").change(function () {

            var staticBanner = "img/static-banner.jpg";

            if ($(this).is(":checked")) {

                $("#hotelBanner").css(
                    "background-image",
                    "url('" + staticBanner + "')"
                );
                $(".hotel-left").show();

            } else {
                $(".hotel-left").hide();
            }

        });



        // Refresh page after each print is completed/cancelled.
        window.addEventListener("afterprint", function () {
            window.location.reload();
        });

        function saveTicketOnPrint() {
            updateTicketData();
            var names = [];
            $(".pax-group").each(function () {
                var initial = $(this).find(".pax-initial").val() || "";
                var n = $(this).find(".pax-name").val();
                if (n) names.push(initial + " " + n);
            });

            const element = document.getElementById("ticket");
            const opt = {
                // Match saved PDF layout with print layout.
                margin: [6, 8, 12, 8],
                filename: 'flight-ticket.pdf',
                image: { type: 'jpeg', quality: 1 },
                html2canvas: {
                    scale: 3,
                    useCORS: true,
                    scrollY: 0,
                    onclone: function (clonedDoc) {
                        var clonedTicket = clonedDoc.getElementById("ticket");
                        if (clonedTicket) {
                            var styleEl = clonedDoc.createElement("style");
                            styleEl.innerHTML = `
                                #ticket {
                                    margin: 0 auto !important;
                                    padding: 8px 8px 18mm 8px !important;
                                    box-sizing: border-box !important;
                                    box-shadow: none !important;
                                    border: none !important;
                                    width: 100% !important;
                                    max-width: 100% !important;
                                    display: flex !important;
                                    flex-direction: column !important;
                                }
                                #ticket .phone {
                                    white-space: nowrap !important;
                                    display: inline-block !important;
                                }
                                #ticket, #ticket * {
                                    font-size: 90% !important;
                                }
                                #ticket .ticket-footer {
                                    margin-top: auto !important;
                                    position: static !important;
                                    width: 100% !important;
                                }
                            `;
                            clonedDoc.head.appendChild(styleEl);
                        }
                    }
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).output('blob').then(function (pdfBlob) {
                var reader = new FileReader();
                reader.readAsDataURL(pdfBlob);
                reader.onloadend = function () {
                    var pdfBase64 = reader.result;

                    var sector = ($("#apiFrom").attr("data-code") || ($("#apiFrom").val() || "IXR").substring(0,3).toUpperCase()) + " - " + ($("#apiTo").attr("data-code") || ($("#apiTo").val() || "DEL").substring(0,3).toUpperCase());
                    var airlines = [];
                    $(".airline-name").each(function () {
                        var txt = $(this).text().trim();
                        if (txt && airlines.indexOf(txt) === -1) airlines.push(txt);
                    });
                    var airline = airlines.length > 0 ? airlines.join(", ") : "-";

                    $.post("ajax/save_ticket.php", {
                        edit_id: typeof window.editTicketId !== 'undefined' ? window.editTicketId : 0,
                        pnr: $("#pnrInput").val(),
                        date: $("#bookingDate").val(),
                        pax: $("#paxCount").val(),
                        base: $("#baseInput").val(),
                        tax: $("#taxInput").val(),
                        total: $("#totalInput").val(),
                        passenger_names: names.join(", "),
                        sector: sector,
                        airline: airline,
                        departure_date: $("#apiDate").val(),
                        arrival_date: $("#apiReturnDate").val(),
                        flight_html: $("#flightDetailsContainer").html(),
                        return_flight_html: $("input[name='tripType']:checked").val() === "roundtrip" ? $("#returnFlightDetailsContainer").html() : "",
                        pdf_data: pdfBase64
                    });
                    
                    window.print();
                };
            });
        }

        // Removed setupAutosuggest to prevent double dropdown with custom initAirportAutosuggest

        $("#apiTo").on("change blur", function () {
            var val = $(this).val();
            if (val) {
                var city = $(this).attr("data-city");
                // If they have a valid city stored, use it, else fallback to what they typed
                var display = city ? city : val;
                display = display.split(',')[0].replace(/\(.*?\)/g, '').trim();
                $("#hotelCity").text(display.toUpperCase());
            } else {
                $("#hotelCity").text("MUMBAI");
                $(this).removeAttr("data-city");
            }
        });

        // Also update when clicking Search to ensure it's synced
        $("#searchFlightsBtn").click(function (e) {
            e.preventDefault();

            var fromVal = $("#apiFrom").val().trim();
            var toVal = $("#apiTo").val().trim();

            if (toVal) {
                var city = $("#apiTo").attr("data-city") || toVal;
                city = city.split(',')[0].replace(/\(.*?\)/g, '').trim();
                $("#hotelCity").text(city.toUpperCase());
            }
            var date = $("#apiDate").val().trim();

            var from = $("#apiFrom").attr("data-code") || fromVal;
            var to = $("#apiTo").attr("data-code") || toVal;

            var fromCity = $("#apiFrom").attr("data-city") || fromVal;
            fromCity = fromCity.split(',')[0].replace(/\(.*?\)/g, '').trim();

            var toCity = $("#apiTo").attr("data-city") || toVal;
            toCity = toCity.split(',')[0].replace(/\(.*?\)/g, '').trim();
            var pax = parseInt($("#paxCount").val()) || 1;
            var tType = $("input[name='tripType']:checked").val() === "roundtrip" ? "ROUNDTRIP" : "ONEWAY";
            var returnDate = $("#apiReturnDate").val().trim();

            if (!from || !to || !date || (tType === "ROUNDTRIP" && !returnDate)) {
                alert("Please fill all required search fields");
                return;
            }

            var sectorInfos = [{
                src: { code: from, name: from, city: from },
                dest: { code: to, name: to, city: to },
                date: moment(date).format('YYYY-MM-DD'),
                debug: false
            }];

            if (tType === "ROUNDTRIP") {
                sectorInfos.push({
                    src: { code: to, name: to, city: to },
                    dest: { code: from, name: from, city: from },
                    date: moment(returnDate).format('YYYY-MM-DD'),
                    debug: false
                });
            }

            // Create exact payload structure from Angular source code
            var payload = {
                sectorInfos: sectorInfos,
                prefAirlines: [{ code: 'ALL', name: 'ALL' }],
                "class": 'ALL',
                paxCount: { adt: parseInt(pax), chd: 0, inf: 0 },
                route: 'ALL',
                disc: false,
                multiHop: false,
                multiCity: false,
                senior: false,
                special: false,
                domestic: true,
                isOfflineSearch: false,
                isPaxWiseCommission: false
            };

            $("#searchFlightsBtn").text("Searching...").prop("disabled", true);
            $("#searchLoadingIndicator").show();

            $.ajax({
                url: "ajax/via_search.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify(payload),
                success: function (res) {
                    $("#searchFlightsBtn").text("Search").prop("disabled", false);
                    $("#searchLoadingIndicator").hide();

                    if (typeof res === "string") {
                        try { res = JSON.parse(res); } catch (err) { }
                    }

                    console.log("API Result:", res);

                    function createFlightCard(item, overallDepCode, overallArrCode, overallDepCityName, overallArrCityName) {
                        var journey = item.journey || item;
                        var flightsArray = (journey.flights && journey.flights.length > 0) ? journey.flights : [item.primaryFlight || item];

                        var primaryF = item.primaryFlight || flightsArray[0] || item;
                        var pAirlineObj = primaryF.carrier || primaryF.airline || {};
                        var pFname = pAirlineObj.name || primaryF.airlineName || primaryF.carrierName || "Airline";
                        var pFno = (pAirlineObj.code || "FL") + "-" + (primaryF.flightNo || primaryF.flightNumber || "101");
                        var pCarrierCode = pAirlineObj.code || "6E";
                        var stops = flightsArray.length > 1 ? (flightsArray.length - 1) : 0;
                        var stopsStr = stops === 0 ? "Non Stop" : stops + " Stop(s)";

                        var pDurationRaw = primaryF.flyTime || primaryF.duration || 150;
                        var pDuration = typeof pDurationRaw === 'number' ? Math.floor(pDurationRaw / 60) + " hrs " + (pDurationRaw % 60) + " min" : pDurationRaw;

                        var faresObj = journey.fares || {};
                        var paxFares = (faresObj.paxFares && faresObj.paxFares.adt) ? faresObj.paxFares.adt : {};
                        var totObj = paxFares.total || faresObj.totalFare || primaryF.fareDetails || {};
                        var baseObj = paxFares.base || {};
                        var taxObj = paxFares.tax || {};

                        var base = Math.round(parseFloat(baseObj.amount || baseObj.baseFare || 3500) || 0);
                        var tax = Math.round(parseFloat(taxObj.amount || taxObj.taxes || 500) || 0);
                        var tot = Math.round(parseFloat(totObj.amount || totObj.total || (base + tax)) || 0);

                        var dTimeRaw = (primaryF.depDetail && primaryF.depDetail.time) ? primaryF.depDetail.time : (primaryF.departureTime || primaryF.depTime || "08:00 AM");
                        var aTimeRaw = (primaryF.arrDetail && primaryF.arrDetail.time) ? primaryF.arrDetail.time : (primaryF.arrivalTime || primaryF.arrTime || "10:30 AM");
                        var dTime = moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : dTimeRaw;
                        var aTime = moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : aTimeRaw;

                        var fData = {
                            fname: pFname, fno: pFno, dTime: dTime, aTime: aTime,
                            base: base, tax: tax, tot: tot,
                            terminal: primaryF.depDetail?.terminal || "T1",
                            stops: stopsStr, duration: pDuration,
                            baggage: primaryF.baggage || "Cabin: 7kg | Check-in: 15kg",
                            carrierCode: pCarrierCode,
                            segments: flightsArray
                        };
                        var encodedData = encodeURIComponent(JSON.stringify(fData));

                        var dTimeRawUnix = moment(dTimeRaw).valueOf();
                        var aTimeRawUnix = moment(aTimeRaw).valueOf();

                        var cardHtml = `<div class="card mb-2 select-flight-card flight-card-hover" data-flight='${encodedData}' data-duration="${pDurationRaw}" data-dtime="${dTimeRawUnix}" data-atime="${aTimeRawUnix}" data-stops="${stops}" data-airline="${pFname}" data-price="${tot}" style="border: 1px solid #e9ecef; box-shadow: none; border-radius:4px; cursor:pointer; background-color: #f8f9fa;">
                            <div class="card-body p-3">`;

                        flightsArray.forEach(function (f, index) {
                            var airlineObj = f.carrier || f.airline || {};
                            var fname = airlineObj.name || f.airlineName || f.carrierName || "Airline";
                            var fno = (airlineObj.code || "FL") + "-" + (f.flightNo || f.flightNumber || "101");
                            var carrierCode = airlineObj.code || "6E";
                            var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://images.kiwi.com/airlines/64/" + carrierCode + ".png");

                            var dTimeRaw = (f.depDetail && f.depDetail.time) ? f.depDetail.time : (f.departureTime || f.depTime || "08:00 AM");
                            var aTimeRaw = (f.arrDetail && f.arrDetail.time) ? f.arrDetail.time : (f.arrivalTime || f.arrTime || "10:30 AM");
                            var dTime = moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : dTimeRaw;
                            var aTime = moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : aTimeRaw;

                            var durationRaw = f.flyTime || f.duration || 150;
                            var duration = typeof durationRaw === 'number' ? Math.floor(durationRaw / 60) + " hrs " + (durationRaw % 60) + " min" : durationRaw;

                            var legDepCity = (f.depDetail && f.depDetail.name) ? f.depDetail.name : (index === 0 ? overallDepCityName : "City");
                            var legDepCode = (f.depDetail && f.depDetail.code) ? f.depDetail.code : (index === 0 ? overallDepCode : "XXX");
                            var legArrCity = (f.arrDetail && f.arrDetail.name) ? f.arrDetail.name : (index === flightsArray.length - 1 ? overallArrCityName : "City");
                            var legArrCode = (f.arrDetail && f.arrDetail.code) ? f.arrDetail.code : (index === flightsArray.length - 1 ? overallArrCode : "XXX");

                            var depTerminal = (f.depDetail && f.depDetail.terminal) ? "Terminal " + f.depDetail.terminal : "Terminal 1";
                            var arrTerminal = (f.arrDetail && f.arrDetail.terminal) ? "Terminal " + f.arrDetail.terminal : "Terminal 1";

                            var borderBottom = index < flightsArray.length - 1 ? "border-bottom: 1px dashed #ddd; padding-bottom: 10px; margin-bottom: 10px;" : "";

                            cardHtml += `
                                <div class="row align-items-center" style="${borderBottom}">
                                    <div class="col-md-4 d-flex align-items-center" style="padding-right:0;">
                                        <img src="${logoUrl}" alt="${carrierCode}" style="width:30px; height:30px; object-fit:contain; margin-right:8px;">
                                        <div>
                                            <div style="font-weight:700; font-size:12px; color:#333; line-height:1.2;">${fname}</div>
                                            <div class="text-muted" style="font-size:11px;">${fno}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3" style="padding-left:5px; padding-right:5px;">
                                        <div style="font-weight:700; font-size:12px; color:#333;">${legDepCity} (${legDepCode})</div>
                                        <div class="text-muted" style="font-size:10px;">${depTerminal}</div>
                                        <div class="text-muted" style="font-size:11px;">${dTime}</div>
                                    </div>
                                    <div class="col-md-3" style="padding-left:5px; padding-right:5px;">
                                        <div style="font-weight:700; font-size:12px; color:#333;">${legArrCity} (${legArrCode})</div>
                                        <div class="text-muted" style="font-size:10px;">${arrTerminal}</div>
                                        <div class="text-muted" style="font-size:11px;">${aTime}</div>
                                    </div>
                                    <div class="col-md-2 text-left" style="padding-left:5px;">
                                        <div style="font-weight:700; font-size:12px; color:#333;">${duration}</div>
                                        <div class="text-muted" style="font-size:11px;">Non Stop</div>
                                        ${index === 0 ? `<div style="font-weight:bold; font-size:14px; color:#e31b23; margin-top:3px;">₹${Math.round(tot).toLocaleString('en-IN')}</div>` : ''}
                                    </div>
                                </div>`;
                        });

                        cardHtml += `</div></div>`;
                        return cardHtml;
                    }

                    var flights = [];
                    var rFlights = [];

                    if (res && res.data) {
                        if (res.data.onwardJourneys && res.data.onwardJourneys.length > 0) {
                            res.data.onwardJourneys.forEach(function (journey) {
                                flights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                            });
                        }
                        if (res.data.returnJourneys && res.data.returnJourneys.length > 0) {
                            res.data.returnJourneys.forEach(function (journey) {
                                rFlights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                            });
                        }
                    } else if (res && res.onwardJourneys) {
                        res.onwardJourneys.forEach(function (journey) {
                            flights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                        });
                        if (res.returnJourneys) {
                            res.returnJourneys.forEach(function (journey) {
                                rFlights.push({ journey: journey, primaryFlight: (journey.flights && journey.flights.length > 0) ? journey.flights[0] : {} });
                            });
                        }
                    } else if (res && res.flights) {
                        flights = res.flights;
                    }

                    var modalBody = $("#flightsModalBody");
                    modalBody.empty();

                    var sortHtml = `
                    <div class="d-flex justify-content-end align-items-center w-100 mt-2 pb-2" style="font-size: 14px; border-bottom: 1px solid #eee;">
                        <label class="mb-0 mr-2" style="font-weight: 600; color: #555; font-size: 13px;"><i class="fa fa-sort-amount-desc"></i> Sort By:</label>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="price" data-order="asc">Price <i class="fa fa-sort"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="duration" data-order="asc">Duration <i class="fa fa-sort"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="dtime" data-order="asc">Departure <i class="fa fa-sort"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flight-sort-btn" data-sort="atime" data-order="asc">Arrival <i class="fa fa-sort"></i></button>
                        </div>
                    </div>
                    <div class="w-100 pt-2 pb-2 mb-2" style="font-size: 13px; border-bottom: 1px solid #ddd;">
                        <div class="row m-0">
                            <div class="col-md-5 p-0 d-flex align-items-center">
                                <label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-filter"></i> Stops:</label>
                                <div class="btn-group filter-group-stops" role="group">
                                    <button type="button" class="btn btn-sm btn-secondary active flight-filter-btn" data-filter="stops" data-value="all">All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="stops" data-value="0">Non-Stop</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="stops" data-value="1">1 Stop</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="stops" data-value="2">2+ Stops</button>
                                </div>
                            </div>
                            <div class="col-md-7 p-0 d-flex align-items-center justify-content-end">
                                <label class="mb-0 mr-2" style="font-weight: 600; color: #555;"><i class="fa fa-clock-o"></i> Time:</label>
                                <div class="btn-group filter-group-time" role="group">
                                    <button type="button" class="btn btn-sm btn-secondary active flight-filter-btn" data-filter="time" data-value="all">All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="morning">Morning</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="afternoon">Afternoon</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="evening">Evening</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary flight-filter-btn" data-filter="time" data-value="night">Night</button>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    if (tType === "ROUNDTRIP") {
                        $("#flightsModal .modal-dialog").css("max-width", "1200px");
                        var rightTitle = `${to} - ${from} <span style='color:#ccc'>|</span> ${moment(returnDate).format('DD/MM/YYYY')}`;
                        var leftTitle = `${from} - ${to} <span style='color:#ccc'>|</span> ${moment(date).format('DD/MM/YYYY')}`;
                        $("#flightsModalTitle").html(`<div class="row w-100 m-0"><div class="col-md-6 text-center">${leftTitle}</div><div class="col-md-6 text-center" style="border-left:1px solid #ccc;">${rightTitle}</div></div>${sortHtml}`);

                        var html = '<div class="row mt-2"><div class="col-md-6" id="onwardCol"></div><div class="col-md-6" id="returnCol"></div></div>';
                        modalBody.html(html);

                        if (flights.length > 0) {
                            flights.forEach(f => $("#onwardCol").append(createFlightCard(f, from, to, fromCity, toCity)));
                        } else {
                            $("#onwardCol").html('<div class="alert alert-info">No onward flights found.</div>');
                        }

                        if (rFlights.length > 0) {
                            rFlights.forEach(f => $("#returnCol").append(createFlightCard(f, to, from, toCity, fromCity)));
                        } else {
                            $("#returnCol").html('<div class="alert alert-info">No return flights found.</div>');
                        }
                    } else {
                        $("#flightsModal .modal-dialog").css("max-width", "900px");
                        $("#flightsModalTitle").html(`<div class="w-100 text-center">${from} - ${to} <span style='color:#ccc'>|</span> ${moment(date).format('DD/MM/YYYY')}</div>${sortHtml}`);

                        if (flights.length > 0) {
                            flights.forEach(f => modalBody.append(createFlightCard(f, from, to, fromCity, toCity)));
                        } else {
                            modalBody.html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No flights found for this route and date. Please try a different search.</div>');
                        }
                    }

                    $("#flightsModal").modal("show");
                },
                error: function (err) {
                    $("#searchFlightsBtn").text("Search").prop("disabled", false);
                    $("#searchLoadingIndicator").hide();

                    $("#flightsModalTitle").html(`${from} - ${to} <span style='color:#ccc'>|</span> ${date}`);
                    var modalBody = $("#flightsModalBody");
                    modalBody.empty();

                    var errMsg = (err.responseJSON && err.responseJSON.err && err.responseJSON.err.title)
                        ? err.responseJSON.err.title
                        : "Could not connect to flight search. Please try again.";

                    modalBody.html(`
                        <div class="alert alert-danger" role="alert">
                            <h5 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Search Failed</h5>
                            <p style="margin-bottom:0; font-size:13px;">${errMsg}</p>
                        </div>
                    `);

                    $("#flightsModal").modal("show");
                    console.error("API proxy failed:", err);
                }
            });
        });

        // ==========================================
        //         AIRPORT AUTOSUGGEST LOGIC
        // ==========================================

        function initAirportAutosuggest(inputId, suggestDivId) {
            var timeoutId = null;
            var inputEl = $("#" + inputId);
            var suggestObj = $("#" + suggestDivId);

            inputEl.on("keyup", function () {
                var query = $(this).val().trim();

                if (query.length < 2) {
                    suggestObj.hide();
                    return;
                }

                clearTimeout(timeoutId);
                timeoutId = setTimeout(function () {
                    $.ajax({
                        url: 'ajax/mmt_autosuggest.php',
                        type: 'GET',
                        data: { q: query },
                        success: function (res) {
                            suggestObj.empty();

                            // Handing makemytrip specific response inside res.r
                            var items = res.r ? res.r : (Array.isArray(res) ? res : []);
                            if (!Array.isArray(items)) {
                                items = [items];
                            }

                            var html = "";
                            var count = 0;
                            if (items.length > 0) {
                                for (var i = 0; i < items.length; i++) {
                                    var item = items[i];
                                    var code = item.iata || "";
                                    var city = item.ct || item.cName || "";
                                    var country = item.cnty || item.countryName || "";
                                    var fullStr = city + ", " + country + " (" + code + ")";
                                    if (code && city) {
                                        html += `<div class="suggest-item p-2 border-bottom autocomplete-hover" style="cursor:pointer; font-size:14px;" data-code="${code}" data-full="${fullStr}" data-city="${city}">
                                            <div class="d-flex justify-content-between">
                                                <div><i class="fa fa-plane text-muted mr-1"></i> ${city} <small class="text-muted">${country}</small></div>
                                                <div class="font-weight-bold text-primary">${code}</div>
                                            </div>
                                        </div>`;
                                        count++;
                                    }
                                }
                            }

                            if (count > 0) {
                                suggestObj.html(html).show();
                            } else {
                                suggestObj.hide();
                            }
                        },
                        error: function () {
                            suggestObj.hide();
                        }
                    });
                }, 300);
            });

            $(document).on("click", "#" + suggestDivId + " .suggest-item", function () {
                var code = $(this).data("code");
                var fullText = $(this).data("full");
                var city = $(this).data("city");
                inputEl.attr("data-code", code);
                if (city) inputEl.attr("data-city", city);
                inputEl.val(fullText ? fullText : code);
                suggestObj.hide();
                inputEl.trigger("change");
            });

            $(document).on("click", function (e) {
                if (!$(e.target).closest("#" + inputId).length && !$(e.target).closest("#" + suggestDivId).length) {
                    suggestObj.hide();
                }
            });
        }

        initAirportAutosuggest("apiFrom", "apiFromSuggest");
        initAirportAutosuggest("apiTo", "apiToSuggest");

        // Use proper delegated click handler to prevent inline JS escaping bugs
        window.selectedOnwardFare = null;
        window.selectedReturnFare = null;

        $(document).on("click", ".select-flight-card", function () {
            var rawData = $(this).attr("data-flight");
            var f = JSON.parse(decodeURIComponent(rawData));

            var isReturn = $(this).closest("#returnCol").length > 0;
            var isRoundTrip = $("input[name='tripType']:checked").val() === "roundtrip";

            var container = isReturn ? $("#returnFlightDetailsContainer") : $("#flightDetailsContainer");
            var depCode = isReturn ? ($("#apiTo").val() || "BOM") : ($("#apiFrom").val() || "DEL");
            var arrCode = isReturn ? ($("#apiFrom").val() || "DEL") : ($("#apiTo").val() || "BOM");

            var cardHtml = "";
            var segments = f.segments && f.segments.length > 0 ? f.segments : null;

            if (segments) {
                var prevArrTimeRaw = null;
                segments.forEach(function (leg, index) {
                    var airlineObj = leg.carrier || leg.airline || {};
                    var fname = airlineObj.name || leg.airlineName || leg.carrierName || f.fname;
                    var fno = (airlineObj.code || "FL") + "-" + (leg.flightNo || leg.flightNumber || f.fno.split('-')[1] || "101");

                    var dTimeRaw = (leg.depDetail && leg.depDetail.time) ? leg.depDetail.time : (leg.departureTime || leg.depTime);
                    var aTimeRaw = (leg.arrDetail && leg.arrDetail.time) ? leg.arrDetail.time : (leg.arrivalTime || leg.arrTime);

                    var dTime = dTimeRaw && moment(dTimeRaw).isValid() ? moment(dTimeRaw).format('DD MMM YYYY | hh:mm A') : (f.dTime || "");
                    var aTime = aTimeRaw && moment(aTimeRaw).isValid() ? moment(aTimeRaw).format('DD MMM YYYY | hh:mm A') : (f.aTime || "");

                    var durationRaw = leg.flyTime || leg.duration || 150;
                    var duration = typeof durationRaw === 'number' ? Math.floor(durationRaw / 60) + " hrs " + (durationRaw % 60) + " min" : durationRaw;

                    var legDepCity = (leg.depDetail && leg.depDetail.name) ? leg.depDetail.name : (index === 0 ? ($("#apiFrom").attr("data-city") || "City") : "City");
                    var legDepCode = (leg.depDetail && leg.depDetail.code) ? leg.depDetail.code : (index === 0 ? depCode : "XXX");
                    var legArrCity = (leg.arrDetail && leg.arrDetail.name) ? leg.arrDetail.name : (index === segments.length - 1 ? ($("#apiTo").attr("data-city") || "City") : "City");
                    var legArrCode = (leg.arrDetail && leg.arrDetail.code) ? leg.arrDetail.code : (index === segments.length - 1 ? arrCode : "XXX");

                    var depTerminal = (leg.depDetail && leg.depDetail.terminal) ? "Terminal " + leg.depDetail.terminal : "";
                    var arrTerminal = (leg.arrDetail && leg.arrDetail.terminal) ? "Terminal " + leg.arrDetail.terminal : "";

                    cardHtml += `
                    <div class="flight-card" style="${index > 0 ? 'margin-top: 0px;' : ''}">
                        <div class="flight-card-header">
                            <div class="flight-num">Flight ${index + 1}</div>
                            <div class="header-label"><img src="img/flight.png" style="width:10px!important;"> Departing</div>
                            <div class="header-label"><img src="img/flight1.png" style="width:10px!important;"> Arriving</div>
                            ${index === 0 ? '<div style="cursor:pointer;" class="trash-icon-container"><i class="fa fa-trash text-muted"></i></div>' : '<div style="width: 14px;"></div>'}
                        </div>
                        <div class="flight-card-body">
                            <div class="flight-info-group airline-info">
                                <div class="airline-name">${fname}</div>
                                <div class="flight-code">${fno}</div>
                            </div>
                            <div class="flight-info-group location-info">
                            <div class="d-flex align-items-center">
                                <div class="city">${legDepCity} (${legDepCode})</div>
                                ${depTerminal ? '<div class="terminal" style="font-size:12px; color:#666;">' + depTerminal + '</div>' : ''}</div>
                                <div class="datetime">${dTime}</div>
                            </div>
                            <div class="flight-info-group location-info">
                            <div class="d-flex align-items-center">
                                <div class="city">${legArrCity} (${legArrCode})</div>
                                ${arrTerminal ? '<div class="terminal" style="font-size:12px; color:#666;">' + arrTerminal + '</div>' : ''}</div>
                                <div class="datetime">${aTime}</div>
                            </div>
                            <div class="flight-meta-right">
                                <div class="stops">Non Stop</div>
                                <div class="duration">${duration}</div>
                            </div>
                        </div>
                        <div class="baggage-info" style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="flex: 1; text-align: left;">${f.baggage}</div>`;

                    var nextLeg = segments[index + 1];
                    if (nextLeg) {
                        var nextDTimeRaw = (nextLeg.depDetail && nextLeg.depDetail.time) ? nextLeg.depDetail.time : (nextLeg.departureTime || nextLeg.depTime);
                        if (aTimeRaw && nextDTimeRaw) {
                            var layoverMs = moment(nextDTimeRaw).valueOf() - moment(aTimeRaw).valueOf();
                            if (layoverMs > 0) {
                                var layoverMins = Math.floor(layoverMs / 60000);
                                var layoverStr = Math.floor(layoverMins / 60) + " hrs " + (layoverMins % 60) + " min";
                                cardHtml += `
                                <div style="flex: 1; text-align: center; font-size: 12px; font-weight: 600; color: #555;">
                                    <i class="fa fa-clock-o"></i> Layover: ${layoverStr}
                                </div>
                                <div style="flex: 1;"></div>`;
                            }
                        }
                    }

                    cardHtml += `
                        </div>
                    </div>`;
                });
            } else {
                cardHtml = `
                <div class="flight-card">
                    <div class="flight-card-header">
                        <div class="flight-num">Flight 1</div>
                        <div class="header-label">✈ Departing</div>
                        <div class="header-label">✈ Arriving</div>
                        <div style="cursor:pointer;"><i class="fa fa-trash text-muted"></i></div>
                    </div>
                    <div class="flight-card-body">
                        <div class="flight-info-group airline-info">
                            <div class="airline-name">${f.fname}</div>
                            <div class="flight-code">${f.fno}</div>
                        </div>
                        <div class="flight-info-group location-info">
                            <div class="city">${depCode}</div>
                            ${f.terminal && f.terminal !== "undefined" ? '<div class="terminal" style="font-size:12px; color:#666;">Terminal ' + f.terminal + '</div>' : ''}
                            <div class="datetime">${f.dTime}</div>
                        </div>
                        <div class="flight-info-group location-info">
                            <div class="city">${arrCode}</div>
                            <div class="datetime">${f.aTime}</div>
                        </div>
                        <div class="flight-meta-right">
                            <div class="stops">${f.stops}</div>
                            <div class="duration">${f.duration}</div>
                        </div>
                    </div>
                    <div class="baggage-info">
                        ${f.baggage}
                    </div>
                </div>`;
            }
            container.html(cardHtml);

            // Populate Input fields and handle modal
            if (isRoundTrip) {
                if (isReturn) {
                    window.selectedReturnFare = f;
                } else {
                    window.selectedOnwardFare = f;
                }

                var base = (window.selectedOnwardFare ? window.selectedOnwardFare.base : 0) + (window.selectedReturnFare ? window.selectedReturnFare.base : 0);
                var tax = (window.selectedOnwardFare ? window.selectedOnwardFare.tax : 0) + (window.selectedReturnFare ? window.selectedReturnFare.tax : 0);
                var tot = (window.selectedOnwardFare ? window.selectedOnwardFare.tot : 0) + (window.selectedReturnFare ? window.selectedReturnFare.tot : 0);

                $("#baseInput").val(base).trigger("keyup");
                $("#taxInput").val(tax).trigger("keyup");
                $("#totalInput").val(tot).trigger("keyup");

                var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://pics.avs.io/200/100/" + f.carrierCode + ".png");
                $("#airlineLogo").attr("src", logoUrl);
                $("#airlineLogoBox").css("display", "flex");

                $(this).closest(".col-md-6").find(".select-flight-card").css("border", "1px solid #e9ecef").removeClass("border-success");
                $(this).css("border", "2px solid #28a745").addClass("border-success");

                if (window.selectedOnwardFare && window.selectedReturnFare) {
                    setTimeout(function () { $("#flightsModal").modal("hide"); }, 500);
                }
            } else {
                $("#flightsModal").modal("hide");

                window.selectedOnwardFare = f;
                window.selectedReturnFare = null;

                $("#baseInput").val(f.base).trigger("keyup");
                $("#taxInput").val(f.tax).trigger("keyup");
                $("#totalInput").val(f.tot).trigger("keyup");

                var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://pics.avs.io/200/100/" + f.carrierCode + ".png");
                $("#airlineLogo").attr("src", logoUrl);
                $("#airlineLogoBox").css("display", "flex");
            }
        });

        $(document).on("click", ".fa-trash", function () {
            $(this).closest(".flight-container").empty();
            if ($("#flightDetailsContainer").is(":empty") && $("#returnFlightDetailsContainer").is(":empty")) {
                $("#airlineLogoBox").hide();
                window.selectedOnwardFare = null;
                window.selectedReturnFare = null;
                $("#baseInput").val(0).trigger("keyup");
                $("#taxInput").val(0).trigger("keyup");
                $("#totalInput").val(0).trigger("keyup");
            }
        });

        $("input[name='tripType']").change(function () {
            if ($(this).val() === "roundtrip") {
                $("#returnDateContainer").show();
                $(".return-flight-title").show();
                $("#returnFlightDetailsContainer").show();
            } else {
                $("#returnDateContainer").hide();
                $(".return-flight-title").hide();
                $("#returnFlightDetailsContainer").hide().empty();
            }
        });

        // Date Validation Logic
        $("#apiDate").on("change", function () {
            var onwardDate = $(this).val();
            if (onwardDate) {
                $("#apiReturnDate").attr("min", onwardDate);
                if ($("#apiReturnDate").val() < onwardDate) {
                    $("#apiReturnDate").val(onwardDate);
                }
            }
        });

        // Flight Sorting Logic
        $(document).on("click", ".flight-sort-btn", function () {
            var $btn = $(this);
            var sortType = $btn.attr("data-sort");
            var currentOrder = $btn.attr("data-order");
            var newOrder = "asc";

            if ($btn.hasClass("active")) {
                newOrder = currentOrder === "asc" ? "desc" : "asc";
            }

            $(".flight-sort-btn").attr("data-order", "asc").removeClass("active btn-secondary").addClass("btn-outline-secondary").find("i").attr("class", "fa fa-sort");

            $btn.attr("data-order", newOrder).removeClass("btn-outline-secondary").addClass("active btn-secondary");
            if (newOrder === "asc") {
                $btn.find("i").attr("class", "fa fa-sort-amount-asc");
            } else {
                $btn.find("i").attr("class", "fa fa-sort-amount-desc");
            }

            function sortCards(containerId) {
                var $container = $(containerId);
                var $cards = $container.find(".select-flight-card");
                if ($cards.length === 0) return;

                $cards.sort(function (a, b) {
                    var valA = parseInt($(a).attr("data-" + sortType));
                    var valB = parseInt($(b).attr("data-" + sortType));

                    if (newOrder === "asc") {
                        return valA - valB;
                    } else {
                        return valB - valA;
                    }
                });

                $cards.detach().appendTo($container);
            }

            if ($("#onwardCol").length) {
                sortCards("#onwardCol");
                sortCards("#returnCol");
            } else {
                sortCards("#flightsModalBody");
            }
        });

        // Flight Filtering Logic
        $(document).on("click", ".flight-filter-btn", function () {
            var $btn = $(this);

            $btn.siblings().removeClass("active btn-secondary").addClass("btn-outline-secondary");
            $btn.removeClass("btn-outline-secondary").addClass("active btn-secondary");

            applyFlightFilters();
        });

        function applyFlightFilters() {
            var stopFilter = $(".filter-group-stops .active").data("value");
            var timeFilter = $(".filter-group-time .active").data("value");

            function filterCards(containerId) {
                var $container = $(containerId);
                if ($container.length === 0) return;

                $container.find(".select-flight-card").each(function () {
                    var $card = $(this);
                    var showCard = true;

                    if (stopFilter !== "all" && showCard) {
                        var cardStops = parseInt($card.attr("data-stops"));
                        if (stopFilter == "0" && cardStops !== 0) showCard = false;
                        if (stopFilter == "1" && cardStops !== 1) showCard = false;
                        if (stopFilter == "2" && cardStops < 2) showCard = false;
                    }

                    if (timeFilter !== "all" && showCard) {
                        var dTimeUnix = parseInt($card.attr("data-dtime"));
                        var hour = moment(dTimeUnix).hour();

                        if (timeFilter === "morning" && (hour < 6 || hour >= 12)) showCard = false;
                        if (timeFilter === "afternoon" && (hour < 12 || hour >= 18)) showCard = false;
                        if (timeFilter === "evening" && (hour < 18 || hour > 23)) showCard = false;
                        if (timeFilter === "night" && (hour > 5)) showCard = false;
                    }

                    if (showCard) {
                        $card.show();
                    } else {
                        $card.hide();
                    }
                });
            }

            if ($("#onwardCol").length) {
                filterCards("#onwardCol");
                filterCards("#returnCol");
            } else {
                filterCards("#flightsModalBody");
            }
        }

    </script>

    <script>

        document.querySelectorAll("#apiToSuggest div").forEach(function (item) {

            item.addEventListener("click", function () {

                let cityName = this.innerText;

                document.getElementById("apiTo").value = cityName;

                document.getElementById("hotelCity").innerText = cityName;

                document.getElementById("apiToSuggest").style.display = "none";

            });

        });

    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <?php if ($editData): ?>
    <script>
    $(document).ready(function() {
        // Pre-fill fields
        $("#bookingDate").val("<?php echo date('Y-m-d', strtotime($editData['booking_date'])); ?>").trigger('input');
        $("#pnrInput").val("<?php echo addslashes($editData['pnr']); ?>").trigger('input');
        $("#paxCount").val("<?php echo $editData['pax_count']; ?>").trigger('change');
        
        setTimeout(function() {
            $("#baseInput").val("<?php echo $editData['base_fare']; ?>").trigger('input');
            $("#taxInput").val("<?php echo $editData['tax']; ?>").trigger('input');
            $("#totalInput").val("<?php echo $editData['total_fare']; ?>").trigger('input');

            // Fill passenger names
            <?php
            if (!empty($editData['passenger_names'])) {
                $names = array_map('trim', explode(',', $editData['passenger_names']));
                foreach ($names as $index => $fullName) {
                    $parts = explode(' ', $fullName, 2);
                    $initial = isset($parts[0]) ? $parts[0] : '';
                    $name = isset($parts[1]) ? $parts[1] : '';
                    echo "$('.pax-group').eq($index).find('.pax-initial').val('".addslashes($initial)."');\n";
                    echo "$('.pax-group').eq($index).find('.pax-name').val('".addslashes($name)."').trigger('input');\n";
                }
            }
            ?>

            // Extract Sector if possible
            <?php
            $sector = $editData['sector'];
            if (!empty($sector) && strpos($sector, ' - ') !== false) {
                list($from, $to) = explode(' - ', $sector);
                echo "$('#apiFrom').val('".addslashes($from)."').attr('data-code', '".addslashes($from)."');\n";
                echo "$('#apiTo').val('".addslashes($to)."').attr('data-code', '".addslashes($to)."');\n";
                echo "$('#hotelCity').text('".addslashes(strtoupper($to))."');\n";
            }
            ?>
            
            <?php if (!empty($editData['departure_date'])): ?>
            $("#apiDate").val("<?php echo addslashes($editData['departure_date']); ?>");
            <?php endif; ?>

            // Restore Flight HTML
            <?php if (!empty($editData['flight_html'])): ?>
                $("#flightDetailsContainer").html(`<?php echo str_replace('`', '\`', $editData['flight_html']); ?>`);
            <?php endif; ?>

            <?php if (!empty($editData['return_flight_html'])): ?>
                $("#returnFlightDetailsContainer").html(`<?php echo str_replace('`', '\`', $editData['return_flight_html']); ?>`);
            <?php endif; ?>

            var hasReturnFlight = $("#returnFlightDetailsContainer .flight-card").length > 0;
            if (hasReturnFlight) {
                $("#roundtrip").prop("checked", true);
                $("#returnDateContainer").show();
                $(".return-flight-title").show();
                $("#returnFlightDetailsContainer").show();
                <?php if (!empty($editData['arrival_date'])): ?>
                $("#apiReturnDate").val("<?php echo addslashes($editData['arrival_date']); ?>");
                <?php endif; ?>
            } else {
                $("#oneway").prop("checked", true);
                $("#returnDateContainer").hide();
                $(".return-flight-title").hide();
                $("#returnFlightDetailsContainer").hide().empty();
            }

            var flightCode = $("#flightDetailsContainer .flight-code").first().text().trim();
            if (flightCode) {
                var carrierCode = flightCode.split("-")[0].trim();
                if (carrierCode) {
                    var logoUrl = "ajax/image_proxy.php?url=" + encodeURIComponent("https://pics.avs.io/200/100/" + carrierCode + ".png");
                    $("#airlineLogo").attr("src", logoUrl);
                    $("#airlineLogoBox").css("display", "flex");
                }
            }
        }, 500);

        window.editTicketId = <?php echo $editData['id']; ?>;
    });
    </script>
    <?php endif; ?>
</body>
</html>