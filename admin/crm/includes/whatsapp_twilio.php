<?php

/**
 * Twilio WhatsApp helpers (inbound webhook signature + phone parsing).
 */

require_once __DIR__ . '/whatsapp_config.php';

/**
 * Public URL Twilio used for this request (required for signature validation).
 */
function crmTwilioWebhookUrl(): string
{
    $configured = function_exists('crmTwilioConfiguredWebhookUrl') ? crmTwilioConfiguredWebhookUrl() : '';
    if ($configured !== '') {
        return $configured;
    }

    $proto = 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $proto = strtolower(trim($parts[0]));
    } elseif (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        $proto = 'https';
    } elseif (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        $proto = 'https';
    } else {
        $proto = (!empty($_SERVER['REQUEST_SCHEME']) ? (string) $_SERVER['REQUEST_SCHEME'] : 'http');
    }
    if ($proto !== 'https' && $proto !== 'http') {
        $proto = 'https';
    }

    $host = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST']);
        $host = trim($parts[0]);
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $host = (string) $_SERVER['HTTP_HOST'];
    } elseif (!empty($_SERVER['SERVER_NAME'])) {
        $host = (string) $_SERVER['SERVER_NAME'];
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    return $proto . '://' . $host . $uri;
}

/**
 * Validate X-Twilio-Signature for the current POST body.
 *
 * @param array<string, mixed> $params Typically $_POST
 */
function crmTwilioValidateRequest(string $authToken, string $url, array $params, string $signature): bool
{
    if ($authToken === '' || $signature === '' || $url === '') {
        return false;
    }

    ksort($params);
    $data = $url;
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        $data .= $key . $value;
    }

    $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

    return hash_equals($expected, $signature);
}

/**
 * Strip whatsapp: prefix and non-digits from Twilio From/To.
 */
function crmTwilioNormalizeWaAddress(string $raw): string
{
    $raw = trim($raw);
    if (stripos($raw, 'whatsapp:') === 0) {
        $raw = substr($raw, strlen('whatsapp:'));
    }
    $digits = preg_replace('/\D+/', '', $raw);

    return $digits !== null ? $digits : '';
}

function crmTwilioEmptyTwiml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<Response></Response>';
}

/**
 * Format E.164 digits as a Twilio WhatsApp address (whatsapp:+91…).
 */
function crmTwilioWhatsappAddress(string $phoneDigits): string
{
    $digits = preg_replace('/\D+/', '', $phoneDigits);
    if ($digits === null || $digits === '') {
        return '';
    }

    return 'whatsapp:+' . ltrim($digits, '+');
}

