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
