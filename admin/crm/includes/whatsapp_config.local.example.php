<?php

/**
 * Copy this file to whatsapp_config.local.php and fill in the values.
 * whatsapp_config.local.php is gitignored. Do not commit the API key.
 *
 * template_name and template_language must match an approved Meta template
 * exactly. Most RichAutomate templates use language en_US, not en.
 *
 * template_variables is the body variable order:
 *   guest   — lead first name
 *   agent   — signed-in staff name
 *   company — Multi Zone Travels
 */
return [
    'richautomate_api_key' => 'ra_X6CbQ96ffL8aR8lLnKqN30QD4RRmV8jk27a9lrjl',
    'template_name' => '',
    'template_language' => 'en_US',
    'template_variables' => ['guest', 'agent', 'company'],
];
