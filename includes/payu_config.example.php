<?php

/**
 * PayU Payment Gateway — EXAMPLE only (safe to commit).
 * Copy to payu_config.php and fill in your real credentials.
 *
 *   copy includes/payu_config.example.php includes/payu_config.php
 *
 * mode: sandbox → test.payu.in | production → secure.payu.in
 *
 * Hosted checkout (payment links) needs merchant_key + merchant_salt only.
 * client_id / client_secret are for PayU Payment Links REST API (not used here).
 * Use Live Mode key/salt when mode is production — a stale salt causes
 * "incorrectly calculated hash parameter".
 */
return [
    'mode' => 'sandbox',
    'merchant_key' => '',
    'merchant_salt' => '',
    'client_id' => '',
    'client_secret' => '',
];
