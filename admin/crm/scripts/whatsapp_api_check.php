<?php

/**
 * Local checks for the RichAutomate client.
 * Does not message a lead.
 * A live send happens only when both --phone= and --confirm-send=yes are passed,
 * and only to that phone.
 *
 * Usage:
 *   php admin/crm/scripts/whatsapp_api_check.php
 *   php admin/crm/scripts/whatsapp_api_check.php --prepare-db
 *   php admin/crm/scripts/whatsapp_api_check.php --phone=91XXXXXXXXXX --confirm-send=yes --mode=template
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

$invalidPhone = crmWhatsappSendText('abc', 'Hello', 'test-invalid-phone', '');
waCheck('invalid number does not call the API', ($invalidPhone['status'] ?? '') === 'failed' && ($invalidPhone['http_status'] ?? -1) === 0, (string) ($invalidPhone['error'] ?? ''));

$missingKey = crmWhatsappSendText('919876543210', 'Hello', 'test-missing-key', '');
waCheck('missing key stops before the network', ($missingKey['ok'] ?? true) === false && strpos((string) ($missingKey['error'] ?? ''), 'not configured') !== false, (string) ($missingKey['error'] ?? ''));

$emptyText = crmWhatsappSendText('919876543210', '   ', 'test-empty', '');
waCheck('empty text is rejected', ($emptyText['status'] ?? '') === 'failed');

$badTemplate = crmWhatsappSendTemplate('919876543210', '', 'en_US', [], 'test-template', '');
waCheck('missing template name is rejected', ($badTemplate['status'] ?? '') === 'failed');

$unauth = crmWhatsappInterpretTransport(401, '{"success":false,"error":"Invalid or suspended API key."}', 0, '');
waCheck('401 is a failed send', ($unauth['status'] ?? '') === 'failed' && ($unauth['ok'] ?? true) === false, (string) ($unauth['error'] ?? ''));

$invalid = crmWhatsappInterpretTransport(422, '{"success":false,"error":"The phone field is invalid."}', 0, '');
waCheck('422 is a failed send', ($invalid['status'] ?? '') === 'failed');

$accepted = crmWhatsappInterpretTransport(200, '{"success":true,"message_id":123456,"status":"queued"}', 0, '');
waCheck('200 stores the provider message id', ($accepted['ok'] ?? false) === true && ($accepted['message_id'] ?? '') === '123456');

$timeout = crmWhatsappInterpretTransport(0, null, 28, 'Operation timed out');
waCheck('timeout is unknown and not a success', ($timeout['status'] ?? '') === 'unknown' && ($timeout['timed_out'] ?? false) === true && ($timeout['ok'] ?? true) === false);

$serverError = crmWhatsappInterpretTransport(502, '{"success":false,"error":"upstream"}', 0, '');
waCheck('502 stays unknown so it is not retried automatically', ($serverError['status'] ?? '') === 'unknown');

if (function_exists('curl_init')) {
    $ch = curl_init('http://192.0.2.1:81/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $errno = (int) curl_errno($ch);
    curl_close($ch);
    $fromNetwork = crmWhatsappInterpretTransport(0, null, $errno === 0 ? 28 : $errno, $errno === 28 || $errno === 0 ? 'timed out' : 'connect failed');
    $status = (string) ($fromNetwork['status'] ?? '');
    waCheck(
        'a dead network is not reported as delivered',
        $errno !== 0 && ($fromNetwork['ok'] ?? true) === false && ($status === 'unknown' || $status === 'failed'),
        'curl errno ' . $errno . ' status ' . $status
    );
}

$redacted = crmWhatsappRedact(['api_key' => 'ra_live_secret', 'nested' => ['authorization' => 'Bearer x']]);
waCheck('redaction removes key fields', ($redacted['api_key'] ?? '') === '[redacted]' && ($redacted['nested']['authorization'] ?? '') === '[redacted]');

$status = crmWhatsappPublicStatus();
waCheck(
    'public status has no key value',
    !array_key_exists('richautomate_api_key', $status) && is_bool($status['key_configured'] ?? null)
);
echo 'INFO  API key configured: ' . (!empty($status['key_configured']) ? 'yes' : 'no') . PHP_EOL;
echo 'INFO  template: ' . (($status['template_name'] ?? '') !== '' ? $status['template_name'] . ' (' . $status['template_language'] . ')' : 'not set') . PHP_EOL;

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
            'channel' => 'template',
            'template_name' => 'example_template',
            'template_language' => 'en_US',
            'body_text' => 'Template example_template (en_US)',
            'variables_json' => '["Guest"]',
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
    } elseif (crmWhatsappApiKey() === '') {
        waCheck('live send', false, 'Refused. API key is not configured.');
    } else {
        $mode = (string) ($args['mode'] ?? 'template');
        $idem = 'manual-test-' . bin2hex(random_bytes(8));
        if ($mode === 'session') {
            $text = (string) ($args['text'] ?? 'Multizone Travels API check');
            $live = crmWhatsappSendText($phone, $text, $idem);
        } else {
            $template = crmWhatsappTemplateName();
            $language = crmWhatsappTemplateLanguage();
            $live = crmWhatsappSendTemplate($phone, $template, $language, ['Guest', 'Team', 'Multi Zone Travels'], $idem);
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
