<?php

/**
 * Twilio WhatsApp settings (send + receive).
 *
 * Option 1 — copy whatsapp_config.local.example.php to whatsapp_config.local.php
 * Option 2 — set CRM_TWILIO_ACCOUNT_SID / CRM_TWILIO_AUTH_TOKEN / CRM_TWILIO_WHATSAPP_FROM
 *
 * Secrets stay on the server. Do not print them or send them to the browser.
 *
 * twilio_mode:
 *   trial      — ContentSid required (Twilio trial / sandbox templates only)
 *   production — free text inside 24h window; approved Content templates outside
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
        'twilio_mode' => 'trial',
        'twilio_content_sid' => '',
        'twilio_content_templates' => [],
        'twilio_validate_signature' => true,
        'twilio_webhook_url' => '',
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

    $mode = strtolower(trim((string) ($settings['twilio_mode'] ?? 'trial')));
    $settings['twilio_mode'] = $mode === 'production' ? 'production' : 'trial';

    if (!is_array($settings['template_variables'])) {
        $settings['template_variables'] = ['guest', 'agent', 'company'];
    }
    if (!is_array($settings['twilio_content_templates'])) {
        $settings['twilio_content_templates'] = [];
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

    $from = $settings['twilio_whatsapp_from'];
    if ($from !== '' && stripos($from, 'whatsapp:') !== 0) {
        $digits = preg_replace('/\D+/', '', $from);
        if ($digits !== null && $digits !== '') {
            $settings['twilio_whatsapp_from'] = 'whatsapp:+' . ltrim($digits, '+');
        }
    }

    // If only a single default Content SID is set, expose it as a selectable template.
    if ($settings['twilio_content_sid'] !== '' && $settings['twilio_content_templates'] === []) {
        $settings['twilio_content_templates'] = [[
            'sid' => $settings['twilio_content_sid'],
            'label' => 'Default template',
            'preview' => '',
            'variables' => $settings['template_variables'],
        ]];
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

/** @return 'trial'|'production' */
function crmTwilioMode(): string
{
    $cfg = crmWhatsappSettings();
    $mode = strtolower((string) ($cfg['twilio_mode'] ?? 'trial'));

    return $mode === 'production' ? 'production' : 'trial';
}

function crmTwilioIsTrialMode(): bool
{
    return crmTwilioMode() === 'trial';
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

/**
 * Predefined Content templates available in the chat picker (safe for the browser).
 *
 * @return list<array{sid:string,label:string,preview:string,variables:list<string>}>
 */
function crmTwilioContentTemplates(): array
{
    $cfg = crmWhatsappSettings();
    $out = [];
    foreach ((array) ($cfg['twilio_content_templates'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sid = trim((string) ($row['sid'] ?? ''));
        if ($sid === '' || !preg_match('/^HX[0-9a-fA-F]+$/', $sid)) {
            continue;
        }
        $vars = [];
        foreach ((array) ($row['variables'] ?? $cfg['template_variables'] ?? []) as $token) {
            $token = strtolower(trim((string) $token));
            if ($token !== '') {
                $vars[] = $token;
            }
        }
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            $label = 'Template ' . substr($sid, 0, 8);
        }
        $out[] = [
            'sid' => $sid,
            'label' => $label,
            'preview' => trim((string) ($row['preview'] ?? '')),
            'variables' => $vars,
        ];
    }

    return $out;
}

/**
 * Resolve a Content SID from the picker / default config.
 */
function crmTwilioResolveContentSid(string $requested = ''): string
{
    $requested = trim($requested);
    if ($requested !== '' && preg_match('/^HX[0-9a-fA-F]+$/', $requested)) {
        foreach (crmTwilioContentTemplates() as $tpl) {
            if ($tpl['sid'] === $requested) {
                return $requested;
            }
        }
        // Allow an explicit SID even if not listed (ops override).
        return $requested;
    }

    $default = crmTwilioContentSid();
    if ($default !== '' && preg_match('/^HX[0-9a-fA-F]+$/', $default)) {
        return $default;
    }

    $templates = crmTwilioContentTemplates();
    if ($templates !== []) {
        return $templates[0]['sid'];
    }

    return '';
}

/**
 * @return array{sid:string,label:string,preview:string,variables:list<string>}|null
 */
function crmTwilioFindContentTemplate(string $sid): ?array
{
    $sid = trim($sid);
    foreach (crmTwilioContentTemplates() as $tpl) {
        if ($tpl['sid'] === $sid) {
            return $tpl;
        }
    }

    return null;
}

function crmWhatsappTemplateName(): string
{
    return crmTwilioResolveContentSid();
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
 * @return array<string, mixed>
 */
function crmWhatsappPublicStatus(): array
{
    $ready = crmTwilioIsConfigured();
    $tokenOk = crmTwilioAuthToken() !== '';
    $mode = crmTwilioMode();
    $templates = crmTwilioContentTemplates();
    $defaultSid = crmTwilioResolveContentSid();

    return [
        'key_configured' => $ready,
        'twilio_configured' => $ready,
        'twilio_mode' => $mode,
        'is_trial' => $mode === 'trial',
        'templates' => $templates,
        'default_content_sid' => $defaultSid,
        'template_name' => $defaultSid,
        'template_language' => crmWhatsappTemplateLanguage(),
        'can_send' => $ready,
        'can_send_text' => $ready && $mode === 'production',
        'can_receive' => $tokenOk,
        'ready' => $ready,
    ];
}
