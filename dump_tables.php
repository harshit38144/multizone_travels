<?php
$_SERVER['HTTP_HOST'] = 'localhost';
include('admin/connection.php');
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo "TABLE: " . $row[0] . "\n";
    $res2 = $conn->query("DESCRIBE " . $row[0]);
    while($col = $res2->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
?>