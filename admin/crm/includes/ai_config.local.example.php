<?php
/**
 * Copy to ai_config.local.php
 *
 * instant_mode (default true) — immediate local itinerary, no API wait.
 * use_gemini_ai — set true only when you have active Gemini quota/billing.
 */
return [
    'gemini_api_key' => '',
    'use_gemini_ai' => false,
    'instant_mode' => true,
    'pexels_api_key' => '',
    // Unsplash (https://unsplash.com/oauth/applications) — only access key is used for search.
    // Never expose secret_key to frontend JS.
    'unsplash_access_key' => '',
    'unsplash_secret_key' => '',
    'unsplash_application_id' => '',
];
