<?php

/**
 * PhonePe Payment Gateway — Standard Checkout (PG v2).
 *
 * Dashboard: https://business.phonepe.com/ → Developer Settings.
 *
 * IMPORTANT for payments to work:
 * 1. Whitelist your EXACT site URL in PhonePe dashboard (one URL per merchant).
 * 2. Production requires HTTPS — set public_site_url to your live https:// domain.
 * 3. Use mode "sandbox" on localhost / HTTP (XAMPP).
 * 4. Do not open the PhonePe page in a new browser tab — use same tab only.
 */
return [
  /** sandbox = UAT/preprod (localhost OK). production = live (HTTPS + whitelisted domain required). */
    'mode' => 'sandbox',
    'client_id' => 'SU2605121821062309820642',
    'client_secret' => '6fcf72d3-0a7b-411f-a7dc-d6de50d0f75e',
    'client_version' => '1',

    /**
     * Your public website base URL (no trailing slash).
     * Example: https://www.multizonetravels.com
     * Leave empty to auto-detect from the current request (fine for sandbox only).
     */
    'public_site_url' => '',
];
