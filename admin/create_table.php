<?php
$conn = new mysqli("localhost", "root", "", "db_multizone");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS printed_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pnr VARCHAR(50),
    booking_date DATE,
    pax_count INT,
    base_fare DECIMAL(10,2),
    tax_amount DECIMAL(10,2),
    total_amount DECIMAL(10,2),
    trip_type VARCHAR(50),
    flight_details TEXT,
    passenger_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table printed_tickets created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
