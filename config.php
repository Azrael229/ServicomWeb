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
];

foreach ($environmentMap as $environmentName => $configName) {
    $value = getenv($environmentName);
    if ($value !== false && $value !== '') {
        $config[$configName] = $value;
    }
}

$booleanKeys = ['mail_dry_run'];
foreach ($booleanKeys as $key) {
    if (!is_bool($config[$key])) {
        $config[$key] = filter_var($config[$key], FILTER_VALIDATE_BOOLEAN);
    }
}

$integerKeys = ['smtp_port'];
foreach ($integerKeys as $key) {
    $config[$key] = max(1, (int) $config[$key]);
}

return $config;