/**
 * @param array<string, string> $fields
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmTwilioMessagesCreate(array $fields, int $timeoutSeconds = 20): array
{
    $empty = [
        'ok' => false,
        'status' => 'failed',
        'http_status' => 0,
        'message_id' => '',
        'error' => '',
        'timed_out' => false,
        'response' => null,
    ];

    if (!crmTwilioIsConfigured()) {
        $empty['error'] = 'Twilio WhatsApp is not configured. Set Account SID, Auth Token, and WhatsApp From in whatsapp_config.local.php.';

        return $empty;
    }

    if (!function_exists('curl_init')) {
        $empty['error'] = 'PHP cURL is not available on this server.';

        return $empty;
    }

    $sid = crmTwilioAccountSid();
    $token = crmTwilioAuthToken();
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_USERPWD => $sid . ':' . $token,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => max(5, $timeoutSeconds),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);
    $raw = curl_exec($ch);
    $curlErrno = (int) curl_errno($ch);
    $curlError = (string) curl_error($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = $empty;
    $result['http_status'] = $httpStatus;

    $timedOut = $curlErrno === 28 || stripos($curlError, 'timed out') !== false || stripos($curlError, 'timeout') !== false;
    if ($curlErrno !== 0 || $raw === false) {
        $neverSent = in_array($curlErrno, [6, 7], true);
        $result['timed_out'] = $timedOut;
        $result['status'] = $neverSent ? 'failed' : 'unknown';
        $result['error'] = $timedOut
            ? 'The request timed out before Twilio confirmed the result. It was not retried.'
            : ($curlError !== '' ? 'Could not reach Twilio.' : 'Twilio returned an empty response.');

        return $result;
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        $result['status'] = $httpStatus >= 500 || $httpStatus === 0 ? 'unknown' : 'failed';
        $result['error'] = 'Twilio returned a response that was not JSON.';

        return $result;
    }

    // Never persist auth material if Twilio echoes anything sensitive.
    if (isset($decoded['account_sid'])) {
        $decoded['account_sid'] = '[redacted]';
    }
    $result['response'] = $decoded;

    $messageSid = '';
    if (!empty($decoded['sid']) && is_scalar($decoded['sid'])) {
        $messageSid = (string) $decoded['sid'];
        $result['message_id'] = $messageSid;
    }

    $providerError = '';
    if (!empty($decoded['message']) && is_string($decoded['message']) && $httpStatus >= 400) {
        $providerError = trim($decoded['message']);
    } elseif (!empty($decoded['error_message']) && is_string($decoded['error_message'])) {
        $providerError = trim($decoded['error_message']);
    }
    if (!empty($decoded['code']) && is_scalar($decoded['code']) && $httpStatus >= 400) {
        $providerError = trim(($providerError !== '' ? $providerError . ' ' : '') . '(code ' . $decoded['code'] . ')');
    }

    if (($httpStatus === 200 || $httpStatus === 201) && $messageSid !== '' && $providerError === '') {
        $result['ok'] = true;
        $result['status'] = 'accepted';
        $result['error'] = '';

        return $result;
    }

    if ($httpStatus >= 500 || $httpStatus === 0) {
        $result['status'] = 'unknown';
        $result['error'] = $providerError !== ''
            ? $providerError
            : 'Twilio did not confirm this send. It was not retried.';

        return $result;
    }

    $result['status'] = 'failed';
    $result['error'] = $providerError !== '' ? $providerError : 'Twilio rejected the message.';

    return $result;
}

/**
 * Free-form WhatsApp text (inside the 24-hour customer-care window).
 *
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmTwilioSendWhatsappText(string $toPhoneDigits, string $body): array
{
    $to = crmTwilioWhatsappAddress($toPhoneDigits);
    $from = crmTwilioWhatsappFrom();
    $body = trim($body);

    if ($to === '') {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'Enter a valid mobile number with country code.',
            'timed_out' => false,
            'response' => null,
        ];
    }
    if ($body === '') {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'Write a message before sending.',
            'timed_out' => false,
            'response' => null,
        ];
    }

    return crmTwilioMessagesCreate([
        'From' => $from,
        'To' => $to,
        'Body' => $body,
    ]);
}

/**
 * Business-initiated send via a Twilio Content Template (ContentSid).
 *
 * @param list<string> $variables Mapped to ContentVariables "1","2",…
 * @return array{ok:bool,status:string,http_status:int,message_id:string,error:string,timed_out:bool,response:array<string,mixed>|null}
 */
function crmTwilioSendWhatsappContent(string $toPhoneDigits, string $contentSid, array $variables): array
{
    $to = crmTwilioWhatsappAddress($toPhoneDigits);
    $from = crmTwilioWhatsappFrom();
    $contentSid = trim($contentSid);

    if ($to === '') {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'Enter a valid mobile number with country code.',
            'timed_out' => false,
            'response' => null,
        ];
    }
    if ($contentSid === '' || !preg_match('/^HX[0-9a-fA-F]+$/', $contentSid)) {
        return [
            'ok' => false,
            'status' => 'failed',
            'http_status' => 0,
            'message_id' => '',
            'error' => 'Set twilio_content_sid (HX…) in whatsapp_config.local.php for template sends.',
            'timed_out' => false,
            'response' => null,
        ];
    }

    $contentVariables = [];
    $keys = array_keys($variables);
    $isList = $keys === range(0, count($variables) - 1);
    if ($isList) {
        $i = 1;
        foreach ($variables as $variable) {
            $val = mb_substr(trim((string) $variable), 0, 1024);
            if ($val !== '') {
                $contentVariables[(string) $i] = $val;
            }
            $i++;
        }
    } else {
        foreach ($variables as $key => $variable) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }
            $contentVariables[$key] = mb_substr(trim((string) $variable), 0, 1024);
        }
    }

    // ContentSid only — do not send Body with templates (Twilio rejects / ignores).
    $fields = [
        'From' => $from,
        'To' => $to,
        'ContentSid' => $contentSid,
    ];
    // Only include ContentVariables when we have values (avoids error 21654 edge cases).
    if ($contentVariables !== []) {
        $fields['ContentVariables'] = json_encode($contentVariables, JSON_UNESCAPED_UNICODE);
    }

    return crmTwilioMessagesCreate($fields);
}
