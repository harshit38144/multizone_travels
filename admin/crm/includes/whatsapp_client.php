<?php

require_once __DIR__ . '/whatsapp_config.php';
require_once __DIR__ . '/whatsapp_db.php';
require_once __DIR__ . '/whatsapp_twilio.php';
require_once __DIR__ . '/lead_db.php';
require_once dirname(__DIR__, 3) . '/includes/payment_helpers.php';

/**
 * Twilio WhatsApp client.
 * Session text uses Messages API Body (24-hour window).
 * Business-initiated template sends use ContentSid when configured.
 */

function crmWhatsappNormalizePhone(string $mobile): string
{
    $digits = payment_whatsapp_phone($mobile);
    if (!preg_match('/^[1-9][0-9]{10,14}$/', $digits)) {
        return '';
    }

    return $digits;
}

function crmWhatsappFingerprint(string $channel, string $phone, string $template, string $language, string $payload): string
{
    return hash('sha256', $channel . '|' . $phone . '|' . $template . '|' . $language . '|' . $payload);
}

/**
 * @param mixed $value
 * @return mixed
 */
function crmWhatsappRedact($value)
{
    if (!is_array($value)) {
        if (is_string($value) && preg_match('/\b(SK|AC)[0-9a-fA-F]{20,}\b/', $value)) {
            return '[redacted]';
        }

        return $value;
    }

    foreach ($value as $key => $item) {
        if (is_string($key) && preg_match('/api[_-]?key|authorization|secret|token|password|auth/i', $key)) {
            $value[$key] = '[redacted]';
            continue;
        }
        $value[$key] = crmWhatsappRedact($item);
    }

    return $value;
}

/**
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmWhatsappLocalFailure(string $error): array
{
    return [
        'ok' => false,
        'status' => 'failed',
        'http_status' => 0,
        'message_id' => '',
        'error' => $error,
        'timed_out' => false,
        'response' => null,
    ];
}

/**
 * Session text via Twilio. Only delivers inside an open WhatsApp customer-care window.
 *
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmWhatsappSendText(string $phone, string $message, string $idempotencyKey = '', ?string $unused = null): array
{
    unset($idempotencyKey, $unused);
    $phone = crmWhatsappNormalizePhone($phone);
    $message = trim($message);

    if ($phone === '') {
        return crmWhatsappLocalFailure('Enter a valid mobile number with country code.');
    }
    if ($message === '') {
        return crmWhatsappLocalFailure('Write a message before sending.');
    }
    if (mb_strlen($message) > 4096) {
        return crmWhatsappLocalFailure('The message is longer than WhatsApp allows.');
    }

    return crmTwilioSendWhatsappText($phone, $message);
}

/**
 * Business-initiated Content Template via Twilio.
 *
 * @param list<string> $variables
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmWhatsappSendTemplate(string $phone, string $template, string $language, array $variables, string $idempotencyKey = '', ?string $unused = null): array
{
    unset($language, $idempotencyKey, $unused);
    $phone = crmWhatsappNormalizePhone($phone);
    $template = trim($template);

    if ($phone === '') {
        return crmWhatsappLocalFailure('Enter a valid mobile number with country code.');
    }

    $contentSid = crmTwilioContentSid() !== '' ? crmTwilioContentSid() : $template;
    if ($contentSid === '' || !preg_match('/^HX[0-9a-fA-F]+$/', $contentSid)) {
        return crmWhatsappLocalFailure('Set twilio_content_sid (HX…) in whatsapp_config.local.php for template sends.');
    }

    $cleanVars = [];
    foreach ($variables as $variable) {
        $cleanVars[] = mb_substr(trim((string) $variable), 0, 1024);
    }
    if (count($cleanVars) > 10) {
        return crmWhatsappLocalFailure('A template can use at most 10 variables.');
    }

    return crmTwilioSendWhatsappContent($phone, $contentSid, $cleanVars);
}

/**
 * Manual send for one CRM lead. Does not send unless this function is called.
 *
 * @return array{success:bool,message:string,status:string,provider_message_id:string,needs_confirm:bool}
 */
