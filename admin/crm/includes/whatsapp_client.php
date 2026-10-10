<?php

require_once __DIR__ . '/whatsapp_config.php';
require_once __DIR__ . '/whatsapp_db.php';
require_once __DIR__ . '/lead_db.php';
require_once dirname(__DIR__, 3) . '/includes/payment_helpers.php';

/**
 * RichAutomate API V1.
 * Base: https://richautomate.in/api/v1
 * Business-initiated sends use POST /send-template.
 * Free text uses POST /send-message and only works inside the 24-hour customer-service window.
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
        if (is_string($value) && preg_match('/\bra_(live|test)_[A-Za-z0-9]+\b/', $value)) {
            return '[redacted]';
        }

        return $value;
    }

    foreach ($value as $key => $item) {
        if (is_string($key) && preg_match('/api[_-]?key|authorization|secret|token|password/i', $key)) {
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
function crmWhatsappInterpretTransport(int $httpStatus, ?string $rawBody, int $curlErrno, string $curlError): array
{
    $result = [
        'ok' => false,
        'status' => 'failed',
        'http_status' => $httpStatus,
        'message_id' => '',
        'error' => '',
        'timed_out' => false,
        'response' => null,
    ];

    $timedOut = $curlErrno === 28 || stripos($curlError, 'timed out') !== false || stripos($curlError, 'timeout') !== false;
    if ($curlErrno !== 0 || $rawBody === null || $rawBody === false) {
        $neverSent = in_array($curlErrno, [6, 7], true);
        $result['timed_out'] = $timedOut;
        $result['status'] = $neverSent ? 'failed' : 'unknown';
        $result['error'] = $timedOut
            ? 'The request timed out before RichAutomate confirmed the result. It was not retried.'
            : ($curlError !== '' ? 'Could not reach RichAutomate.' : 'RichAutomate returned an empty response.');

        return $result;
    }

    $decoded = json_decode($rawBody, true);
    if (!is_array($decoded)) {
        $result['status'] = $httpStatus >= 500 || $httpStatus === 0 ? 'unknown' : 'failed';
        $result['error'] = 'RichAutomate returned a response that was not JSON.';

        return $result;
    }

    $result['response'] = crmWhatsappRedact($decoded);
    $messageId = $decoded['message_id'] ?? ($decoded['data']['message_id'] ?? ($decoded['id'] ?? ''));
    if (is_scalar($messageId) && (string) $messageId !== '') {
        $result['message_id'] = (string) $messageId;
    }

    $providerError = '';
    if (isset($decoded['error']) && is_string($decoded['error'])) {
        $providerError = trim($decoded['error']);
    } elseif (isset($decoded['message']) && is_string($decoded['message']) && empty($decoded['success'])) {
        $providerError = trim($decoded['message']);
    }

    $successFlag = array_key_exists('success', $decoded) ? (bool) $decoded['success'] : ($httpStatus === 200 || $httpStatus === 201);
    if (($httpStatus === 200 || $httpStatus === 201) && $successFlag && $providerError === '') {
        $result['ok'] = true;
        $result['status'] = 'accepted';
        $result['error'] = '';

        return $result;
    }

    if ($httpStatus === 409 || $httpStatus >= 500 || $httpStatus === 0) {
        $result['status'] = 'unknown';
        $result['error'] = $providerError !== ''
            ? $providerError
            : 'RichAutomate did not confirm this send. It was not retried.';

        return $result;
    }

    $result['status'] = 'failed';
    $result['error'] = $providerError !== '' ? $providerError : 'RichAutomate rejected the message.';

    return $result;
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmWhatsappRequest(string $path, array $payload, string $idempotencyKey, int $timeoutSeconds = 20, ?string $apiKey = null): array
{
    $apiKey = $apiKey === null ? crmWhatsappApiKey() : trim($apiKey);
    if ($apiKey === '') {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'RichAutomate API key is not configured.',
            'timed_out' => false,
            'response' => null,
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'PHP cURL is not available on this server.',
            'timed_out' => false,
            'response' => null,
        ];
    }

    $url = 'https://richautomate.in/api/v1/' . ltrim($path, '/');
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'Could not encode the WhatsApp request.',
            'timed_out' => false,
            'response' => null,
        ];
    }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => max(5, $timeoutSeconds),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $curlErrno = (int) curl_errno($ch);
    $curlError = (string) curl_error($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $rawBody = ($raw === false) ? null : (string) $raw;

    return crmWhatsappInterpretTransport($httpStatus, $rawBody, $curlErrno, $curlError);
}

/**
 * @param list<string> $variables
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmWhatsappSendTemplate(string $phone, string $template, string $language, array $variables, string $idempotencyKey, ?string $apiKey = null): array
{
    $phone = crmWhatsappNormalizePhone($phone);
    $template = trim($template);
    $language = $language === 'en' ? 'en' : ($language === 'en_US' ? 'en_US' : '');

    if ($phone === '') {
        return crmWhatsappLocalFailure('Enter a valid mobile number with country code.');
    }
    if (!preg_match('/^[a-z0-9_]{1,120}$/', $template)) {
        return crmWhatsappLocalFailure('The approved template name is missing or not valid.');
    }
    if ($language === '') {
        return crmWhatsappLocalFailure('Template language must be en or en_US, and it must match the Meta template exactly.');
    }

    $cleanVars = [];
    foreach ($variables as $variable) {
        $cleanVars[] = mb_substr(trim((string) $variable), 0, 1024);
    }
    if (count($cleanVars) > 10) {
        return crmWhatsappLocalFailure('A template can use at most 10 variables.');
    }

    return crmWhatsappRequest('send-template', [
        'phone' => $phone,
        'template' => $template,
        'language' => $language,
        'variables' => $cleanVars,
    ], $idempotencyKey, 20, $apiKey);
}

/**
 * Session text. WhatsApp only delivers this inside an open 24-hour customer-service window.
 *
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmWhatsappSendText(string $phone, string $message, string $idempotencyKey, ?string $apiKey = null): array
{
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

    return crmWhatsappRequest('send-message', [
        'phone' => $phone,
        'message' => $message,
        'type' => 'text',
    ], $idempotencyKey, 20, $apiKey);
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
        if (crmWhatsappApiKey() === '') {
            return crmWhatsappUserResult(false, 'RichAutomate API key is not configured.', 'failed', '', false);
        }
        $bodyText = $sessionMessage;
        $fingerprintPayload = $sessionMessage;
    } else {
        $templateName = crmWhatsappTemplateName();
        $language = crmWhatsappTemplateLanguage();
        if (crmWhatsappApiKey() === '') {
            return crmWhatsappUserResult(false, 'RichAutomate API key is not configured. Add it to whatsapp_config.local.php or CRM_RICHAUTOMATE_API_KEY.', 'failed', '', false);
        }
        if (!preg_match('/^[a-z0-9_]{1,120}$/', $templateName)) {
            return crmWhatsappUserResult(false, 'No approved WhatsApp template is configured. Open WhatsApp still works on this computer.', 'failed', '', false);
        }
        foreach (crmWhatsappTemplateVariableTokens() as $token) {
            $variables[] = $values[$token];
        }
        $bodyText = 'Template ' . $templateName . ' (' . $language . ')';
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
            'channel' => $mode,
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
            $encoded = json_encode($api['response'], JSON_UNESCAPED_UNICODE);
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
                : 'WhatsApp accepted template ' . $templateName . '.';
            if ((string) $api['message_id'] !== '') {
                $detail .= ' Message ID ' . $api['message_id'] . '.';
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
