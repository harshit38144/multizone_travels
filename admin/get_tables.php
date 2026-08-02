<?php
$conn = new mysqli("localhost", "root", "", "db_multizone");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()){
    echo "TABLE: " . $row[0] . "\n";
    $cRes = $conn->query("DESCRIBE `" . $row[0] . "`");
    while($cRow = $cRes->fetch_assoc()){
        echo "  " . $cRow['Field'] . " (" . $cRow['Type'] . ")\n";
    }
}
?>
