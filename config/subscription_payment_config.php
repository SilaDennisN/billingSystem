<?php
/**
 * config/subscription_payment_config.php
 *
 * Platform-level PesaFlux credentials used for subscription billing.
 * These are NOT per-router — they belong to Inovatech's own Paybill/Till.
 *
 * NEVER expose this file publicly. Block via .htaccess:
 *   <Files "subscription_payment_config.php">
 *       Order allow,deny
 *       Deny from all
 *   </Files>
 */

// ── Platform PesaFlux Credentials ────────────────────────────
define('SUB_PESAFLUX_API_KEY', 'PSFXUoq8DOc2');
define('SUB_PESAFLUX_EMAIL',   'siladennis1256@gmail.com');

// ── Shared PesaFlux endpoints (same as per-router) ───────────
if (!defined('PESAFLUX_STK_URL')) {
    define('PESAFLUX_STK_URL',    'https://api.pesaflux.co.ke/v1/initiatestk');
}
if (!defined('PESAFLUX_VERIFY_URL')) {
    define('PESAFLUX_VERIFY_URL', 'https://api.pesaflux.co.ke/v1/transactionstatus');
}