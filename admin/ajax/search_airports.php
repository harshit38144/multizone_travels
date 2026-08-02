<?php
header('Content-Type: application/json');
include '../connection.php';

$q = $_GET['q'] ?? '';

if (!$q) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
SELECT name,city,country,iata
FROM airports
WHERE iata LIKE CONCAT('%',?,'%')
OR city LIKE CONCAT('%',?,'%')
OR name LIKE CONCAT('%',?,'%')
LIMIT 10
");

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param("sss", $q, $q, $q);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $row['flag'] = strtolower(substr($row['country'], 0, 2));
    $data[] = $row;
}

echo json_encode($data);