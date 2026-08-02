<?php
session_start();
include 'connection.php';

$msg = "";
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

/* ================= AJAX HANDLERS ================= */
if (isset($_POST['action'])) {

    // FETCH LOGS
    if ($_POST['action'] == 'fetch') {

        $where = " WHERE 1 ";

        if (!empty($_POST['admin_id'])) {
            $admin_id = $_POST['admin_id'];
            $where .= " AND l.admin_id='$admin_id'";
        }

        if (!empty($_POST['from_date']) && !empty($_POST['to_date'])) {
            $from = $_POST['from_date'] . " 00:00:00";
            $to = $_POST['to_date'] . " 23:59:59";
            $where .= " AND l.created_at BETWEEN '$from' AND '$to'";
        }

        $sql = "
            SELECT l.*, a.name 
            FROM admin_log_history l
            LEFT JOIN admin a ON a.id = l.admin_id
            $where
            ORDER BY l.id DESC
        ";

        $run = mysqli_query($conn, $sql);
        $i = 1;

        while ($row = mysqli_fetch_assoc($run)) {

            // highlight failed logins
            $danger = (stripos($row['message'], 'fail') !== false)
                ? 'style="background:#f8d7da;"'
                : '';

            echo "<tr $danger>
                <td>{$i}</td>
                <td>{$row['name']}</td>
                <td>{$row['message']}</td>
                <td>{$row['created_at']}</td>
                <td>
                    <button class='btn btn-sm btn-danger deleteLog' data-id='{$row['id']}'>
                        <i class='fas fa-trash'></i>
                    </button>
                </td>
            </tr>";
            $i++;
        }
        exit;
    }

    // DELETE SINGLE LOG
    if ($_POST['action'] == 'delete') {
        mysqli_query($conn, "DELETE FROM admin_log_history WHERE id='{$_POST['id']}'");
        exit;
    }

    // CLEAR ALL LOGS
    if ($_POST['action'] == 'clear') {
        mysqli_query($conn, "TRUNCATE TABLE admin_log_history");
        exit;
    }
}
/* ================= END AJAX ================= */

// fetch admins for filter dropdown
$admins = mysqli_query($conn, "SELECT id, name FROM admin ORDER BY name ASC");
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Admin Log History</title>
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

                    <!-- FILTER -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">

                                <div class="col-md-3">
                                    <label>Admin</label>
                                    <select id="admin_id" class="form-control">
                                        <option value="">All Admins</option>
                                        <?php while ($a = mysqli_fetch_assoc($admins)) { ?>
                                            <option value="<?= $a['id'] ?>"><?= $a['name'] ?></option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label>From Date</label>
                                    <input type="date" id="from_date" class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label>To Date</label>
                                    <input type="date" id="to_date" class="form-control">
                                </div>

                                <div class="col-md-3 d-flex align-items-end">
                                    <button id="filterBtn" class="btn btn-primary mr-2">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    <button id="clearLogs" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Clear Logs
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- TABLE -->
                    <div class="card">
                        <div class="card-body">
                            <table id="logTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Admin</th>
                                        <th>Message</th>
                                        <th>Date & Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="logData"></tbody>
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

            // INIT DATATABLE ONLY ONCE
            let table = $('#logTable').DataTable({
                responsive: true,
                ordering: false
            });

            function loadLogs() {
                $.post("log_history.php", {
                    action: "fetch",
                    admin_id: $("#admin_id").val(),
                    from_date: $("#from_date").val(),
                    to_date: $("#to_date").val()
                }, function (data) {
                    table.clear().destroy();   // destroy safely
                    $("#logData").html(data);  // replace tbody
                    table = $('#logTable').DataTable({
                        responsive: true,
                        ordering: false
                    });
                });
            }

            loadLogs();

            $("#filterBtn").click(loadLogs);

            $(document).on("click", ".deleteLog", function () {
                if (confirm("Delete this log?")) {
                    $.post("log_history.php", {
                        action: "delete",
                        id: $(this).data("id")
                    }, loadLogs);
                }
            });

            $("#clearLogs").click(function () {
                if (confirm("Clear all logs?")) {
                    $.post("log_history.php", { action: "clear" }, loadLogs);
                }
            });

        });
    </script>

</body>

</html>