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

function servicom_contact_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function servicom_contact_fold_text(string $value): string
{
    return strtr(servicom_contact_lower($value), [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n',
    ]);
}

function servicom_contact_words(string $value): array
{
    $matches = [];
    $result = preg_match_all('/[\p{L}\p{N}]+/u', servicom_contact_fold_text($value), $matches);
    return $result === false ? [] : ($matches[0] ?? []);
}

function servicom_contact_has_crlf(string $value): bool
{
    return preg_match('/[\r\n]/', $value) === 1;
}

function servicom_contact_email_domain(string $email): string
{
    $normalized = servicom_contact_lower(trim($email));
    $separator = strrpos($normalized, '@');
    return $separator === false ? '' : rtrim(substr($normalized, $separator + 1), '.');
}

function servicom_contact_domain_is_blocked(string $domain, array $spamConfig): bool
{
    if ($domain === '') {
        return false;
    }

    foreach ($spamConfig['blocked_domains'] as $blockedDomain) {
        $blockedDomain = trim(servicom_contact_lower((string) $blockedDomain), ". \t\n\r\0\x0B");
        if ($blockedDomain !== '' && (
            $domain === $blockedDomain
            || str_ends_with($domain, '.' . $blockedDomain)
        )) {
            return true;
        }
    }

    $lastDot = strrpos($domain, '.');
    $tld = $lastDot === false ? '' : substr($domain, $lastDot + 1);
    $blockedTlds = array_map(
        static fn($value): string => ltrim(servicom_contact_lower((string) $value), '.'),
        $spamConfig['blocked_tlds']
    );
    return $tld !== '' && in_array($tld, $blockedTlds, true);
}

function servicom_contact_script_counts(string $message, array $spamConfig): array
{
    $counts = [];
    foreach ($spamConfig['blocked_scripts'] as $script) {
        $script = (string) $script;
        $matches = [];
        $count = @preg_match_all('/\p{' . $script . '}/u', $message, $matches);
        if ($count !== false && $count > 0) {
            $counts[$script] = $count;
        }
    }
    return $counts;
}

function servicom_contact_language_result(string $message, array $spamConfig): array
{
    $normalized = implode(' ', servicom_contact_words($message));
    foreach ($spamConfig['short_foreign_phrases'] as $phrase) {
        if ($normalized === implode(' ', servicom_contact_words((string) $phrase))) {
            return ['blocked' => true, 'language' => 'short-foreign-phrase'];
        }
    }

    $allowedTechnical = array_fill_keys(array_map(
        'servicom_contact_fold_text',
        array_map('strval', $spamConfig['allowed_technical_words'])
    ), true);
    $tokens = array_values(array_filter(
        servicom_contact_words($message),
        static fn(string $word): bool => !isset($allowedTechnical[$word])
    ));
    $spanishWords = array_fill_keys(array_map(
        'servicom_contact_fold_text',
        array_map('strval', $spamConfig['spanish_signal_words'])
    ), true);
    $spanishSignals = 0;
    foreach ($tokens as $token) {
        if (isset($spanishWords[$token])) {
            $spanishSignals++;
        }
    }

    $strongestLanguage = '';
    $strongestSignals = 0;
    foreach ($spamConfig['foreign_signal_words'] as $language => $words) {
        $signals = array_fill_keys(array_map(
            'servicom_contact_fold_text',
            array_map('strval', $words)
        ), true);
        $count = 0;
        foreach ($tokens as $token) {
            if (isset($signals[$token])) {
                $count++;
            }
        }
        if ($count > $strongestSignals) {
            $strongestLanguage = (string) $language;
            $strongestSignals = $count;
        }
    }

    $minimum = (int) $spamConfig['foreign_minimum_signals'];
    $margin = (int) $spamConfig['foreign_signal_margin'];
    if ($strongestSignals >= $minimum && $strongestSignals >= ($spanishSignals + $margin)) {
        return [
            'blocked' => true,
            'language' => $strongestLanguage,
            'foreign_signals' => $strongestSignals,
            'spanish_signals' => $spanishSignals,
        ];
    }
    return ['blocked' => false];
}

