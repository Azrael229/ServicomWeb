<?php
declare(strict_types=1);

require_once __DIR__ . '/../estructura/contact_security.php';
$spamConfig = require __DIR__ . '/../estructura/contact_spam_config.php';

$baseConfig = [
    'mail_dry_run' => true,
    'smtp_host' => 'smtp.example.invalid',
    'smtp_port' => 465,
    'smtp_username' => 'usuario-de-prueba',
    'smtp_password' => 'secreto-solo-de-prueba',
    'smtp_encryption' => 'ssl',
    'mail_from' => 'formulario@servicombasculas.com.mx',
    'mail_from_name' => 'SERVICOM Básculas Digitales',
    'mail_recipient' => 'contacto@servicombasculas.com.mx',
];
$server = ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '127.0.0.1'];
$validPost = [
    'submit' => '',
    'nombre' => 'Cliente de prueba',
    'telefono' => '442 000 0000',
    'email' => 'cliente@example.com',
    'mensaje' => 'Necesito información para reparar una báscula.',
    'company_website' => '',
    'csrf_token' => 'csrf-test',
];
$sessionFactory = static fn(): array => ['contact_csrf' => 'csrf-test'];
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
$evaluate = static fn(string $email, string $message): array
    => servicom_contact_evaluate_spam($email, $message, $spamConfig);

$run('Configuración antispam válida y Unicode disponible', function () use ($spamConfig, $assert): void {
    $assert(servicom_contact_spam_config_valid($spamConfig), 'La configuración o las propiedades Unicode no son compatibles.');
});

$blockedSenders = [
    'svetlanaxopome@mail.ru',
    'relietracku@mail.ru',
    'akindinkudryashov6842@mail.ru',
    'ramyl_gilmanov@mail.ru',
    'pavelnaciti@mail.ru',
    'yulyagabipo@mail.ru',
    'stefaniyamorozova8282@inbox.ru',
    'xqdjpnxdrOn@ventura17.ru',
    'pbvgdjcwxpa@ventura17.ru',
    'ugowqdwjjst@ventura17.ru',
    'hevhapogtSt@ventura17.ru',
    'tpnersdxtEt@ventura17.ru',
    'ajkccizneOt@ventura17.ru',
    'novikov_ivan_1978_10_4@bk.ru',
    'kxiliya@bk.ru',
    'qnikcvfe@bekommenmail.com',
    'kxbywdjg@duhastmail.com',
    'rwsndqqt@duhastmail.com',
    'chqrylge@duhastmail.com',
    'zftflosi@fuhrenmail.com',
    'almirershawson2000@dezikmail.com',
    'suhedza@hotmail.cim',
];
foreach ($blockedSenders as $sender) {
    $run('Remitente bloqueado: ' . $sender, function () use ($sender, $evaluate, $assert): void {
        $result = $evaluate($sender, 'Necesito información de una báscula.');
        $assert(!empty($result['blocked']) && ($result['type'] ?? '') === 'domain', 'El remitente no fue bloqueado por dominio.');
    });
}

$blockedDomains = [
    'mail.ru', 'inbox.ru', 'ventura17.ru', 'bk.ru', 'bekommenmail.com',
    'duhastmail.com', 'fuhrenmail.com', 'dezikmail.com', 'hotmail.cim',
];
foreach ($blockedDomains as $domain) {
    $run('Dominio configurado: ' . $domain, function () use ($domain, $evaluate, $assert): void {
        $assert(!empty($evaluate('prueba@' . $domain, 'Necesito calibración.')['blocked']), 'El dominio configurado fue permitido.');
    });
}

$run('TLD .ru nuevo', function () use ($evaluate, $assert): void {
    $assert(!empty($evaluate('persona@dominio-nuevo.ru', 'Necesito calibración.')['blocked']), 'Un dominio .ru nuevo fue permitido.');
});
$run('Dominio bloqueado con mayúsculas y espacios', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['email'] = ' PERSONA@MAIL.RU ';
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig);
    $assert(($result['code'] ?? '') === 'spam', 'Mayúsculas o espacios evadieron el bloqueo.');
});
$run('Subdominio de dominio bloqueado', function () use ($evaluate, $assert): void {
    $assert(!empty($evaluate('persona@sub.mail.ru', 'Necesito calibración.')['blocked']), 'El subdominio bloqueado fue permitido.');
});

