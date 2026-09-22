<?php
declare(strict_types=1);

function servicom_contact_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function servicom_contact_value($value): string
{
    return is_string($value) ? trim($value) : '';
}

function servicom_contact_has_crlf(string $value): bool
{
    return preg_match('/[\r\n]/', $value) === 1;
}

function servicom_contact_client_ip(array $server, array $config): string
{
    $candidate = '';
    if (!empty($config['trust_cloudflare_ip_header'])) {
        $candidate = servicom_contact_value($server['HTTP_CF_CONNECTING_IP'] ?? '');
    }
    if ($candidate === '') {
        $candidate = servicom_contact_value($server['REMOTE_ADDR'] ?? '0.0.0.0');
    }
    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '0.0.0.0';
}

function servicom_contact_rate_limit(array &$session, string $ip, array $config, ?int $now = null): bool
{
    $now = $now ?? time();
    $sessionWindow = (int) $config['contact_session_window_seconds'];
    $sessionMax = (int) $config['contact_session_max_attempts'];
    $sessionAttempts = array_values(array_filter(
        is_array($session['contact_attempts'] ?? null) ? $session['contact_attempts'] : [],
        static fn($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $sessionWindow)
    ));
    if (count($sessionAttempts) >= $sessionMax) {
        return false;
    }
    $sessionAttempts[] = $now;
    $session['contact_attempts'] = $sessionAttempts;

    $directory = (string) $config['contact_rate_limit_dir'];
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('[SERVICOM contact] No se pudo inicializar el almacenamiento del límite por IP.');
        return false;
    }

    $key = (string) ($config['contact_rate_limit_key'] ?? '');
    $hash = $key !== '' ? hash_hmac('sha256', $ip, $key) : hash('sha256', $ip);
    $path = $directory . DIRECTORY_SEPARATOR . $hash . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        error_log('[SERVICOM contact] No se pudo abrir el control de frecuencia por IP.');
        return false;
    }

    $allowed = true;
    try {
        if (!flock($handle, LOCK_EX)) {
            error_log('[SERVICOM contact] No se pudo bloquear el control de frecuencia por IP.');
            return false;
        }
        $contents = stream_get_contents($handle);
        $attempts = json_decode($contents ?: '[]', true);
        $attempts = is_array($attempts) ? $attempts : [];
        $window = (int) $config['contact_ip_window_seconds'];
        $attempts = array_values(array_filter(
            $attempts,
            static fn($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $window)
        ));
        if (count($attempts) >= (int) $config['contact_ip_max_attempts']) {
            $allowed = false;
        } else {
            $attempts[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($attempts, JSON_UNESCAPED_SLASHES));
            fflush($handle);
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    return $allowed;
}

function servicom_contact_verify_turnstile(string $token, string $ip, array $config): array
{
    $secret = (string) ($config['turnstile_secret_key'] ?? '');
    if ($secret === '' || $token === '' || strlen($token) > 2048) {
        return ['success' => false, 'error-codes' => ['missing-input']];
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ], '', '&', PHP_QUERY_RFC3986);
    $response = false;

    if (function_exists('curl_init')) {
        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 10,
        ]]);
        $response = @file_get_contents(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            false,
            $context
        );
    }

    if (!is_string($response) || $response === '') {
        return ['success' => false, 'error-codes' => ['validation-service-unavailable']];
    }
    $result = json_decode($response, true);
    return is_array($result) ? $result : ['success' => false, 'error-codes' => ['invalid-response']];
}