function crmWhatsappSendLeadManual(
    mysqli $conn,
    int $leadId,
    string $mode,
    string $sessionMessage,
    bool $confirmResend,
    int $adminId,
    string $adminName
): array {
    $mode = $mode === 'session' ? 'session' : 'template';
    if ($leadId <= 0) {
        return crmWhatsappUserResult(false, 'Choose a lead first.', 'failed', '', false);
    }

    $lead = crmLeadFetchById($conn, $leadId, false);
    if (!$lead) {
        return crmWhatsappUserResult(false, 'That lead could not be found.', 'failed', '', false);
    }

    $phone = crmWhatsappNormalizePhone((string) ($lead['customer_phone'] ?? ''));
    if ($phone === '') {
        return crmWhatsappUserResult(false, 'This lead does not have a valid mobile number.', 'failed', '', false);
    }

    if (!crmTwilioIsConfigured()) {
        return crmWhatsappUserResult(
            false,
            'Twilio WhatsApp is not configured. Add Account SID, Auth Token, and WhatsApp From to whatsapp_config.local.php on this server.',
            'failed',
            '',
            false
        );
    }

    $guest = trim((string) ($lead['customer_name'] ?? ''));
    $guestFirst = $guest === '' ? 'Guest' : preg_split('/\s+/', $guest)[0];
    $agent = trim($adminName) !== '' ? trim($adminName) : 'Team';
    $company = 'Multi Zone Travels';
    $values = [
        'guest' => $guestFirst,
        'agent' => $agent,
        'company' => $company,
    ];

    $templateName = '';
    $language = '';
    $variables = [];
    $bodyText = '';
    $fingerprintPayload = '';

    if ($mode === 'session') {
        $sessionMessage = trim($sessionMessage);
        if ($sessionMessage === '') {
            return crmWhatsappUserResult(false, 'Select or write a message before sending.', 'failed', '', false);
        }
        if (mb_strlen($sessionMessage) > 4096) {
            return crmWhatsappUserResult(false, 'The message is longer than WhatsApp allows.', 'failed', '', false);
        }
        $bodyText = $sessionMessage;
        $fingerprintPayload = $sessionMessage;
    } else {
        $templateName = crmTwilioContentSid();
        $language = crmWhatsappTemplateLanguage();
        if ($templateName === '' || !preg_match('/^HX[0-9a-fA-F]+$/', $templateName)) {
            return crmWhatsappUserResult(
                false,
                'No Twilio Content template is configured. Set twilio_content_sid in whatsapp_config.local.php, or reply in the chat after the guest messages you.',
                'failed',
                '',
                false
            );
        }
        foreach (crmWhatsappTemplateVariableTokens() as $token) {
            $variables[] = $values[$token];
        }
        $bodyText = 'Template ' . $templateName;
        $fingerprintPayload = json_encode($variables, JSON_UNESCAPED_UNICODE) ?: '';
    }

    $fingerprint = crmWhatsappFingerprint($mode, $phone, $templateName, $language, $fingerprintPayload);

    if (!crmWhatsappEnsureTables($conn)) {
        return crmWhatsappUserResult(false, 'Could not prepare the WhatsApp log.', 'failed', '', false);
    }
    if (!crmWhatsappAcquireLeadLock($conn, $leadId)) {
        return crmWhatsappUserResult(false, 'Another WhatsApp send for this lead is already running.', 'pending', '', false);
    }

    try {
        $recent = crmWhatsappFindRecentSend($conn, $leadId, $fingerprint);
        if ($recent) {
            $recentStatus = (string) ($recent['status'] ?? '');
            $recentId = (string) ($recent['provider_message_id'] ?? '');
            if ($recentStatus === 'pending' || $recentStatus === 'unknown') {
                return crmWhatsappUserResult(
                    false,
                    'A previous send to this number has no confirmed result. It was not sent again.',
                    $recentStatus,
                    $recentId,
                    false
                );
            }
            if ($recentStatus === 'accepted' && !$confirmResend) {
                $when = (string) ($recent['created_at'] ?? '');
                return crmWhatsappUserResult(
                    false,
                    'This message was already accepted for this guest' . ($when !== '' ? ' at ' . $when : '') . '. Send it again only if you mean to.',
                    'accepted',
                    $recentId,
                    true
                );
            }
        }

        $idempotencyKey = crmWhatsappNewIdempotencyKey($leadId);
        $logId = crmWhatsappInsertPending($conn, [
            'lead_id' => $leadId,
            'phone' => $phone,
            'channel' => $mode === 'session' ? 'twilio' : 'twilio_template',
            'template_name' => $templateName,
            'template_language' => $language,
            'body_text' => $bodyText,
            'variables_json' => $mode === 'template' ? json_encode($variables, JSON_UNESCAPED_UNICODE) : '',
            'fingerprint' => $fingerprint,
            'idempotency_key' => $idempotencyKey,
            'created_by_id' => $adminId,
            'created_by_name' => $adminName,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($logId <= 0) {
            return crmWhatsappUserResult(false, 'Could not record this send, so it was not sent.', 'failed', '', false);
        }

        if ($mode === 'session') {
            $api = crmWhatsappSendText($phone, $sessionMessage, $idempotencyKey);
        } else {
            $api = crmWhatsappSendTemplate($phone, $templateName, $language, $variables, $idempotencyKey);
        }

        $storedResponse = '';
        if (is_array($api['response'])) {
            $encoded = json_encode(crmWhatsappRedact($api['response']), JSON_UNESCAPED_UNICODE);
            $storedResponse = $encoded === false ? '' : mb_substr($encoded, 0, 4000);
        }
        crmWhatsappUpdateResult(
            $conn,
            $logId,
            (string) $api['status'],
            (int) $api['http_status'] > 0 ? (int) $api['http_status'] : null,
            (string) $api['message_id'],
            mb_substr((string) $api['error'], 0, 500),
            $storedResponse
        );

        if (!empty($api['ok'])) {
            $detail = $mode === 'session'
                ? 'WhatsApp accepted the text message.'
                : 'WhatsApp accepted the template message.';
            if ((string) $api['message_id'] !== '') {
                $detail .= ' Message SID ' . $api['message_id'] . '.';
            }
            if ($mode === 'session') {
                $detail .= ' This only arrives if the guest messaged you in the last 24 hours.';
            }

            return crmWhatsappUserResult(true, $detail, 'accepted', (string) $api['message_id'], false);
        }

        $error = (string) ($api['error'] !== '' ? $api['error'] : 'WhatsApp did not accept the message.');
        if (($api['status'] ?? '') === 'unknown') {
            $error .= ' Nothing was retried.';
        }

        return crmWhatsappUserResult(false, $error, (string) $api['status'], (string) $api['message_id'], false);
    } finally {
        crmWhatsappReleaseLeadLock($conn, $leadId);
    }
}

/**
 * @return array{success:bool,message:string,status:string,provider_message_id:string,needs_confirm:bool}
 */
function crmWhatsappUserResult(bool $success, string $message, string $status, string $providerMessageId, bool $needsConfirm): array
{
    return [
        'success' => $success,
        'message' => $message,
        'status' => $status,
        'provider_message_id' => $providerMessageId,
        'needs_confirm' => $needsConfirm,
    ];
}
