<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/whatsapp_client.php';

header('Content-Type: application/json; charset=utf-8');

function sendWhatsappJson(array $result): void
{
    echo json_encode([
        'success' => (bool) ($result['success'] ?? false),
        'message' => (string) ($result['message'] ?? ''),
        'status' => (string) ($result['status'] ?? ''),
        'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
        'needs_confirm' => !empty($result['needs_confirm']),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendWhatsappJson([
        'success' => false,
        'message' => 'Invalid request method.',
        'status' => 'failed',
    ]);
}

$leadId = (int) ($_POST['lead_id'] ?? 0);
$mode = (string) ($_POST['mode'] ?? 'template');
$message = (string) ($_POST['message'] ?? '');
$confirmResend = (string) ($_POST['confirm_resend'] ?? '') === '1';
$adminId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$adminName = trim((string) ($_SESSION['name'] ?? ''));

sendWhatsappJson(crmWhatsappSendLeadManual(
    $conn,
    $leadId,
    $mode,
    $message,
    $confirmResend,
    $adminId,
    $adminName
));
