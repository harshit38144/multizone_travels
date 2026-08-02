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

// Date Range Filter Logic
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$date_filter_type = isset($_GET['date_filter_type']) ? $_GET['date_filter_type'] : 'created_at';



// Handle Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];

    // Attempt to delete PDF file if exists
    $q = mysqli_query($conn, "SELECT pdf_path FROM saved_tickets WHERE id=$del_id");
    if ($r = mysqli_fetch_assoc($q)) {
        if (!empty($r['pdf_path']) && file_exists($r['pdf_path'])) {
            @unlink($r['pdf_path']);
        }
    }

    // Delete record
    if (mysqli_query($conn, "DELETE FROM saved_tickets WHERE id=$del_id")) {
        $_SESSION['msg'] = "Ticket deleted successfully.";
        header("Location: eticketslist.php");
        exit;
    }
}

// Handle Update
if (isset($_POST['update_ticket'])) {
    $id = (int) $_POST['ticket_id'];
    $booking_date = mysqli_real_escape_string($conn, $_POST['booking_date']);
    $pnr = mysqli_real_escape_string($conn, $_POST['pnr']);
    $pax_count = (int) $_POST['pax_count'];
    $base_fare = (float) $_POST['base_fare'];
    $tax = (float) $_POST['tax'];
    $total_fare = (float) $_POST['total_fare'];
    $passenger_names = mysqli_real_escape_string($conn, $_POST['passenger_names']);
    $pdf_path = mysqli_real_escape_string($conn, $_POST['pdf_path']);
    $sector = mysqli_real_escape_string($conn, $_POST['sector']);
    $airline = mysqli_real_escape_string($conn, $_POST['airline']);

    $sql = "UPDATE saved_tickets SET 
            booking_date='$booking_date', 
            pnr='$pnr', 
            pax_count=$pax_count, 
            base_fare=$base_fare, 
            tax=$tax, 
            total_fare=$total_fare, 
            passenger_names='$passenger_names', 
            pdf_path='$pdf_path',
            sector='$sector',
            airline='$airline'
            WHERE id=$id";

    if (mysqli_query($conn, $sql)) {
        $msg = "Ticket updated successfully.";
    } else {
        $msg = "Error updating ticket: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Saved Tickets</title>

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

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        .filter-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .date-input-group {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 4px 12px;
            transition: all 0.2s;
        }

        .date-input-group:focus-within {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .date-input-group i {
            color: #6c757d;
            margin-right: 8px;
        }

        .date-input-group input {
            border: none;
            box-shadow: none;
            outline: none;
            padding: 4px 0;
            background: transparent;
            font-size: 14px;
            color: #495057;
        }

        .date-input-group input:focus {
            outline: none;
        }

        .btn-filter {
            font-weight: 500;
            font-size: 14px;
            padding: 7px 16px;
            transition: all 0.2s;
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
                        <div class="card-header border-0 pb-0">
                            <h5 class="mb-0 font-weight-bold" style="color: #333;">Saved Tickets</h5>
                        </div>

                        <!-- TABLE -->
                        <div class="card-body">

                            <!-- PROFESSIONAL FILTER BAR -->
                            <div class="filter-container">
                                <form method="GET" class="m-0">
                                    <div class="row align-items-center">

                                        <!-- Date Range Selectors -->
                                        <div class="col-lg-5 col-md-12 mb-3 mb-lg-0">
                                            <label class="text-muted mb-2"
                                                style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;"><i
                                                    class="fas fa-calendar-day mr-1"></i> Select Date Range</label>
                                            <input type="hidden" name="date_filter_type"
                                                value="<?= htmlspecialchars($date_filter_type); ?>">
                                            <div class="d-flex align-items-center">
                                                <div class="date-input-group flex-fill">
                                                    <i class="far fa-calendar-alt"></i>
                                                    <input type="date" name="from_date"
                                                        value="<?= htmlspecialchars($from_date); ?>"
                                                        style="width:100%;">
                                                </div>
                                                <span class="mx-3 text-muted"
                                                    style="font-weight:700; font-size:12px;">TO</span>
                                                <div class="date-input-group flex-fill">
                                                    <i class="far fa-calendar-alt"></i>
                                                    <input type="date" name="to_date"
                                                        value="<?= htmlspecialchars($to_date); ?>" style="width:100%;">
                                                </div>

                                                <button type="submit" class="btn btn-primary shadow-sm ml-3"
                                                    style="border-radius: 6px; padding: 5px 15px; font-weight:600; font-size:14px;">
                                                    <i class="fas fa-search mr-1"></i> Search
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Filter Buttons -->
                                        <div
                                            class="col-lg-7 col-md-12 d-flex justify-content-lg-end justify-content-start align-items-end mt-2 mt-lg-0">
                                            <div class="d-flex align-items-center">
                                                <div class="mr-3 text-muted d-none d-md-block"
                                                    style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">
                                                    Filter By:</div>

                                                <div class="btn-group shadow-sm" role="group">
                                                    <button type="submit" name="date_filter_type" value="created_at"
                                                        class="btn <?= ($date_filter_type == 'created_at' && (!empty($from_date) || !empty($to_date))) ? 'btn-primary' : 'btn-light border'; ?> btn-filter">
                                                        <i class="fas fa-ticket-alt mr-1"></i> Booking
                                                    </button>
                                                    <button type="submit" name="date_filter_type" value="departure_date"
                                                        class="btn <?= ($date_filter_type == 'departure_date') ? 'btn-info text-white' : 'btn-light border'; ?> btn-filter">
                                                        <i class="fas fa-plane-departure mr-1"></i> Departure
                                                    </button>
                                                    <button type="submit" name="date_filter_type" value="arrival_date"
                                                        class="btn <?= ($date_filter_type == 'arrival_date') ? 'btn-warning text-dark' : 'btn-light border'; ?> btn-filter">
                                                        <i class="fas fa-plane-arrival mr-1"></i> Arrival
                                                    </button>
                                                </div>

                                                <?php if (!empty($from_date) || !empty($to_date)) { ?>
                                                    <a href="eticketslist.php"
                                                        class="btn btn-outline-danger btn-filter ml-3 shadow-sm"
                                                        style="border-radius:20px; border-width: 2px;">
                                                        <i class="fas fa-times mr-1"></i> Clear
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>

                            <table id="ticketTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>Booking Date & Time</th>
                                        <th>Pax Name</th>
                                        <th>Sector</th>
                                        <th>Airline</th>
                                        <th>Dep Date</th>
                                        <!-- <th>Arr Date</th> -->
                                        <th>PNR</th>
                                        <th>Fare</th>
                                        <th>Boarding</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php
                                    $where = [];
                                    if (!empty($from_date) && !empty($to_date)) {
                                        if ($date_filter_type === 'created_at') {
                                            $from = mysqli_real_escape_string($conn, $from_date . " 00:00:00");
                                            $to = mysqli_real_escape_string($conn, $to_date . " 23:59:59");
                                            $where[] = "created_at BETWEEN '$from' AND '$to'";
                                        } else {
                                            $from = mysqli_real_escape_string($conn, $from_date);
                                            $to = mysqli_real_escape_string($conn, $to_date);
                                            $type = mysqli_real_escape_string($conn, $date_filter_type);
                                            $where[] = "$type BETWEEN '$from' AND '$to'";
                                        }
                                    } elseif (!empty($from_date)) {
                                        if ($date_filter_type === 'created_at') {
                                            $from = mysqli_real_escape_string($conn, $from_date . " 00:00:00");
                                            $where[] = "created_at >= '$from'";
                                        } else {
                                            $from = mysqli_real_escape_string($conn, $from_date);
                                            $type = mysqli_real_escape_string($conn, $date_filter_type);
                                            $where[] = "$type >= '$from'";
                                        }
                                    } elseif (!empty($to_date)) {
                                        if ($date_filter_type === 'created_at') {
                                            $to = mysqli_real_escape_string($conn, $to_date . " 23:59:59");
                                            $where[] = "created_at <= '$to'";
                                        } else {
                                            $to = mysqli_real_escape_string($conn, $to_date);
                                            $type = mysqli_real_escape_string($conn, $date_filter_type);
                                            $where[] = "$type <= '$to'";
                                        }
                                    }

                                    $whereClause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

                                    $orderBy = "ORDER BY id DESC";
                                    if ($date_filter_type === 'departure_date') {
                                        $orderBy = "ORDER BY departure_date = '', departure_date IS NULL, departure_date ASC";
                                    } elseif ($date_filter_type === 'arrival_date') {
                                        $orderBy = "ORDER BY arrival_date = '', arrival_date IS NULL, arrival_date ASC";
                                    }

                                    $query = "SELECT * FROM saved_tickets $whereClause $orderBy";

                                    $result = mysqli_query($conn, $query);

                                    while ($row = mysqli_fetch_assoc($result)) {

                                        // Process Pax Name
                                        $paxNames = array_map('trim', explode(',', $row['passenger_names']));
                                        $paxName = "N/A";
                                        $paxBadge = "";
                                        if (count($paxNames) > 0 && !empty($paxNames[0])) {
                                            $paxName = htmlspecialchars($paxNames[0]);
                                            if (count($paxNames) > 1) {
                                                $extraCount = count($paxNames) - 1;
                                                $paxBadge = "<span class='badge badge-info ml-1'>+{$extraCount}</span>";
                                            }
                                        }

                                        // Fallback placeholders for Sector and Airline if they don't exist in DB
                                        $sector = isset($row['sector']) && !empty($row['sector']) ? htmlspecialchars($row['sector']) : 'IXR - DEL';
                                        $airline = isset($row['airline']) ? htmlspecialchars($row['airline']) : '-';

                                        // Booking Date & Time (using created_at or fallback to booking_date)
                                        $bookingDateTime = !empty($row['created_at']) ? date('d-m-Y h:i A', strtotime($row['created_at'])) : date('d-m-Y', strtotime($row['booking_date']));

                                        // Format Dep/Arr dates
                                        $depDateStr = !empty($row['departure_date']) ? date('d-m-Y', strtotime($row['departure_date'])) : '-';
                                        $arrDateStr = !empty($row['arrival_date']) ? date('d-m-Y', strtotime($row['arrival_date'])) : '-';

                                        ?>
                                        <tr>
                                            <td><?= $bookingDateTime; ?></td>
                                            <td style="text-transform: uppercase;"><?= $paxName . $paxBadge; ?></td>
                                            <td><?= $sector; ?></td>
                                            <td><?= $airline; ?></td>
                                            <td><?= $depDateStr; ?></td>
                                            <!-- <td><?= $arrDateStr; ?></td> -->
                                            <td><?= htmlspecialchars($row['pnr']); ?></td>
                                            <td><?= $row['total_fare']; ?></td>
                                            <?php
                                            $checkinStatus = isset($row['webcheckin_status']) && $row['webcheckin_status'] !== '' ? $row['webcheckin_status'] : 'pass';
                                            $btnClass = $checkinStatus === 'done' ? 'btn-success' : 'btn-outline-primary';
                                            $btnText = $checkinStatus === 'done' ? 'Boarding Done' : 'Webcheckin';
                                            ?>
                                            <td>
                                                <button class="btn btn-sm <?= $btnClass; ?> btn-webcheckin"
                                                    data-id="<?= $row['id']; ?>" data-status="<?= $checkinStatus; ?>"
                                                    data-airline="<?= $airline; ?>">
                                                    <?= $btnText; ?>
                                                </button>
                                            </td>
                                            <td>
                                                <div class="d-flex" style="gap: 5px;">
                                                    <a href="javascript:void(0)" class="action-btn btn-view-ticket"
                                                        style="background:#e3f2fd; color:#0d47a1;"
                                                        data-pdf="<?= htmlspecialchars($row['pdf_path']); ?>"
                                                        title="View"><i
                                                            class="fas fa-eye"></i></a>
                                                    <a href="etickets.php?edit_id=<?= $row['id']; ?>" class="action-btn"
                                                        style="background:#d9efe1; color:green;" title="Edit"><i
                                                            class="fas fa-pen"></i></a>
                                                    <a href="<?= $row['pdf_path']; ?>" download class="action-btn"
                                                        style="background:#e6f7ec; color:#28a745;" title="Download PDF"><i
                                                            class="fas fa-download"></i></a>
                                                    <a href="eticketslist.php?delete_id=<?= $row['id']; ?>"
                                                        class="action-btn" style="background:#ffebee; color:#b71c1c;"
                                                        title="Delete"
                                                        onclick="return confirm('Are you sure you want to delete this ticket?');"><i
                                                            class="fas fa-trash"></i></a>
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

        <!-- TICKET MODAL -->
        <div class="modal fade" id="addTicketModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="" method="POST">
                        <div class="modal-header">
                            <h5 id="ticketModalTitle">Ticket Details</h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="ticket_id" id="modalTicketId">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>Booking Date</label>
                                    <input type="date" name="booking_date" id="modalBookingDate" class="form-control">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>PNR</label>
                                    <input type="text" name="pnr" id="modalPnr" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Pax Count</label>
                                    <input type="number" name="pax_count" id="modalPaxCount" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Sector</label>
                                    <input type="text" name="sector" id="modalSector" class="form-control"
                                        placeholder="IXR - DEL">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Airline</label>
                                    <input type="text" name="airline" id="modalAirline" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Base Fare</label>
                                    <input type="number" step="0.01" name="base_fare" id="modalBaseFare"
                                        class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Tax</label>
                                    <input type="number" step="0.01" name="tax" id="modalTax" class="form-control">
                                </div>
                                <div class="col-md-4 form-group">
                                    <label>Total Fare</label>
                                    <input type="number" step="0.01" name="total_fare" id="modalTotalFare"
                                        class="form-control">
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>Passenger Names</label>
                                    <input type="text" name="passenger_names" id="modalNames" class="form-control"
                                        placeholder="Comma separated">
                                </div>
                                <div class="col-md-12 form-group">
                                    <label>PDF Path</label>
                                    <input type="text" name="pdf_path" id="modalPdfPath" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="update_ticket" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- VIEW TICKET MODAL -->
        <div class="modal fade" id="viewTicketModal">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">View Ticket</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe id="ticketIframe" src="" style="width: 100%; height: 80vh; border: none;"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer-links.php'; ?>

        <script>
            $(function () {
                var table = $('#ticketTable').DataTable({
                    "order": [] // Disable initial sorting so server-side order is respected
                });

                // Webcheckin Toggle Button
                $(document).on('click', '.btn-webcheckin', function () {
                    var btn = $(this);
                    var airline = btn.data('airline');
                    var ticketId = btn.data('id');
                    var status = btn.data('status');
                    var newStatus = status === 'pass' ? 'done' : 'pass';

                    var url = "";
                    if (airline) {
                        var a = airline.toLowerCase();
                        if (a.includes('indigo')) url = "https://www.goindigo.in/web-check-in.html";
                        else if (a.includes('air india express')) url = "https://www.airindiaexpress.com/check-in";
                        else if (a.includes('air india')) url = "https://www.airindia.com/in/en/manage/web-checkin.html";
                        else if (a.includes('vistara')) url = "https://www.airvistara.com/in/en/check-in";
                        else if (a.includes('spicejet')) url = "https://www.spicejet.com/#check-in";
                        else if (a.includes('akasa')) url = "https://www.akasaair.com/web-check-in";
                        else if (a.includes('alliance')) url = "https://www.allianceair.in/checkin";
                        else if (a.includes('star air')) url = "https://www.starair.in/web-check-in";
                        else if (a.includes('indiaone')) url = "https://indiaoneair.com/";
                    }

                    if (url) {
                        window.open(url, '_blank');
                    } else if (airline && airline !== '-') {
                        window.open("https://www.google.com/search?q=" + encodeURIComponent(airline + " web check in"), '_blank');
                    }

                    // Update UI
                    if (newStatus === 'done') {
                        btn.removeClass('btn-outline-primary').addClass('btn-success');
                        btn.text('Boarding Done');
                    } else {
                        btn.removeClass('btn-success').addClass('btn-outline-primary');
                        btn.text('Webcheckin');
                    }
                    btn.data('status', newStatus);

                    // Persist state
                    $.post('ajax/update_webcheckin.php', { id: ticketId, status: newStatus });
                });

                // View Ticket Modal
                $(document).on('click', '.btn-view-ticket', function () {
                    var pdfPath = $(this).data('pdf');
                    if (pdfPath) {
                        $('#ticketIframe').attr('src', pdfPath);
                        $('#viewTicketModal').modal('show');
                    } else {
                        alert("Saved print copy not available. Please print the ticket first.");
                    }
                });

                // Clear iframe on close
                $('#viewTicketModal').on('hidden.bs.modal', function () {
                    $('#ticketIframe').attr('src', '');
                });

                // Clear modal when adding new
                $('.btn-add').click(function () {
                    $('#ticketModalTitle').text('Add Ticket');
                    $('#modalTicketId').val('');
                    $('form')[0].reset();
                });

            });
        </script>

    </div>
</body>

</html>