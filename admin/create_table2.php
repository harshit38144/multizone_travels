<?php
$conn = new mysqli("localhost", "root", "", "db_multizone");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS saved_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pnr VARCHAR(50),
    booking_date VARCHAR(50),
    pax_count INT,
    base_fare DECIMAL(10,2),
    tax DECIMAL(10,2),
    total_fare DECIMAL(10,2),
    passenger_names TEXT,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table saved_tickets created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>
