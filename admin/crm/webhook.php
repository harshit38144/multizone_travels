<?php

/**
 * Twilio WhatsApp inbound webhook.
 *
 * Configure this URL in the Twilio Console → Messaging → WhatsApp sandbox / sender
 * → “A message comes in” webhook (HTTP POST).
 *
 * Does not require an admin session. Authenticity is enforced via X-Twilio-Signature.
 */

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/connection.php';
require_once __DIR__ . '/includes/whatsapp_config.php';
require_once __DIR__ . '/includes/whatsapp_db.php';
require_once __DIR__ . '/includes/whatsapp_twilio.php';
require_once __DIR__ . '/includes/lead_db.php';
require_once dirname(__DIR__, 2) . '/includes/payment_helpers.php';

// Normalize helper lives on the RichAutomate client; load only that function’s file deps lightly.
if (!function_exists('crmWhatsappNormalizePhone')) {
    require_once __DIR__ . '/includes/whatsapp_client.php';
}

function crmWaWebhookRespondTwiml(int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    header('Content-Type: text/xml; charset=utf-8');
    echo crmTwilioEmptyTwiml();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method Not Allowed';
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    crmWaWebhookRespondTwiml(500);
}

$authToken = crmTwilioAuthToken();
$signature = (string) ($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');

if (crmTwilioValidateSignatureEnabled()) {
    if ($authToken === '') {
        // Misconfigured — refuse so Twilio retries / ops notice; do not accept unsigned traffic.
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Twilio auth token not configured';
        exit;
    }
    $url = crmTwilioWebhookUrl();
    if (!crmTwilioValidateRequest($authToken, $url, $_POST, $signature)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid Twilio signature';
        exit;
    }
}

$messageSid = trim((string) ($_POST['MessageSid'] ?? $_POST['SmsMessageSid'] ?? ''));
$fromRaw = (string) ($_POST['From'] ?? '');
$body = (string) ($_POST['Body'] ?? '');
$numMedia = (int) ($_POST['NumMedia'] ?? 0);

// Ignore non-message callbacks (status callbacks without Body/MessageSid pattern).
if ($messageSid === '') {
    crmWaWebhookRespondTwiml(200);
}

$senderPhone = crmTwilioNormalizeWaAddress($fromRaw);
if ($senderPhone === '') {
    crmWaWebhookRespondTwiml(200);
}

// Optional media-only: keep a short placeholder so the chat still shows activity.
$bodyTrim = trim($body);
if ($bodyTrim === '' && $numMedia > 0) {
    $bodyTrim = '[Media message]';
}

$leadId = crmWhatsappFindLeadIdByPhone($conn, $senderPhone);
if ($leadId <= 0) {
    // Unknown number — acknowledge Twilio; do not invent a lead.
    crmWaWebhookRespondTwiml(200);
}

$createdAt = date('Y-m-d H:i:s');
$payload = [
    'MessageSid' => $messageSid,
    'From' => $fromRaw,
    'To' => (string) ($_POST['To'] ?? ''),
    'Body' => $body,
    'NumMedia' => $numMedia,
    'ProfileName' => (string) ($_POST['ProfileName'] ?? ''),
    'WaId' => (string) ($_POST['WaId'] ?? ''),
    'SmsStatus' => (string) ($_POST['SmsStatus'] ?? ''),
    'AccountSid' => (string) ($_POST['AccountSid'] ?? ''),
];

crmWhatsappInsertInbound($conn, [
    'lead_id' => $leadId,
    'phone' => $senderPhone,
    'body_text' => $bodyTrim,
    'provider_message_id' => $messageSid,
    'created_at' => $createdAt,
    'provider_response_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

crmWaWebhookRespondTwiml(200);