foreach (['hotmail.com', 'gmail.com', 'outlook.com', 'empresa.com.mx', 'empresa-internacional.com'] as $domain) {
    $run('Dominio legítimo permitido: ' . $domain, function () use ($domain, $evaluate, $assert): void {
        $assert(empty($evaluate('persona@' . $domain, 'Necesito calibración de una báscula.')['blocked']), 'Se bloqueó un dominio legítimo.');
    });
}

$allowedSpanish = [
    'Necesito reparar una báscula que no enciende.',
    'Solicito información para calibración de una báscula de plataforma.',
    'Requiero conectar una báscula por RS232 a una computadora.',
    'Busco una báscula con software y conexión Bluetooth.',
    'Necesito reparar una báscula Mettler Toledo IND560.',
    'Solicito calibración del modelo XK-200.5.',
    'Necesito ayuda.',
];
foreach ($allowedSpanish as $index => $message) {
    $run('Español legítimo permitido ' . ($index + 1), function () use ($message, $evaluate, $assert): void {
        $assert(empty($evaluate('cliente@gmail.com', $message)['blocked']), 'Se bloqueó el mensaje legítimo: ' . $message);
    });
}

$blockedLanguages = [
    'Hello, I need information about your services.',
    'Contact me for a business proposal.',
    'We provide SEO services for your website.',
    'Hello',
    'Hello!',
    'Buy now',
    'Здравствуйте, мне нужна информация о ваших услугах.',
    'Это текст на кириллице.',
    'مرحبا أريد معلومات عن خدماتكم',
    '这是一个中文垃圾消息',
];
foreach ($blockedLanguages as $index => $message) {
    $run('Idioma ajeno bloqueado ' . ($index + 1), function () use ($message, $evaluate, $assert): void {
        $result = $evaluate('cliente@example.com', $message);
        $assert(!empty($result['blocked']), 'Se permitió contenido claramente ajeno al español: ' . $message);
    });
}

$linkMessages = [
    0 => 'Necesito información para reparar una báscula.',
    1 => 'Necesito información y adjunto https://example.com.',
    2 => 'Necesito información: https://example.com y www.example.net.',
];
foreach ($linkMessages as $expected => $message) {
    $run($expected . ' enlaces permitidos', function () use ($message, $expected, $evaluate, $assert): void {
        $assert(servicom_contact_count_links($message) === $expected, 'El conteo de enlaces no coincide.');
        $assert(empty($evaluate('cliente@example.com', $message)['blocked']), 'Se bloquearon hasta dos enlaces.');
    });
}
$run('Tres enlaces bloqueados', function () use ($evaluate, $assert): void {
    $message = 'Necesito revisar example.com, segundo.net y https://tercero.org.';
    $result = $evaluate('cliente@example.com', $message);
    $assert(($result['type'] ?? '') === 'links' && ($result['count'] ?? 0) === 3, 'No se bloquearon tres enlaces.');
});
$run('Correo dentro del texto no cuenta como enlace', function () use ($assert): void {
    $assert(servicom_contact_count_links('Mi correo es cliente@example.com para información.') === 0, 'Un correo se contó como enlace.');
});
$run('Modelo con puntos o guiones no cuenta como enlace', function () use ($assert): void {
    $assert(servicom_contact_count_links('Necesito reparar el modelo IND.560-A y XK-200.5.') === 0, 'Un modelo técnico se contó como enlace.');
});

$run('Repetición natural permitida', function () use ($evaluate, $assert): void {
    $message = 'La báscula requiere calibración y la reparación de la báscula necesita revisión.';
    $assert(empty($evaluate('cliente@example.com', $message)['blocked']), 'Se bloqueó una repetición natural.');
});
$run('Caracteres excesivamente repetidos', function () use ($evaluate, $assert): void {
    $assert(($evaluate('cliente@example.com', 'Necesito ayudaaaaaaaaaaaaa con mi báscula.')['type'] ?? '') === 'repetition', 'No se bloquearon caracteres repetidos.');
});
$run('Palabra excesivamente repetida', function () use ($evaluate, $assert): void {
    $message = 'oferta oferta oferta oferta oferta oferta para una báscula';
    $assert(($evaluate('cliente@example.com', $message)['type'] ?? '') === 'repetition', 'No se bloqueó una palabra repetida.');
});
$run('Frase idéntica repetida', function () use ($evaluate, $assert): void {
    $message = 'oferta especial oferta especial oferta especial oferta especial';
    $assert(($evaluate('cliente@example.com', $message)['type'] ?? '') === 'repetition', 'No se bloqueó una frase repetida.');
});

