<?php

/**
 * Copy this file to whatsapp_config.local.php and fill in the values.
 * whatsapp_config.local.php is gitignored. Do not commit secrets.
 *
 * twilio_mode:
 *   trial      — ContentSid required (Twilio trial / Console templates)
 *   production — free text inside 24h window; Content templates outside
 *
 * Copy Content SID (HX…) values from Twilio Console → Content Template Builder
 * or from the Try out WhatsApp page during trial.
 */
return [
    'twilio_account_sid' => '',
    'twilio_auth_token' => '',
    'twilio_whatsapp_from' => 'whatsapp:+14155238886',
    'twilio_mode' => 'trial',

    // Default / fallback Content SID used when the chat picker has no selection.
    'twilio_content_sid' => '',

    // Predefined templates shown in the leads chat modal.
    'twilio_content_templates' => [
        [
            'sid' => 'HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'label' => 'Sample template',
            'preview' => 'Hello {{1}}, thanks for contacting Multi Zone Travels.',
            'variables' => ['guest'],
        ],
    ],

    'twilio_validate_signature' => true,
    'twilio_webhook_url' => 'https://admin.multizonetravels.com/crm/webhook.php',
    'template_variables' => ['guest', 'agent', 'company'],
];
