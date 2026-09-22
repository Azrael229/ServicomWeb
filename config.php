<?php
declare(strict_types=1);

/**
 * Configuración segura del formulario de contacto.
 *
 * Los secretos se leen de variables de entorno o de config.local.php, que está
 * ignorado por Git. Este archivo nunca debe contener credenciales reales.
 */

$config = [
    'app_env' => 'production',
    'mail_dry_run' => false,
    'smtp_host' => '',
    'smtp_port' => 465,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'ssl',
    'mail_from' => '',
    'mail_from_name' => 'SERVICOM Básculas Digitales',
    'mail_recipient' => 'contacto@servicombasculas.com.mx',
    'turnstile_site_key' => '',
    'turnstile_secret_key' => '',
    'contact_min_fill_seconds' => 3,
    'contact_session_max_attempts' => 4,
    'contact_session_window_seconds' => 600,
    'contact_ip_max_attempts' => 12,
    'contact_ip_window_seconds' => 900,
    'contact_rate_limit_key' => '',
    'contact_rate_limit_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'servicom_contact_rate',
    'trust_cloudflare_ip_header' => false,
];

$localConfigFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

$environmentMap = [
    'APP_ENV' => 'app_env',
    'MAIL_DRY_RUN' => 'mail_dry_run',
    'SMTP_HOST' => 'smtp_host',
    'SMTP_PORT' => 'smtp_port',
    'SMTP_USERNAME' => 'smtp_username',
    'SMTP_PASSWORD' => 'smtp_password',
    'SMTP_ENCRYPTION' => 'smtp_encryption',
    'MAIL_FROM' => 'mail_from',
    'MAIL_FROM_NAME' => 'mail_from_name',
    'MAIL_RECIPIENT' => 'mail_recipient',
    'TURNSTILE_SITE_KEY' => 'turnstile_site_key',
    'TURNSTILE_SECRET_KEY' => 'turnstile_secret_key',
    'CONTACT_MIN_FILL_SECONDS' => 'contact_min_fill_seconds',
    'CONTACT_SESSION_MAX_ATTEMPTS' => 'contact_session_max_attempts',
    'CONTACT_SESSION_WINDOW_SECONDS' => 'contact_session_window_seconds',
    'CONTACT_IP_MAX_ATTEMPTS' => 'contact_ip_max_attempts',
    'CONTACT_IP_WINDOW_SECONDS' => 'contact_ip_window_seconds',
    'CONTACT_RATE_LIMIT_KEY' => 'contact_rate_limit_key',
    'CONTACT_RATE_LIMIT_DIR' => 'contact_rate_limit_dir',
    'TRUST_CLOUDFLARE_IP_HEADER' => 'trust_cloudflare_ip_header',
];

foreach ($environmentMap as $environmentName => $configName) {
    $value = getenv($environmentName);
    if ($value !== false && $value !== '') {
        $config[$configName] = $value;
    }
}

$booleanKeys = ['mail_dry_run', 'trust_cloudflare_ip_header'];
foreach ($booleanKeys as $key) {
    if (!is_bool($config[$key])) {
        $config[$key] = filter_var($config[$key], FILTER_VALIDATE_BOOLEAN);
    }
}

$integerKeys = [
    'smtp_port',
    'contact_min_fill_seconds',
    'contact_session_max_attempts',
    'contact_session_window_seconds',
    'contact_ip_max_attempts',
    'contact_ip_window_seconds',
];
foreach ($integerKeys as $key) {
    $config[$key] = max(1, (int) $config[$key]);
}

return $config;
