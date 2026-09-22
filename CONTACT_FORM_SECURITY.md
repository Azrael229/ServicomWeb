# Protección del formulario de contacto

## Alcance

La protección combina Turnstile, CSRF, honeypot, tiempo mínimo, límites de frecuencia, validación del lado del servidor, límites de longitud, escape de HTML y envío SMTP seguro.

## Configuración privada

Los secretos se leen desde variables de entorno o desde `config.local.php`. Para una instalación manual, copie `config.example.php` como `config.local.php` y reemplace todos los valores ficticios. Nunca confirme `config.local.php` en Git.

Variables admitidas:

- `APP_ENV`
- `MAIL_DRY_RUN`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_ENCRYPTION`
- `MAIL_FROM`
- `MAIL_FROM_NAME`
- `MAIL_RECIPIENT`
- `TURNSTILE_SITE_KEY`
- `TURNSTILE_SECRET_KEY`
- `CONTACT_MIN_FILL_SECONDS`
- `CONTACT_SESSION_MAX_ATTEMPTS`
- `CONTACT_SESSION_WINDOW_SECONDS`
- `CONTACT_IP_MAX_ATTEMPTS`
- `CONTACT_IP_WINDOW_SECONDS`
- `CONTACT_RATE_LIMIT_KEY`
- `CONTACT_RATE_LIMIT_DIR`
- `TRUST_CLOUDFLARE_IP_HEADER`

## Valores predeterminados de protección

- Tiempo mínimo de llenado: 3 segundos.
- Límite por sesión: 4 intentos en 10 minutos.
- Límite por IP: 12 intentos en 15 minutos.
- Almacenamiento por IP: archivos JSON con bloqueo exclusivo en el directorio temporal del servidor.
- Las IP se transforman a un hash; se recomienda configurar `CONTACT_RATE_LIMIT_KEY` con un valor aleatorio privado.

El límite por archivos funciona en un solo servidor. Si el sitio se distribuye en varios servidores, deberá sustituirse por un almacén compartido. `TRUST_CLOUDFLARE_IP_HEADER` debe activarse únicamente cuando el origen acepte tráfico exclusivamente a través de Cloudflare.

## Turnstile

Cree un widget administrado para `servicombasculas.com.mx`. La clave pública se asigna a `TURNSTILE_SITE_KEY`; la clave secreta se asigna a `TURNSTILE_SECRET_KEY`. La validación del token en el servidor es obligatoria y falla de forma segura si la configuración o el servicio no están disponibles.

Para pruebas locales pueden usarse únicamente las claves de prueba oficiales de Cloudflare. Nunca utilice esas claves en producción.

Referencias oficiales:

- Pruebas: https://developers.cloudflare.com/turnstile/troubleshooting/testing/
- Validación en servidor: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/

## Correo

`MAIL_FROM` debe ser una cuenta autorizada de `servicombasculas.com.mx`. El correo del visitante se usa únicamente como `Reply-To`. Active `MAIL_DRY_RUN=true` durante pruebas locales para impedir cualquier conexión SMTP.

## Publicación posterior

1. Conservar un respaldo de los archivos actuales del hosting.
2. Crear el widget Turnstile y restringirlo al dominio de producción.
3. Configurar variables de entorno o un `config.local.php` privado en el servidor.
4. Mantener `MAIL_DRY_RUN=true` durante una comprobación inicial sin correo.
5. Verificar el formulario en móvil y escritorio.
6. Cambiar `MAIL_DRY_RUN=false` únicamente para la prueba final autorizada.
7. Confirmar recepción, remitente y Reply-To.
8. Revisar los registros del servidor sin exponerlos públicamente.
9. Si falla, restaurar los archivos respaldados y la configuración privada anterior.

La contraseña SMTP que anteriormente aparecía en `config.php` debe rotarse, porque permanece en el historial de Git. Este cambio no reescribe el historial.
