<?php
declare(strict_types=1);

require_once __DIR__ . '/../estructura/contact_security.php';

$temporaryRateDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'servicom_contact_test_' . bin2hex(random_bytes(5));
$baseConfig = [
    'contact_min_fill_seconds' => 3,
    'contact_session_max_attempts' => 4,
    'contact_session_window_seconds' => 600,
    'contact_ip_max_attempts' => 12,
    'contact_ip_window_seconds' => 900,
    'contact_rate_limit_key' => 'test-only-key',
    'contact_rate_limit_dir' => $temporaryRateDirectory,
    'trust_cloudflare_ip_header' => false,
    'mail_from' => 'formulario@servicombasculas.com.mx',
    'mail_from_name' => 'SERVICOM Básculas Digitales',
    'mail_recipient' => 'contacto@servicombasculas.com.mx',
    'turnstile_secret_key' => 'test-secret',
];
$server = ['REMOTE_ADDR' => '127.0.0.1'];
$validPost = [
    'submit' => '',
    'nombre' => 'Cliente de prueba',
    'telefono' => '442 000 0000',
    'email' => 'cliente@example.com',
    'mensaje' => 'Mensaje válido para solicitar información.',
    'website' => '',
    'csrf_token' => 'csrf-test',
    'cf-turnstile-response' => 'dummy-token',
];
$passingVerifier = static fn(): array => ['success' => true, 'error-codes' => []];
$failingVerifier = static fn(): array => ['success' => false, 'error-codes' => ['invalid-input-response']];
$tests = [];

$run = static function (string $name, callable $test) use (&$tests): void {
    try {
        $test();
        $tests[] = [$name, true, 'Aprobada'];
    } catch (Throwable $exception) {
        $tests[] = [$name, false, $exception->getMessage()];
    }
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$sessionFactory = static fn(): array => ['contact_csrf' => 'csrf-test', 'contact_form_started_at' => 1000];

$run('Envío válido simulado', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($validPost, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(!empty($result['ok']), 'El envío válido fue rechazado.');
});
$run('Campos obligatorios vacíos', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['nombre'] = '';
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(empty($result['ok']), 'Se aceptó un nombre vacío.');
});
$run('Correo inválido', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['email'] = 'correo-invalido';
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'email', 'No se rechazó el correo inválido.');
});
$run('Campo demasiado largo', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['mensaje'] = str_repeat('a', 4001);
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'message', 'No se rechazó el mensaje excesivo.');
});
$run('Honeypot completado', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['website'] = 'https://spam.invalid';
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'honeypot', 'No se activó el honeypot.');
});
$run('Envío demasiado rápido', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($validPost, $server, $session, $baseConfig, $passingVerifier, 1001);
    $assert(($result['code'] ?? '') === 'too-fast', 'No se rechazó el envío rápido.');
});
$run('Token Turnstile ausente', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['cf-turnstile-response'] = '';
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'turnstile-missing', 'No se rechazó el token ausente.');
});
$run('Token Turnstile inválido o reutilizado', function () use ($validPost, $server, $baseConfig, $failingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($validPost, $server, $session, $baseConfig, $failingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'turnstile-invalid', 'No se rechazó el token inválido.');
});
$run('Inyección CRLF', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['nombre'] = "Persona\r\nBcc: atacante@example.com";
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'name', 'No se rechazó la inyección CRLF.');
});
$run('HTML y JavaScript escapados', function () use ($baseConfig, $assert): void {
    $data = ['nombre' => '<b>Cliente</b>', 'telefono' => '4420000000', 'email' => 'cliente@example.com', 'mensaje' => '<script>alert(1)</script>'];
    $message = servicom_contact_build_message($data, $baseConfig);
    $assert(strpos($message['html'], '<script>') === false && strpos($message['html'], '&lt;script&gt;') !== false, 'El HTML no fue escapado.');
});
$run('From del dominio y Reply-To del prospecto', function () use ($baseConfig, $assert): void {
    $data = ['nombre' => 'Cliente', 'telefono' => '4420000000', 'email' => 'cliente@example.com', 'mensaje' => 'Mensaje válido de prueba.'];
    $message = servicom_contact_build_message($data, $baseConfig);
    $assert($message['from'] === 'formulario@servicombasculas.com.mx', 'El remitente no pertenece al dominio.');
    $assert($message['reply_to'] === 'cliente@example.com', 'Reply-To no corresponde al prospecto.');
});
$run('From externo rechazado por configuración', function () use ($baseConfig, $assert): void {
    $config = array_replace($baseConfig, [
        'smtp_host' => 'smtp.example.invalid',
        'smtp_username' => 'usuario',
        'smtp_password' => 'secreto-de-prueba',
        'turnstile_site_key' => 'clave-publica-de-prueba',
        'mail_from' => 'suplantado@example.com',
    ]);
    $assert(!servicom_contact_configuration_ready($config), 'Se aceptó un remitente externo al dominio de SERVICOM.');
});
$run('Campos inesperados', function () use ($validPost, $server, $baseConfig, $passingVerifier, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $post = $validPost;
    $post['admin'] = '1';
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $passingVerifier, 1005);
    $assert(($result['code'] ?? '') === 'unexpected-fields', 'No se rechazó el campo inesperado.');
});
$run('Límite por sesión', function () use ($baseConfig, $assert): void {
    $session = [];
    for ($i = 0; $i < 4; $i++) {
        $assert(servicom_contact_rate_limit($session, '192.0.2.10', $baseConfig, 2000 + $i), 'Se bloqueó antes del límite esperado.');
    }
    $assert(!servicom_contact_rate_limit($session, '192.0.2.10', $baseConfig, 2005), 'No se aplicó el límite por sesión.');
});
$run('Límite por IP entre sesiones', function () use ($baseConfig, $assert): void {
    $config = $baseConfig;
    $config['contact_session_max_attempts'] = 20;
    $config['contact_ip_max_attempts'] = 2;
    $firstSession = [];
    $secondSession = [];
    $thirdSession = [];
    $assert(servicom_contact_rate_limit($firstSession, '192.0.2.20', $config, 3000), 'Se bloqueó el primer intento por IP.');
    $assert(servicom_contact_rate_limit($secondSession, '192.0.2.20', $config, 3001), 'Se bloqueó antes del límite por IP.');
    $assert(!servicom_contact_rate_limit($thirdSession, '192.0.2.20', $config, 3002), 'No se aplicó el límite compartido por IP.');
});

$failures = 0;
foreach ($tests as [$name, $passed, $detail]) {
    echo ($passed ? '[OK] ' : '[FALLO] ') . $name . ': ' . $detail . PHP_EOL;
    if (!$passed) {
        $failures++;
    }
}
if (is_dir($temporaryRateDirectory)) {
    foreach (glob($temporaryRateDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($temporaryRateDirectory);
}
exit($failures === 0 ? 0 : 1);
