<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != '1') {
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
    );
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expired. Please sign in again.']);
        exit;
    }
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/includes/mail_db.php';
require_once __DIR__ . '/includes/mail_service.php';

mailEnsureTables($conn);

/** Release session lock so other admin pages can load during long mail requests. */
function mailReleaseSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
