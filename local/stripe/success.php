<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$sessionid = optional_param('session_id', '', PARAM_TEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/success.php'));
$PAGE->set_title('¡Suscripción Exitosa!');
$PAGE->set_heading('¡Suscripción Exitosa!');

echo $OUTPUT->header();

?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fa fa-check-circle text-success" style="font-size: 72px;"></i>
                    </div>
                    <h2 class="card-title">¡Bienvenido a Tatanganga Premium!</h2>
                    <p class="lead">Tu suscripción ha sido activada exitosamente.</p>
                    
                    <div class="alert alert-info mt-4">
                        <h5>¿Qué sigue?</h5>
                        <ul class="text-left">
                            <li>Tu acceso premium se activará en unos segundos</li>
                            <li>Tendrás acceso completo a todos los cursos</li>
                            <li>El banner de suscripción desaparecerá automáticamente</li>
                            <li>Tu suscripción se renovará automáticamente al finalizar el periodo contratado</li>
                        </ul>
                    </div>
                    
                    <?php if (!empty($sessionid)): ?>
                    <div class="alert alert-secondary mt-3">
                        <small><strong>ID de sesión:</strong> <?php echo s($sessionid); ?></small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="<?php echo $CFG->wwwroot; ?>" class="btn btn-primary btn-lg">
                            <i class="fa fa-home"></i> Ir al inicio
                        </a>
                        <a href="<?php echo $CFG->wwwroot; ?>/course/" class="btn btn-success btn-lg">
                            <i class="fa fa-book"></i> Ver mis cursos
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <p class="text-muted">
                            <small>Si no ves tu acceso premium en 1 minuto, recarga la página o contacta a soporte.</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-reload after 5 seconds to refresh user permissions
setTimeout(function() {
    window.location.href = '<?php echo $CFG->wwwroot; ?>';
}, 5000);
</script>

<?php

echo $OUTPUT->footer();
