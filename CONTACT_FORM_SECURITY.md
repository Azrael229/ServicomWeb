# Protección del formulario de contacto

## Alcance

El formulario utiliza una protección local y minimalista implementada únicamente
con PHP. Cloudflare Turnstile fue retirado por completo: no hay widget, script,
claves, validación Siteverify ni dependencia de cuentas o APIs antispam.

Se conservan CSRF, validación del lado del servidor, límites de longitud,
protección CRLF, rechazo de campos inesperados, escape de HTML, remitente seguro,
`Reply-To`, mensajes públicos genéricos, registros técnicos y el patrón
POST/Redirect/GET.

## Configuración privada del correo

Los secretos SMTP se leen desde variables de entorno o desde `config.local.php`.
Copie `config.example.php` como `config.local.php`, reemplace los valores
ficticios y nunca confirme ese archivo en Git.

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

`MAIL_FROM` debe pertenecer a `servicombasculas.com.mx`. El correo del
visitante se utiliza únicamente como `Reply-To`. Para pruebas locales,
`MAIL_DRY_RUN=true` evita cualquier conexión SMTP.

## Configuración del filtro

Las reglas editables están centralizadas en
`estructura/contact_spam_config.php`. El archivo no contiene secretos.

### Dominios y terminaciones

- Para agregar un dominio, añádalo en `blocked_domains` sin `@`.
- Para agregar una terminación, añádala en `blocked_tlds` sin el punto inicial.
- Las comparaciones son exactas por etiquetas de dominio y también cubren
  subdominios. No se usan coincidencias parciales.
- Todo dominio terminado en `.ru` está bloqueado.

### Honeypot

`company_website` es un campo de texto real colocado fuera de la vista mediante
CSS, fuera del orden de tabulación, con autocompletado desactivado y oculto a
lectores de pantalla. Sólo PHP lo valida. Si contiene cualquier valor, el correo
no se envía, el bloqueo se registra y el visitante recibe un éxito aparente para
no revelar la defensa al bot.

### Idioma y alfabetos

Los alfabetos cirílico, árabe, hebreo, Han, Hiragana, Katakana y Hangul se
detectan con propiedades Unicode de PCRE. El arranque del formulario comprueba
que la instalación PHP soporte esas propiedades.

Los idiomas escritos con alfabeto latino se estiman de forma heurística:

1. Se normalizan mayúsculas y acentos.
2. Se excluyen términos técnicos autorizados.
3. Se comparan señales frecuentes del español contra señales de otros idiomas.
4. Sólo se bloquea si existen varias señales extranjeras y superan claramente a
   las españolas.

La heurística no sustituye un análisis lingüístico completo. Para autorizar un
término técnico, agréguelo en `allowed_technical_words`. Las marcas, modelos y
abreviaturas no se analizan rígidamente. Las frases extranjeras cortas evidentes
se administran en `short_foreign_phrases`.

Para agregar una frase recurrente de spam, incorpórela en `blocked_phrases`.

### Enlaces

Se permiten hasta dos enlaces. Se cuentan:

- Direcciones que comienzan con `http://` o `https://`.
- Direcciones que comienzan con `www.`.
- Dominios visibles con terminaciones web comunes.

Antes del conteo se eliminan direcciones de correo. La expresión exige etiquetas
de dominio válidas y una terminación web conocida, por lo que números telefónicos
y modelos como `IND.560-A` o `XK-200.5` no se consideran enlaces.

### Repetición

Los umbrales configurables detectan:

- Un mismo carácter repetido excesivamente.
- Una palabra consecutiva más veces de lo permitido.
- Una secuencia de varias palabras repetida consecutivamente.
- Bloques largos idénticos duplicados.

La revisión se limita al mensaje actual; no existe almacenamiento histórico.
Repeticiones naturales de términos como báscula, calibración o reparación no se
bloquean por sí solas.

## Registros

Cada bloqueo genera una entrada `[SERVICOM contact block]` con:

- Fecha y hora UTC.
- Identificador aleatorio de solicitud.
- Tipo de bloqueo.
- Dominio, alfabeto, variante de repetición o cantidad de enlaces cuando aplica.

No se registra el mensaje completo, nombre, teléfono, credenciales ni secretos.
Los registros deben permanecer fuera del directorio público.

## Mensajes públicos

Los bloqueos por dominio, idioma, enlaces, frases o repetición muestran:

> No fue posible procesar el mensaje. Puede comunicarse por llamada o WhatsApp al 442 871 2550.

El honeypot muestra un éxito aparente. Los errores de validación o correo utilizan
un mensaje genérico y nunca exponen detalles técnicos.

## Pruebas

Desde la raíz del repositorio:

```powershell
C:\xampp\php\php.exe tests\contact_form_security_test.php
```

Las pruebas no cargan PHPMailer y utilizan `MAIL_DRY_RUN=true`. Para una prueba
funcional local, configure credenciales ficticias válidas y mantenga ese modo
activo. No utilice una cuenta SMTP real.

## Riesgos de falsos positivos

La detección de idioma es deliberadamente conservadora. Una frase latina muy
corta o ambigua puede no bloquearse; esto reduce el riesgo para empresas
mexicanas que usan marcas, modelos o vocabulario técnico extranjero. Antes de
endurecer listas o umbrales, agregue casos legítimos y abusivos a las pruebas.

## Publicación y reversión

1. Respaldar los archivos y la configuración privada del hosting.
2. Configurar `config.local.php` o variables de entorno.
3. Probar con `MAIL_DRY_RUN=true` en móvil y escritorio.
4. Revisar registros sin exponerlos públicamente.
5. Autorizar por separado una prueba SMTP real.
6. Si falla, restaurar el respaldo o el commit anterior y su configuración.

La contraseña SMTP que apareció históricamente en `config.php` debe rotarse.
Este cambio no reescribe el historial.
