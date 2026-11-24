<?php

/**
 * Local checks for the Twilio WhatsApp client.
 * Does not message a lead.
 * A live send happens only when both --phone= and --confirm-send=yes are passed,
 * and only to that phone.
 *
 * Usage:
 *   php admin/crm/scripts/whatsapp_api_check.php
 *   php admin/crm/scripts/whatsapp_api_check.php --prepare-db
 *   php admin/crm/scripts/whatsapp_api_check.php --phone=91XXXXXXXXXX --confirm-send=yes --mode=session
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['role'] = '1';

require_once dirname(__DIR__) . '/includes/whatsapp_client.php';

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--') !== 0) {
        continue;
    }
    $arg = substr($arg, 2);
    $parts = explode('=', $arg, 2);
    $args[$parts[0]] = $parts[1] ?? '1';
}

$failed = 0;

function waCheck(string $name, bool $ok, string $detail = ''): void
{
    global $failed;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $name;
    if ($detail !== '') {
        echo ' — ' . $detail;
    }
    echo PHP_EOL;
}

waCheck('rejects a short phone', crmWhatsappNormalizePhone('12345') === '');
waCheck('prefixes a 10-digit India number', crmWhatsappNormalizePhone('9876543210') === '919876543210');
waCheck('keeps a country-code number', crmWhatsappNormalizePhone('+91 98765 43210') === '919876543210');

$invalidPhone = crmWhatsappSendText('abc', 'Hello');
waCheck('invalid number does not call the API', ($invalidPhone['status'] ?? '') === 'failed' && ($invalidPhone['http_status'] ?? -1) === 0, (string) ($invalidPhone['error'] ?? ''));

$emptyText = crmWhatsappSendText('919876543210', '   ');
waCheck('empty text is rejected', ($emptyText['status'] ?? '') === 'failed');

$badTemplate = crmWhatsappSendTemplate('919876543210', '', 'en_US', []);
waCheck('missing ContentSid is rejected', ($badTemplate['status'] ?? '') === 'failed');

$redacted = crmWhatsappRedact(['auth_token' => 'secret', 'nested' => ['authorization' => 'Bearer x']]);
waCheck('redaction removes key fields', ($redacted['auth_token'] ?? '') === '[redacted]' && ($redacted['nested']['authorization'] ?? '') === '[redacted]');

$status = crmWhatsappPublicStatus();
waCheck(
    'public status has no secret fields',
    !array_key_exists('twilio_auth_token', $status)
        && !array_key_exists('richautomate_api_key', $status)
        && is_bool($status['key_configured'] ?? null)
);
echo 'INFO  Twilio configured: ' . (!empty($status['twilio_configured']) ? 'yes' : 'no') . PHP_EOL;
echo 'INFO  can send: ' . (!empty($status['can_send']) ? 'yes' : 'no') . PHP_EOL;
echo 'INFO  content sid: ' . (($status['template_name'] ?? '') !== '' ? $status['template_name'] : 'not set') . PHP_EOL;

if (!crmTwilioIsConfigured()) {
    $missingKey = crmWhatsappSendText('919876543210', 'Hello');
    waCheck(
        'missing Twilio config stops before the network',
        ($missingKey['ok'] ?? true) === false && strpos((string) ($missingKey['error'] ?? ''), 'not configured') !== false,
        (string) ($missingKey['error'] ?? '')
    );
}

if (!empty($args['prepare-db'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
    require_once dirname(__DIR__, 2) . '/connection.php';
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        waCheck('database connection', false, 'could not connect');
    } else {
        waCheck('message log table', crmWhatsappEnsureTables($conn));
        $conn->begin_transaction();
        $logId = crmWhatsappInsertPending($conn, [
            'lead_id' => 0,
            'phone' => '919876543210',
            'channel' => 'twilio',
            'template_name' => '',
            'template_language' => '',
            'body_text' => 'rollback check',
            'variables_json' => '',
            'fingerprint' => hash('sha256', 'rollback-check'),
            'idempotency_key' => 'rollback-' . bin2hex(random_bytes(8)),
            'created_by_id' => 0,
            'created_by_name' => 'API check',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($logId > 0) {
            crmWhatsappUpdateResult($conn, $logId, 'failed', 422, '', 'rollback check', '');
        }
        $conn->rollback();
        $left = $conn->query("SELECT COUNT(*) AS c FROM `crm_whatsapp_messages` WHERE `idempotency_key` LIKE 'rollback-%'");
        $leftCount = 0;
        if ($left) {
            $leftRow = $left->fetch_assoc();
            $leftCount = (int) ($leftRow['c'] ?? 0);
        }
        waCheck('log insert rolls back without keeping a message', $logId > 0 && $leftCount === 0, 'rows left ' . $leftCount);
    }
}

$phone = preg_replace('/\D+/', '', (string) ($args['phone'] ?? '')) ?? '';
$confirm = (string) ($args['confirm-send'] ?? '');
if ($phone !== '' || $confirm !== '') {
    if ($confirm !== 'yes' || crmWhatsappNormalizePhone($phone) === '') {
        waCheck('live send', false, 'Refused. Pass --phone=91XXXXXXXXXX and --confirm-send=yes to message that number only.');
    } elseif (!crmTwilioIsConfigured()) {
        waCheck('live send', false, 'Refused. Twilio is not configured.');
    } else {
        $mode = (string) ($args['mode'] ?? 'session');
        if ($mode === 'template') {
            $live = crmWhatsappSendTemplate($phone, crmTwilioContentSid(), 'en_US', ['Guest', 'Team', 'Multi Zone Travels']);
        } else {
            $text = (string) ($args['text'] ?? 'Multizone Travels Twilio API check');
            $live = crmWhatsappSendText($phone, $text);
        }
        $detail = (string) ($live['error'] ?? '');
        if (!empty($live['ok'])) {
            $detail = 'accepted message_id=' . (string) ($live['message_id'] ?? '');
        }
        waCheck('live send', !empty($live['ok']), $detail . ' http=' . (string) ($live['http_status'] ?? 0) . ' status=' . (string) ($live['status'] ?? ''));
    }
}

echo $failed === 0 ? "OK  {$failed} failed" . PHP_EOL : "DONE  {$failed} failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
