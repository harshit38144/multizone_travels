<?php

/**
 * Copy this file to whatsapp_config.local.php and fill in the values.
 * whatsapp_config.local.php is gitignored. Do not commit secrets.
 *
 * Twilio Console → Account SID / Auth Token
 * WhatsApp From: sandbox e.g. whatsapp:+14155238886 or your approved sender
 * Content SID (optional): HX… template for business-initiated sends outside the 24h window
 */
return [
    'twilio_account_sid' => '',
    'twilio_auth_token' => '',
    'twilio_whatsapp_from' => 'whatsapp:+14155238886',
    'twilio_content_sid' => '',
    'twilio_validate_signature' => true,
    // Must match the URL set in Twilio Console (used for signature validation).
    'twilio_webhook_url' => 'https://admin.multizonetravels.com/crm/webhook.php',

    // Content template body variable order (maps to ContentVariables 1, 2, 3…)
    'template_variables' => ['guest', 'agent', 'company'],
];
