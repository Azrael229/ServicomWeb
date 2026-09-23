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
];