$run('Envío válido simulado', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($validPost, $server, $session, $baseConfig, $spamConfig);
    $assert(!empty($result['ok']), 'El envío válido fue rechazado.');
});
$run('Honeypot vacío continúa', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($validPost, $server, $session, $baseConfig, $spamConfig);
    $assert(!empty($result['ok']), 'El honeypot vacío bloqueó el envío.');
});
$run('Honeypot lleno bloquea silenciosamente', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['company_website'] = 'contenido del bot';
    $session = $sessionFactory();
    $result = servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig);
    $assert(($result['code'] ?? '') === 'honeypot' && !empty($result['silent']), 'El honeypot no produjo bloqueo silencioso.');
});
$run('Correo inválido', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['email'] = 'correo-invalido';
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'email', 'Se aceptó el correo inválido.');
});
$run('Longitud excesiva', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['mensaje'] = str_repeat('a', 4001);
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'message', 'Se aceptó una longitud excesiva.');
});
$run('CSRF inválido', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['csrf_token'] = 'incorrecto';
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'csrf', 'Se aceptó CSRF inválido.');
});
$run('POST directo sin sesión rechazado', function () use ($validPost, $server, $baseConfig, $spamConfig, $assert): void {
    $session = [];
    $assert((servicom_contact_validate_submission($validPost, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'csrf', 'Se aceptó un POST directo sin sesión.');
});
$run('Método distinto de POST rechazado', function () use ($validPost, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($validPost, ['REQUEST_METHOD' => 'GET'], $session, $baseConfig, $spamConfig)['code'] ?? '') === 'method', 'Se aceptó un método distinto de POST.');
});
$run('Campos inesperados rechazados', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['admin'] = '1';
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'unexpected-fields', 'Se aceptó un campo inesperado.');
});
$run('Inyección CRLF rechazada', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['nombre'] = "Persona\r\nBcc: atacante@example.com";
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'name', 'Se aceptó una inyección CRLF.');
});
$run('Inyección CRLF en correo rechazada', function () use ($validPost, $server, $baseConfig, $spamConfig, $sessionFactory, $assert): void {
    $post = $validPost;
    $post['email'] = "cliente@example.com\r\nBcc: atacante@example.com";
    $session = $sessionFactory();
    $assert((servicom_contact_validate_submission($post, $server, $session, $baseConfig, $spamConfig)['code'] ?? '') === 'email', 'Se aceptó CRLF en el correo.');
});
$run('HTML y JavaScript escapados', function () use ($baseConfig, $assert): void {
    $data = ['nombre' => '<b>Cliente</b>', 'telefono' => '4420000000', 'email' => 'cliente@example.com', 'mensaje' => '<script>alert(1)</script>'];
    $message = servicom_contact_build_message($data, $baseConfig);
    $assert(strpos($message['html'], '<script>') === false && strpos($message['html'], '&lt;script&gt;') !== false, 'El HTML o JavaScript no fue escapado.');
});
$run('From seguro y Reply-To del visitante', function () use ($baseConfig, $assert): void {
    $data = ['nombre' => 'Cliente', 'telefono' => '4420000000', 'email' => 'cliente@example.com', 'mensaje' => 'Necesito una reparación.'];
    $message = servicom_contact_build_message($data, $baseConfig);
    $assert($message['from'] === 'formulario@servicombasculas.com.mx', 'From no pertenece al dominio.');
    $assert($message['reply_to'] === 'cliente@example.com', 'Reply-To no corresponde al visitante.');
});
$run('From externo rechazado', function () use ($baseConfig, $assert): void {
    $config = array_replace($baseConfig, ['mail_from' => 'suplantado@example.com']);
    $assert(!servicom_contact_configuration_ready($config), 'Se aceptó un From externo.');
});
$run('Pruebas sin cargar PHPMailer ni SMTP', function () use ($baseConfig, $assert): void {
    $assert($baseConfig['mail_dry_run'] === true, 'El modo simulado no está activo.');
    $assert(!class_exists('PHPMailer\\PHPMailer\\PHPMailer', false), 'PHPMailer fue cargado durante las pruebas unitarias.');
});

$failures = 0;
foreach ($tests as [$name, $passed, $detail]) {
    echo ($passed ? '[OK] ' : '[FALLO] ') . $name . ': ' . $detail . PHP_EOL;
    if (!$passed) {
        $failures++;
    }
}
echo PHP_EOL . count($tests) . ' pruebas; ' . $failures . ' fallos.' . PHP_EOL;
exit($failures === 0 ? 0 : 1);