function servicom_contact_count_links(string $message): int
{
    $withoutEmails = preg_replace(
        '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu',
        ' ',
        $message
    ) ?? $message;
    $pattern = "~(?:https?://|www\.)[^\s<>\"']+|(?<![@\p{L}\p{N}_-])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:com\.mx|com|net|org|mx|io|co|es|info|biz)(?:/[^\s<>\"']*)?~iu";
    $matches = [];
    $count = preg_match_all($pattern, $withoutEmails, $matches);
    return $count === false ? 0 : $count;
}

function servicom_contact_repetition_result(string $message, array $spamConfig): array
{
    $characterLimit = max(1, (int) $spamConfig['max_consecutive_characters']);
    if (preg_match('/([^\s])\1{' . $characterLimit . ',}/u', $message) === 1) {
        return ['blocked' => true, 'kind' => 'characters'];
    }

    $words = servicom_contact_words($message);
    $wordLimit = max(1, (int) $spamConfig['max_consecutive_words']);
    $previous = null;
    $consecutive = 0;
    foreach ($words as $word) {
        if ($word === $previous) {
            $consecutive++;
        } else {
            $previous = $word;
            $consecutive = 1;
        }
        if ($consecutive > $wordLimit) {
            return ['blocked' => true, 'kind' => 'word'];
        }
    }

    $allowedOccurrences = max(1, (int) $spamConfig['max_repeated_phrase_occurrences']);
    $wordCount = count($words);
    $maximumPhraseWords = min(8, intdiv($wordCount, $allowedOccurrences + 1));
    for ($phraseSize = 2; $phraseSize <= $maximumPhraseWords; $phraseSize++) {
        for ($start = 0; $start + ($phraseSize * ($allowedOccurrences + 1)) <= $wordCount; $start++) {
            $phrase = array_slice($words, $start, $phraseSize);
            $repeated = true;
            for ($occurrence = 1; $occurrence <= $allowedOccurrences; $occurrence++) {
                if (array_slice($words, $start + ($phraseSize * $occurrence), $phraseSize) !== $phrase) {
                    $repeated = false;
                    break;
                }
            }
            if ($repeated) {
                return ['blocked' => true, 'kind' => 'phrase'];
            }
        }
    }

    $blocks = preg_split('/\R{2,}/u', trim($message)) ?: [];
    $blockCounts = [];
    foreach ($blocks as $block) {
        $normalized = trim(preg_replace('/\s+/u', ' ', servicom_contact_fold_text($block)) ?? '');
        if (servicom_contact_length($normalized) < 20) {
            continue;
        }
        $blockCounts[$normalized] = ($blockCounts[$normalized] ?? 0) + 1;
        if ($blockCounts[$normalized] > (int) $spamConfig['max_duplicate_blocks']) {
            return ['blocked' => true, 'kind' => 'block'];
        }
    }

    return ['blocked' => false];
}

function servicom_contact_evaluate_spam(string $email, string $message, array $spamConfig): array
{
    $domain = servicom_contact_email_domain($email);
    if (servicom_contact_domain_is_blocked($domain, $spamConfig)) {
        return ['blocked' => true, 'type' => 'domain', 'domain' => $domain];
    }

    $normalizedMessage = servicom_contact_fold_text($message);
    foreach ($spamConfig['blocked_phrases'] as $phrase) {
        $normalizedPhrase = trim(servicom_contact_fold_text((string) $phrase));
        if ($normalizedPhrase !== '' && str_contains($normalizedMessage, $normalizedPhrase)) {
            return ['blocked' => true, 'type' => 'phrase'];
        }
    }

    $scriptCounts = servicom_contact_script_counts($message, $spamConfig);
    $minimumScriptCharacters = (int) $spamConfig['blocked_script_min_characters'];
    foreach ($scriptCounts as $script => $count) {
        if ($count >= $minimumScriptCharacters) {
            return ['blocked' => true, 'type' => 'script', 'script' => $script, 'count' => $count];
        }
    }

    $language = servicom_contact_language_result($message, $spamConfig);
    if (!empty($language['blocked'])) {
        return ['blocked' => true, 'type' => 'language', 'language' => $language['language'] ?? 'unknown'];
    }

    $linkCount = servicom_contact_count_links($message);
    if ($linkCount > (int) $spamConfig['max_links']) {
        return ['blocked' => true, 'type' => 'links', 'count' => $linkCount];
    }

    $repetition = servicom_contact_repetition_result($message, $spamConfig);
    if (!empty($repetition['blocked'])) {
        return ['blocked' => true, 'type' => 'repetition', 'kind' => $repetition['kind'] ?? 'unknown'];
    }

    return ['blocked' => false, 'type' => 'allowed'];
}

