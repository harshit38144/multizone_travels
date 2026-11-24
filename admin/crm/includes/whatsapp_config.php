<?php

/**
 * Twilio WhatsApp settings (send + receive).
 *
 * Option 1 — copy whatsapp_config.local.example.php to whatsapp_config.local.php
 * Option 2 — set CRM_TWILIO_ACCOUNT_SID / CRM_TWILIO_AUTH_TOKEN / CRM_TWILIO_WHATSAPP_FROM
 *
 * Secrets stay on the server. Do not print them or send them to the browser.
 */

function crmWhatsappSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'twilio_account_sid' => '',
        'twilio_auth_token' => '',
        'twilio_whatsapp_from' => '',
        'twilio_content_sid' => '',
        'twilio_validate_signature' => true,
        'twilio_webhook_url' => '',
        // Optional Content Template variable order for business-initiated sends.
        'template_variables' => ['guest', 'agent', 'company'],
        'template_name' => '',
        'template_language' => 'en_US',
    ];

    $localFile = __DIR__ . '/whatsapp_config.local.php';
    if (is_file($localFile)) {
        $cfg = include $localFile;
        if (is_array($cfg)) {
            $settings = array_merge($settings, $cfg);
        }
    }

    $settings['twilio_account_sid'] = trim((string) ($settings['twilio_account_sid'] ?? ''));
    $settings['twilio_auth_token'] = trim((string) ($settings['twilio_auth_token'] ?? ''));
    $settings['twilio_whatsapp_from'] = trim((string) ($settings['twilio_whatsapp_from'] ?? ''));
    $settings['twilio_content_sid'] = trim((string) ($settings['twilio_content_sid'] ?? ''));
    $settings['twilio_webhook_url'] = trim((string) ($settings['twilio_webhook_url'] ?? ''));
    $settings['twilio_validate_signature'] = !empty($settings['twilio_validate_signature']);
    $settings['template_name'] = trim((string) ($settings['template_name'] ?? ''));
    $settings['template_language'] = trim((string) ($settings['template_language'] ?? 'en_US'));
    if (!is_array($settings['template_variables'])) {
        $settings['template_variables'] = ['guest', 'agent', 'company'];
    }

    if ($settings['twilio_auth_token'] === '') {
        $env = getenv('CRM_TWILIO_AUTH_TOKEN');
        if ($env !== false && trim((string) $env) !== '') {
            $settings['twilio_auth_token'] = trim((string) $env);
        }
    }

    if ($settings['twilio_account_sid'] === '') {
        $env = getenv('CRM_TWILIO_ACCOUNT_SID');
        if ($env !== false && trim((string) $env) !== '') {
            $settings['twilio_account_sid'] = trim((string) $env);
        }
    }

    if ($settings['twilio_whatsapp_from'] === '') {
        $env = getenv('CRM_TWILIO_WHATSAPP_FROM');
        if ($env !== false && trim((string) $env) !== '') {
            $settings['twilio_whatsapp_from'] = trim((string) $env);
        }
    }

    // Normalize From to whatsapp:+E164 when a bare number is provided.
    $from = $settings['twilio_whatsapp_from'];
    if ($from !== '' && stripos($from, 'whatsapp:') !== 0) {
        $digits = preg_replace('/\D+/', '', $from);
        if ($digits !== null && $digits !== '') {
            $settings['twilio_whatsapp_from'] = 'whatsapp:+' . ltrim($digits, '+');
        }
    }

    return $settings;
}

function crmTwilioAccountSid(): string
{
    $cfg = crmWhatsappSettings();

    return (string) ($cfg['twilio_account_sid'] ?? '');
}

function crmTwilioAuthToken(): string
{
    $cfg = crmWhatsappSettings();

    return (string) ($cfg['twilio_auth_token'] ?? '');
}

function crmTwilioWhatsappFrom(): string
{
    $cfg = crmWhatsappSettings();

    return (string) ($cfg['twilio_whatsapp_from'] ?? '');
}

function crmTwilioContentSid(): string
{
    $cfg = crmWhatsappSettings();

    return (string) ($cfg['twilio_content_sid'] ?? '');
}

function crmTwilioValidateSignatureEnabled(): bool
{
    $cfg = crmWhatsappSettings();

    return !empty($cfg['twilio_validate_signature']);
}

function crmTwilioConfiguredWebhookUrl(): string
{
    $cfg = crmWhatsappSettings();

    return (string) ($cfg['twilio_webhook_url'] ?? '');
}

/** True when Account SID + Auth Token + WhatsApp From are set (required to send). */
function crmTwilioIsConfigured(): bool
{
    return crmTwilioAccountSid() !== ''
        && crmTwilioAuthToken() !== ''
        && crmTwilioWhatsappFrom() !== '';
}

function crmWhatsappTemplateName(): string
{
    $cfg = crmWhatsappSettings();
    $contentSid = crmTwilioContentSid();
    if ($contentSid !== '') {
        return $contentSid;
    }

    return (string) ($cfg['template_name'] ?? '');
}

function crmWhatsappTemplateLanguage(): string
{
    $cfg = crmWhatsappSettings();
    $language = (string) ($cfg['template_language'] ?? 'en_US');

    return $language === 'en' ? 'en' : 'en_US';
}

/** @return list<string> */
function crmWhatsappTemplateVariableTokens(): array
{
    $cfg = crmWhatsappSettings();
    $tokens = [];
    foreach ((array) ($cfg['template_variables'] ?? []) as $token) {
        $token = strtolower(trim((string) $token));
        if (in_array($token, ['guest', 'agent', 'company'], true)) {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

/**
 * Safe to render in the leads page. Never includes API keys or tokens.
 *
 * @return array{
 *   key_configured:bool,
 *   twilio_configured:bool,
 *   template_name:string,
 *   template_language:string,
 *   ready:bool,
 *   can_send:bool,
 *   can_receive:bool
 * }
 */
function crmWhatsappPublicStatus(): array
{
    $ready = crmTwilioIsConfigured();
    $tokenOk = crmTwilioAuthToken() !== '';

    return [
        'key_configured' => $ready,
        'twilio_configured' => $ready,
        'template_name' => crmWhatsappTemplateName(),
        'template_language' => crmWhatsappTemplateLanguage(),
        'can_send' => $ready,
        'can_receive' => $tokenOk,
        'ready' => $ready,
    ];
}
