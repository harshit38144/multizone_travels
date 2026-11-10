<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/whatsapp_client.php';
require_once __DIR__ . '/../includes/lead_db.php';

header('Content-Type: application/json; charset=utf-8');

function waChatJson(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function waChatFormatMessage(array $row): array
{
    $direction = strtolower((string) ($row['direction'] ?? 'outbound'));
    if ($direction !== 'inbound') {
        $direction = 'outbound';
    }
    $body = trim((string) ($row['body_text'] ?? ''));
    $template = trim((string) ($row['template_name'] ?? ''));
    if ($body === '' && $template !== '') {
        $body = 'Template: ' . $template;
    }
    $createdAt = (string) ($row['created_at'] ?? '');
    $ts = $createdAt !== '' ? strtotime($createdAt) : false;

    return [
        'id' => (int) ($row['id'] ?? 0),
        'direction' => $direction,
        'body' => $body,
        'status' => (string) ($row['status'] ?? ''),
        'channel' => (string) ($row['channel'] ?? ''),
        'error' => (string) ($row['error_message'] ?? ''),
        'created_by' => (string) ($row['created_by_name'] ?? ''),
        'created_at' => $createdAt,
        'time_label' => $ts ? date('h:i A', $ts) : '',
        'date_label' => $ts ? date('d M Y', $ts) : '',
    ];
}

$action = (string) ($_REQUEST['action'] ?? 'list');
$leadId = (int) ($_REQUEST['lead_id'] ?? 0);

if ($leadId <= 0) {
    waChatJson(['success' => false, 'message' => 'Invalid lead.']);
}

$lead = crmLeadFetchById($conn, $leadId, false);
if (!$lead) {
    waChatJson(['success' => false, 'message' => 'Lead not found.']);
}

$guestName = trim((string) ($lead['customer_name'] ?? ''));
$guestPhone = trim((string) ($lead['customer_phone'] ?? ''));
$letters = preg_replace('/[^A-Za-z]/', '', $guestName);
$initials = strtoupper(substr((string) $letters, 0, 2));
$phoneDigits = crmWhatsappNormalizePhone($guestPhone);
$destination = '';
$payloadRaw = (string) ($lead['payload_json'] ?? '');
if ($payloadRaw !== '') {
    $payload = json_decode($payloadRaw, true);
    if (is_array($payload)) {
        $destination = trim((string) ($payload['destination'] ?? $payload['travel_destination'] ?? $payload['dest'] ?? ''));
    }
}

$leadPayload = [
    'id' => (int) $lead['id'],
    'lead_uid' => (string) ($lead['lead_uid'] ?? ''),
    'name' => $guestName !== '' ? $guestName : 'Guest',
    'phone' => $guestPhone,
    'phone_digits' => $phoneDigits,
    'email' => (string) ($lead['customer_email'] ?? ''),
    'initials' => $initials !== '' ? $initials : 'G',
    'destination' => $destination,
];

if ($action === 'list' || $action === 'thread') {
    $messages = array_map('waChatFormatMessage', crmWhatsappListLeadMessages($conn, $leadId, 300));
    waChatJson([
        'success' => true,
        'message' => 'OK',
        'lead' => $leadPayload,
        'messages' => $messages,
        'api' => crmWhatsappPublicStatus(),
    ]);
}

if ($action === 'send') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        waChatJson(['success' => false, 'message' => 'Invalid request method.']);
    }

    $text = trim((string) ($_POST['message'] ?? ''));
    $confirmResend = (string) ($_POST['confirm_resend'] ?? '') === '1';
    $adminId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
    $adminName = trim((string) ($_SESSION['name'] ?? ''));

    $result = crmWhatsappSendLeadManual(
        $conn,
        $leadId,
        'session',
        $text,
        $confirmResend,
        $adminId,
        $adminName
    );

    $messages = array_map('waChatFormatMessage', crmWhatsappListLeadMessages($conn, $leadId, 300));
    waChatJson([
        'success' => (bool) ($result['success'] ?? false),
        'message' => (string) ($result['message'] ?? ''),
        'status' => (string) ($result['status'] ?? ''),
        'needs_confirm' => !empty($result['needs_confirm']),
        'provider_message_id' => (string) ($result['provider_message_id'] ?? ''),
        'lead' => $leadPayload,
        'messages' => $messages,
        'sent_text' => $text,
    ]);
}

waChatJson(['success' => false, 'message' => 'Unknown action.']);
