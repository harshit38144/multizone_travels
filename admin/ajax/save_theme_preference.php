<?php
session_start();
include __DIR__ . '/../connection.php';
require_once __DIR__ . '/../includes/admin_theme.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['role']) || (string) $_SESSION['role'] !== '1') {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please sign in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$adminId = (int) ($_SESSION['id'] ?? 0);
$theme = adminThemeSave($conn, $adminId, (string) ($_POST['theme'] ?? 'light'));

echo json_encode([
    'success' => true,
    'theme' => $theme,
    'message' => 'Theme saved.',
], JSON_UNESCAPED_UNICODE);
