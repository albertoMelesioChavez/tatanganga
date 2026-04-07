<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/cancel.php'));
$PAGE->set_title('Pago Cancelado');
$PAGE->set_heading('Pago Cancelado');

echo $OUTPUT->header();

?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fa fa-times-circle text-warning" style="font-size: 72px;"></i>
                    </div>
                    <h2 class="card-title">Pago Cancelado</h2>
                    <p class="lead">Has cancelado el proceso de suscripción.</p>
                    
                    <div class="alert alert-info mt-4">
                        <p>No te preocupes, puedes intentar de nuevo cuando estés listo.</p>
                        <p class="mb-0">Si tuviste algún problema durante el proceso, por favor contacta a soporte.</p>
                    </div>
                    
                    <div class="mt-4">
                        <a href="<?php echo $CFG->wwwroot; ?>/local/stripe/subscribe.php" class="btn btn-primary btn-lg">
                            <i class="fa fa-credit-card"></i> Intentar de nuevo
                        </a>
                        <a href="<?php echo $CFG->wwwroot; ?>" class="btn btn-secondary btn-lg">
                            <i class="fa fa-home"></i> Volver al inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

echo $OUTPUT->footer();
