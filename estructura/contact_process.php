<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('SERVICOM_APP_BOOTSTRAPPED', true);
$contactConfig = require __DIR__ . '/../config.php';
$contactSpamConfig = require __DIR__ . '/contact_spam_config.php';
require_once __DIR__ . '/contact_security.php';

$contactConfigurationReady = servicom_contact_configuration_ready($contactConfig)
    && servicom_contact_spam_config_valid($contactSpamConfig);
$contactFlash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);
$contactOld = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = servicom_contact_validate_submission(
        $_POST,
        $_SERVER,
        $_SESSION,
        $contactConfig,
        $contactSpamConfig
    );
    $success = false;
    $publicSpamBlock = false;

    if (!$contactConfigurationReady) {
        error_log('[SERVICOM contact] Configuración incompleta; envío rechazado de forma segura.');
        $result = ['ok' => false, 'code' => 'configuration'];
    } elseif (!empty($result['blocked'])) {
        servicom_contact_log_block((array) ($result['spam_result'] ?? ['type' => 'unknown']));
        if (!empty($result['silent'])) {
            // Éxito aparente: no informa al bot y nunca ejecuta PHPMailer.
            $success = true;
        } else {
            $publicSpamBlock = true;
        }
    } elseif (!empty($result['ok'])) {
        $message = servicom_contact_build_message($result['data'], $contactConfig);
        if (!empty($contactConfig['mail_dry_run'])) {
            $success = true;
            error_log('[SERVICOM contact] Simulación local completada; no se contactó al servidor SMTP.');
        } else {
            require_once __DIR__ . '/../configuracion/PHPMailer/src/Exception.php';
            require_once __DIR__ . '/../configuracion/PHPMailer/src/PHPMailer.php';
            require_once __DIR__ . '/../configuracion/PHPMailer/src/SMTP.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = (string) $contactConfig['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = (string) $contactConfig['smtp_username'];
                $mail->Password = (string) $contactConfig['smtp_password'];
                $mail->SMTPSecure = (string) $contactConfig['smtp_encryption'];
                $mail->Port = (int) $contactConfig['smtp_port'];
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($message['from'], $message['from_name']);
                $mail->addAddress($message['recipient'], 'SERVICOM Básculas Digitales');
                $mail->addReplyTo($message['reply_to'], $message['reply_name']);
                $mail->isHTML(true);
                $mail->Subject = $message['subject'];
                $mail->Body = $message['html'];
                $mail->AltBody = $message['text'];
                $mail->send();
                $success = true;
            } catch (Throwable $exception) {
                error_log('[SERVICOM contact] Error interno durante el envío SMTP.');
            }
        }
    }

    if ($success) {
        $_SESSION['contact_flash'] = [
            'type' => 'success',
            'message' => 'Gracias por comunicarte. Tu mensaje fue recibido correctamente.',
        ];
        $_SESSION['contact_old'] = [];
    } elseif ($publicSpamBlock) {
        $_SESSION['contact_flash'] = [
            'type' => 'danger',
            'message' => 'No fue posible procesar el mensaje. Puede comunicarse por llamada o WhatsApp al 442 871 2550.',
        ];
        $_SESSION['contact_old'] = [
            'nombre' => servicom_contact_value($_POST['nombre'] ?? ''),
            'telefono' => servicom_contact_value($_POST['telefono'] ?? ''),
            'email' => servicom_contact_value($_POST['email'] ?? ''),
            'mensaje' => servicom_contact_value($_POST['mensaje'] ?? ''),
        ];
    } else {
        $_SESSION['contact_flash'] = [
            'type' => 'danger',
            'message' => 'No fue posible enviar el mensaje. Revisa los datos e inténtalo nuevamente.',
        ];
        $_SESSION['contact_old'] = [
            'nombre' => servicom_contact_value($_POST['nombre'] ?? ''),
            'telefono' => servicom_contact_value($_POST['telefono'] ?? ''),
            'email' => servicom_contact_value($_POST['email'] ?? ''),
            'mensaje' => servicom_contact_value($_POST['mensaje'] ?? ''),
        ];
    }

    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
    header('Location: index.php#contact', true, 303);
    exit;
}

$contactCsrf = (string) $_SESSION['contact_csrf'];
