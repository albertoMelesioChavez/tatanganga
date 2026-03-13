<?php
// Custom Stripe subscription page.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/accesslib.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/stripe/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Suscripción');
$PAGE->set_heading('Suscripción');

$publishablekey = get_config('local_stripe', 'publishablekey');
$secretkey = get_config('local_stripe', 'secretkey');
$priceid = get_config('local_stripe', 'priceid');
$successurl = get_config('local_stripe', 'successurl');
$cancelurl = get_config('local_stripe', 'cancelurl');

if (empty($successurl)) {
    $successurl = (new moodle_url('/local/stripe/index.php', ['ok' => 1]))->out(false);
}
if (empty($cancelurl)) {
    $cancelurl = (new moodle_url('/local/stripe/index.php', ['cancel' => 1]))->out(false);
}

$messages = [];
if (optional_param('ok', 0, PARAM_BOOL)) {
    $homeurl = new moodle_url('/', ['redirect' => 0]);
    redirect($homeurl, 'Pago recibido. Tu suscripción se activará en unos momentos.', null, \core\output\notification::NOTIFY_SUCCESS);
}
if (optional_param('cancel', 0, PARAM_BOOL)) {
    $messages[] = html_writer::div('Pago cancelado. Puedes intentarlo de nuevo.', 'alert alert-warning');
}

if (empty($secretkey) || empty($priceid)) {
    $messages[] = html_writer::div('Stripe no está configurado. Contacta a soporte.', 'alert alert-danger');
}

if (optional_param('start', 0, PARAM_BOOL)) {
    // Redirigir directamente al Checkout Link de Stripe.
    redirect('https://buy.stripe.com/bJe00jcgo7iDdrve7p8Ra00');
}

echo $OUTPUT->header();
foreach ($messages as $msg) {
    echo $msg;
}

echo html_writer::start_div('local-stripe-subscribe');
echo html_writer::tag('h3', 'Suscripción mensual');
echo html_writer::tag('p', 'Suscríbete para desbloquear todas las clases y recursos.');

echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1]);
echo html_writer::tag('button', 'Continuar a pago', ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo html_writer::end_div();

echo $OUTPUT->footer();

