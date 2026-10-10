<?php

/**
 * RichAutomate WhatsApp settings.
 *
 * Option 1 — copy whatsapp_config.local.example.php to whatsapp_config.local.php
 * Option 2 — set environment variable CRM_RICHAUTOMATE_API_KEY
 *
 * The key stays on the server. Do not print it or send it to the browser.
 */

function crmWhatsappSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [
        'richautomate_api_key' => '',
        'template_name' => '',
        'template_language' => 'en_US',
        'template_variables' => ['guest', 'agent', 'company'],
    ];

    $localFile = __DIR__ . '/whatsapp_config.local.php';
    if (is_file($localFile)) {
        $cfg = include $localFile;
        if (is_array($cfg)) {
            $settings = array_merge($settings, $cfg);
        }
    }

    $settings['richautomate_api_key'] = trim((string) ($settings['richautomate_api_key'] ?? ''));
    $settings['template_name'] = trim((string) ($settings['template_name'] ?? ''));
    $settings['template_language'] = trim((string) ($settings['template_language'] ?? 'en_US'));
    if (!is_array($settings['template_variables'])) {
        $settings['template_variables'] = ['guest', 'agent', 'company'];
    }

    if ($settings['richautomate_api_key'] === '') {
        $env = getenv('CRM_RICHAUTOMATE_API_KEY');
        if ($env !== false && trim((string) $env) !== '') {
            $settings['richautomate_api_key'] = trim((string) $env);
        }
    }

    return $settings;
}

function crmWhatsappApiKey(): string
{
    $cfg = crmWhatsappSettings();

    return (string) ($cfg['richautomate_api_key'] ?? '');
}

function crmWhatsappTemplateName(): string
{
    $cfg = crmWhatsappSettings();

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
 * Safe to render in the leads page. Never includes the API key.
 *
 * @return array{key_configured:bool,template_name:string,template_language:string,ready:bool}
 */
function crmWhatsappPublicStatus(): array
{
    $name = crmWhatsappTemplateName();
    $language = crmWhatsappTemplateLanguage();

    return [
        'key_configured' => crmWhatsappApiKey() !== '',
        'template_name' => $name,
        'template_language' => $language,
        'ready' => crmWhatsappApiKey() !== '' && $name !== '',
    ];
}
