<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['image'];
if (!empty($file['error'])) {
    echo json_encode(['success' => false, 'message' => 'Upload error.']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Image must be under 5MB.']);
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} else {
    $mime = $file['type'] ?? '';
}

if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF or WEBP images are allowed.']);
    exit;
}

$ext = $allowed[$mime];
$uploadDir = __DIR__ . '/../../uploads/quotations/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$filename = 'qt_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'message' => 'Could not save uploaded image.']);
    exit;
}

// URL relative to the admin root (pages use <base href="../">).
echo json_encode(['success' => true, 'url' => 'uploads/quotations/' . $filename]);
