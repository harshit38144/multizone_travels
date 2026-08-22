<?php
require '../connection.php';
header('Content-Type: application/json');

$paxJsonCol = mysqli_query($conn, "SHOW COLUMNS FROM `saved_tickets` LIKE 'passengers_json'");
if ($paxJsonCol && mysqli_num_rows($paxJsonCol) === 0) {
    mysqli_query($conn, "ALTER TABLE `saved_tickets` ADD `passengers_json` LONGTEXT NULL AFTER `passenger_names`");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pnr = mysqli_real_escape_string($conn, $_POST['pnr']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $pax = (int)$_POST['pax'];
    $base = (float)$_POST['base'];
    $tax = (float)$_POST['tax'];
    $total = (float)$_POST['total'];
    $names = mysqli_real_escape_string($conn, $_POST['passenger_names']);
    $passengersJson = mysqli_real_escape_string($conn, isset($_POST['passengers_json']) ? $_POST['passengers_json'] : '');
    $sector = mysqli_real_escape_string($conn, isset($_POST['sector']) ? $_POST['sector'] : '');
    $airline = mysqli_real_escape_string($conn, isset($_POST['airline']) ? $_POST['airline'] : '');
    $pdfData = isset($_POST['pdf_data']) ? $_POST['pdf_data'] : '';

    $dbPath = "";
    if (!empty($pdfData)) {
        // Ensure uploads/tickets exists
        $targetDir = "../uploads/tickets/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $requestedName = isset($_POST['pdf_filename']) ? trim((string) $_POST['pdf_filename']) : '';
        $requestedName = preg_replace('/[\\\\\\/:*?"<>|]/', '', $requestedName);
        $requestedName = preg_replace('/\s+/', ' ', $requestedName);

        if ($requestedName !== '') {
            if (!preg_match('/\.pdf$/i', $requestedName)) {
                $requestedName .= '.pdf';
            }
            $fileName = $requestedName;
        } else {
            $fileName = 'Ticket_' . ($pnr ?: time()) . '.pdf';
        }

        $filePath = $targetDir . $fileName;
        if (file_exists($filePath)) {
            $baseName = preg_replace('/\.pdf$/i', '', $fileName);
            $fileName = $baseName . '_' . uniqid() . '.pdf';
            $filePath = $targetDir . $fileName;
        }

        // Decode and save PDF
        $pdfParts = explode(',', $pdfData);
        if (count($pdfParts) == 2) {
            $pdfBase64 = $pdfParts[1];
            $pdfDecoded = base64_decode($pdfBase64);
            file_put_contents($filePath, $pdfDecoded);
            $dbPath = "uploads/tickets/" . $fileName;
        }
    }

    $departure_date = mysqli_real_escape_string($conn, isset($_POST['departure_date']) ? $_POST['departure_date'] : '');
    $arrival_date = mysqli_real_escape_string($conn, isset($_POST['arrival_date']) ? $_POST['arrival_date'] : '');
    $flight_html = mysqli_real_escape_string($conn, isset($_POST['flight_html']) ? $_POST['flight_html'] : '');
    $return_flight_html = mysqli_real_escape_string($conn, isset($_POST['return_flight_html']) ? $_POST['return_flight_html'] : '');

    // Check if edit_id is provided
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if ($edit_id > 0) {
        $sql = "UPDATE saved_tickets SET 
                pnr='$pnr', booking_date='$date', pax_count=$pax, 
                base_fare=$base, tax=$tax, total_fare=$total, 
                passenger_names='$names', passengers_json='$passengersJson', sector='$sector', 
                airline='$airline', flight_html='$flight_html', 
                return_flight_html='$return_flight_html',
                departure_date='$departure_date', arrival_date='$arrival_date'";
        
        if (!empty($dbPath)) {
            $sql .= ", pdf_path='$dbPath'";
            
            // Delete old PDF to prevent storage bloat
            $q = mysqli_query($conn, "SELECT pdf_path FROM saved_tickets WHERE id=$edit_id");
            if ($r = mysqli_fetch_assoc($q)) {
                if (!empty($r['pdf_path']) && file_exists("../" . $r['pdf_path'])) {
                    @unlink("../" . $r['pdf_path']);
                }
            }
        }
        
        $sql .= " WHERE id=$edit_id";
    } else {
        // Insert to DB
        $sql = "INSERT INTO saved_tickets (pnr, booking_date, pax_count, base_fare, tax, total_fare, passenger_names, passengers_json, sector, airline, flight_html, return_flight_html, departure_date, arrival_date, pdf_path) 
                VALUES ('$pnr', '$date', $pax, $base, $tax, $total, '$names', '$passengersJson', '$sector', '$airline', '$flight_html', '$return_flight_html', '$departure_date', '$arrival_date', '$dbPath')";
    }
            
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Ticket successfully saved/updated in database.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database operation failed: ' . mysqli_error($conn)]);
    }
}
?>