function servicom_contact_log_block(array $result): void
{
    $entry = [
        'timestamp' => gmdate('c'),
        'request_id' => bin2hex(random_bytes(6)),
        'type' => (string) ($result['type'] ?? 'unknown'),
    ];
    foreach (['domain', 'script', 'kind', 'count'] as $key) {
        if (isset($result[$key]) && (is_string($result[$key]) || is_int($result[$key]))) {
            $entry[$key] = $result[$key];
        }
    }
    error_log('[SERVICOM contact block] ' . json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function servicom_contact_validate_submission(
    array $post,
    array $server,
    array &$session,
    array $config,
    array $spamConfig
): array {
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
        return ['ok' => false, 'code' => 'method'];
    }

    $expectedFields = [
        'submit', 'nombre', 'telefono', 'email', 'mensaje', 'company_website', 'csrf_token',
    ];
    if (array_diff(array_keys($post), $expectedFields)) {
        return ['ok' => false, 'code' => 'unexpected-fields'];
    }

    $csrf = servicom_contact_value($post['csrf_token'] ?? '');
    $sessionCsrf = servicom_contact_value($session['contact_csrf'] ?? '');
    if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        return ['ok' => false, 'code' => 'csrf'];
    }

    if (servicom_contact_value($post['company_website'] ?? '') !== '') {
        return [
            'ok' => false,
            'code' => 'honeypot',
            'blocked' => true,
            'silent' => true,
            'spam_result' => ['type' => 'honeypot'],
        ];
    }

    $name = servicom_contact_value($post['nombre'] ?? '');
    $phone = servicom_contact_value($post['telefono'] ?? '');
    $rawEmail = servicom_contact_value($post['email'] ?? '');
    $email = preg_replace('/\s+/u', '', $rawEmail) ?? $rawEmail;
    $message = servicom_contact_value($post['mensaje'] ?? '');

    if (servicom_contact_length($name) < 2 || servicom_contact_length($name) > 100 || servicom_contact_has_crlf($name)) {
        return ['ok' => false, 'code' => 'name'];
    }
    if (servicom_contact_length($phone) < 7 || servicom_contact_length($phone) > 30
        || preg_match('/^[0-9+() .\-]+$/u', $phone) !== 1 || servicom_contact_has_crlf($phone)) {
        return ['ok' => false, 'code' => 'phone'];
    }
    if (servicom_contact_length($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || servicom_contact_has_crlf($rawEmail)) {
        return ['ok' => false, 'code' => 'email'];
    }
    if (servicom_contact_length($message) < 3 || servicom_contact_length($message) > 4000) {
        return ['ok' => false, 'code' => 'message'];
    }

    $spamResult = servicom_contact_evaluate_spam($email, $message, $spamConfig);
    if (!empty($spamResult['blocked'])) {
        return [
            'ok' => false,
            'code' => 'spam',
            'blocked' => true,
            'silent' => false,
            'spam_result' => $spamResult,
        ];
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

function servicom_contact_spam_config_valid(array $spamConfig): bool
{
    $arrayKeys = [
        'blocked_tlds', 'blocked_domains', 'blocked_scripts', 'allowed_technical_words',
        'blocked_phrases', 'short_foreign_phrases', 'spanish_signal_words', 'foreign_signal_words',
    ];
    foreach ($arrayKeys as $key) {
        if (!isset($spamConfig[$key]) || !is_array($spamConfig[$key])) {
            return false;
        }
    }
    foreach ($spamConfig['blocked_scripts'] as $script) {
        if (@preg_match('/\p{' . (string) $script . '}/u', '') === false) {
            return false;
        }
    }
    return isset(
        $spamConfig['max_links'],
        $spamConfig['blocked_script_min_characters'],
        $spamConfig['foreign_minimum_signals'],
        $spamConfig['foreign_signal_margin'],
        $spamConfig['max_consecutive_characters'],
        $spamConfig['max_consecutive_words'],
        $spamConfig['max_repeated_phrase_occurrences'],
        $spamConfig['max_duplicate_blocks']
    );
}

function servicom_contact_configuration_ready(array $config): bool
{
    $required = ['smtp_host', 'smtp_username', 'smtp_password', 'mail_from', 'mail_recipient'];
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