function servicom_contact_validate_submission(
    array $post,
    array $server,
    array &$session,
    array $config,
    ?callable $turnstileVerifier = null,
    ?int $now = null
): array {
    $now = $now ?? time();
    $expectedFields = [
        'submit', 'nombre', 'telefono', 'email', 'mensaje', 'website',
        'csrf_token', 'cf-turnstile-response',
    ];
    if (array_diff(array_keys($post), $expectedFields)) {
        return ['ok' => false, 'code' => 'unexpected-fields'];
    }

    $csrf = servicom_contact_value($post['csrf_token'] ?? '');
    $sessionCsrf = servicom_contact_value($session['contact_csrf'] ?? '');
    if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        return ['ok' => false, 'code' => 'csrf'];
    }

    if (servicom_contact_value($post['website'] ?? '') !== '') {
        return ['ok' => false, 'code' => 'honeypot'];
    }

    $startedAt = (int) ($session['contact_form_started_at'] ?? 0);
    if ($startedAt <= 0 || ($now - $startedAt) < (int) $config['contact_min_fill_seconds']) {
        return ['ok' => false, 'code' => 'too-fast'];
    }
    if (($now - $startedAt) > 7200) {
        return ['ok' => false, 'code' => 'form-expired'];
    }

    $name = servicom_contact_value($post['nombre'] ?? '');
    $phone = servicom_contact_value($post['telefono'] ?? '');
    $email = servicom_contact_value($post['email'] ?? '');
    $message = servicom_contact_value($post['mensaje'] ?? '');

    if (servicom_contact_length($name) < 2 || servicom_contact_length($name) > 100 || servicom_contact_has_crlf($name)) {
        return ['ok' => false, 'code' => 'name'];
    }
    if (servicom_contact_length($phone) < 7 || servicom_contact_length($phone) > 30 ||
        preg_match('/^[0-9+() .\-]+$/u', $phone) !== 1 || servicom_contact_has_crlf($phone)) {
        return ['ok' => false, 'code' => 'phone'];
    }
    if (servicom_contact_length($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || servicom_contact_has_crlf($email)) {
        return ['ok' => false, 'code' => 'email'];
    }
    if (servicom_contact_length($message) < 10 || servicom_contact_length($message) > 4000) {
        return ['ok' => false, 'code' => 'message'];
    }

    $token = servicom_contact_value($post['cf-turnstile-response'] ?? '');
    if ($token === '' || strlen($token) > 2048) {
        return ['ok' => false, 'code' => 'turnstile-missing'];
    }
    $ip = servicom_contact_client_ip($server, $config);
    $verification = $turnstileVerifier
        ? $turnstileVerifier($token, $ip, $config)
        : servicom_contact_verify_turnstile($token, $ip, $config);
    if (empty($verification['success'])) {
        $codes = is_array($verification['error-codes'] ?? null) ? $verification['error-codes'] : [];
        error_log('[SERVICOM contact] Turnstile rechazó el envío: ' . implode(',', $codes));
        return ['ok' => false, 'code' => 'turnstile-invalid'];
    }

    return [
        'ok' => true,
        'code' => 'valid',
        'data' => ['nombre' => $name, 'telefono' => $phone, 'email' => $email, 'mensaje' => $message],
    ];
}

function servicom_contact_build_message(array $data, array $config): array
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeMessage = nl2br($escape($data['mensaje']), false);
    return [
        'from' => (string) $config['mail_from'],
        'from_name' => (string) $config['mail_from_name'],
        'recipient' => (string) $config['mail_recipient'],
        'reply_to' => $data['email'],
        'reply_name' => $data['nombre'],
        'subject' => 'Mensaje desde el formulario de servicombasculas.com.mx',
        'html' => '<h2>Nuevo mensaje de contacto</h2>'
            . '<p><strong>Mensaje:</strong><br>' . $safeMessage . '</p>'
            . '<p><strong>Nombre:</strong> ' . $escape($data['nombre']) . '</p>'
            . '<p><strong>Correo:</strong> ' . $escape($data['email']) . '</p>'
            . '<p><strong>Teléfono:</strong> ' . $escape($data['telefono']) . '</p>',
        'text' => "Nuevo mensaje de contacto\n\n"
            . "Mensaje:\n{$data['mensaje']}\n\n"
            . "Nombre: {$data['nombre']}\nCorreo: {$data['email']}\nTeléfono: {$data['telefono']}",
    ];
}

function servicom_contact_configuration_ready(array $config): bool
{
    $required = ['smtp_host', 'smtp_username', 'smtp_password', 'mail_from', 'mail_recipient',
        'turnstile_site_key', 'turnstile_secret_key', 'contact_rate_limit_key'];
    foreach ($required as $key) {
        if (servicom_contact_value($config[$key] ?? '') === '') {
            return false;
        }
    }
    $from = (string) $config['mail_from'];
    $fromDomain = strtolower((string) substr(strrchr($from, '@') ?: '', 1));
    return filter_var($from, FILTER_VALIDATE_EMAIL) !== false
        && $fromDomain === 'servicombasculas.com.mx'
        && filter_var($config['mail_recipient'], FILTER_VALIDATE_EMAIL) !== false;
}
