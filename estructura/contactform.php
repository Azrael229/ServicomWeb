<?php
declare(strict_types=1);

if (!defined('SERVICOM_APP_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

$escapeContact = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!-- Contact -->
<div id="contact" class="form-1">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <h2 class="h2-heading">Contacto</h2>
                <p class="p-heading">Completa el formulario a continuación y uno de nuestros especialistas se pondrá en contacto contigo para proporcionarte más información detallada sobre cómo nuestros servicios de medición pueden beneficiar a tu empresa. No pierdas la oportunidad de mejorar la precisión, la calidad y la eficiencia en tu industria. ¡Contáctanos hoy mismo!</p><br>
                <ul class="list-unstyled li-space-lg">
                    <li><i class="fas fa-map-marker-alt"></i> &nbsp;Servicom, Querétaro, Qro 76116, MX</li>
                    <li><i class="fas fa-phone"></i> &nbsp;<a href="tel:4426099098">442 609 90 98</a></li>
                    <li><i class="fas fa-phone"></i> &nbsp;<a href="tel:4423601166">442 360 11 66</a></li>
                    <li><i class="fas fa-phone"></i> &nbsp;<a href="tel:4421782616">442 178 26 16</a></li>
                    <li><i class="fas fa-envelope"></i> &nbsp;<a href="mailto:contacto@servicombasculas.com.mx">contacto@servicombasculas.com.mx</a></li>
                </ul>
            </div>
        </div>

        <?php if (is_array($contactFlash)) { ?>
            <div class="row">
                <div class="col-lg-10 offset-lg-1">
                    <div class="alert alert-<?php echo $escapeContact($contactFlash['type'] ?? 'danger'); ?> alert-dismissible fade show" role="alert">
                        <?php echo $escapeContact($contactFlash['message'] ?? 'No fue posible procesar la solicitud.'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php if (!$contactConfigurationReady) { ?>
            <div class="row">
                <div class="col-lg-10 offset-lg-1">
                    <div class="alert alert-warning" role="alert">
                        El formulario se encuentra temporalmente fuera de servicio. Puede comunicarse por teléfono o correo.
                    </div>
                </div>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="POST" action="#contact" accept-charset="UTF-8">
                    <input type="hidden" name="csrf_token" value="<?php echo $escapeContact($contactCsrf); ?>">
                    <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
                        <label for="contact-website">No completar este campo</label>
                        <input id="contact-website" type="text" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="visually-hidden" for="contact-nombre">Nombre</label>
                        <input id="contact-nombre" type="text" class="form-control-input" placeholder="Nombre" required minlength="2" maxlength="100" autocomplete="name" name="nombre" value="<?php echo $escapeContact($contactOld['nombre'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="visually-hidden" for="contact-telefono">Teléfono</label>
                        <input id="contact-telefono" type="tel" class="form-control-input" placeholder="Teléfono" required minlength="7" maxlength="30" autocomplete="tel" inputmode="tel" name="telefono" value="<?php echo $escapeContact($contactOld['telefono'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="visually-hidden" for="contact-email">Correo electrónico</label>
                        <input id="contact-email" type="email" class="form-control-input" placeholder="Correo electrónico" required maxlength="254" autocomplete="email" name="email" value="<?php echo $escapeContact($contactOld['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="visually-hidden" for="contact-mensaje">Mensaje</label>
                        <textarea id="contact-mensaje" class="form-control-textarea" placeholder="Mensaje" required minlength="10" maxlength="4000" name="mensaje"><?php echo $escapeContact($contactOld['mensaje'] ?? ''); ?></textarea>
                    </div>
                    <?php if ($contactConfigurationReady) { ?>
                        <div class="form-group">
                            <div class="cf-turnstile" data-sitekey="<?php echo $escapeContact($contactTurnstileSiteKey); ?>" data-theme="auto"></div>
                        </div>
                    <?php } ?>
                    <div class="form-group">
                        <button type="submit" name="submit" class="form-control-submit-button"<?php echo $contactConfigurationReady ? '' : ' disabled'; ?>>Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if ($contactConfigurationReady) { ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php } ?>
<!-- end of contact -->
