<?php
declare(strict_types=1);

/**
 * Copie este archivo como config.local.php y reemplace únicamente los valores
 * ficticios. config.local.php está ignorado por Git.
 */
return [
    'app_env' => 'local',
    'mail_dry_run' => true,
    'smtp_host' => 'smtp.example.invalid',
    'smtp_port' => 465,
    'smtp_username' => 'usuario@example.invalid',
    'smtp_password' => 'CAMBIAR_EN_CONFIG_LOCAL',
    'smtp_encryption' => 'ssl',
    'mail_from' => 'formulario@example.invalid',
    'mail_from_name' => 'SERVICOM Básculas Digitales',
    'mail_recipient' => 'destinatario@example.invalid',
    'turnstile_site_key' => 'CLAVE_PUBLICA_TURNSTILE',
    'turnstile_secret_key' => 'CLAVE_SECRETA_TURNSTILE',
    'contact_min_fill_seconds' => 3,
    'contact_session_max_attempts' => 4,
    'contact_session_window_seconds' => 600,
    'contact_ip_max_attempts' => 12,
    'contact_ip_window_seconds' => 900,
    'contact_rate_limit_key' => 'GENERAR_VALOR_ALEATORIO_PRIVADO',
    'trust_cloudflare_ip_header' => false,
];
