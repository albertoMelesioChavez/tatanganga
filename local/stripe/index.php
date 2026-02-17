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
    $messages[] = html_writer::div('Pago recibido. Tu suscripción se activará en unos momentos.', 'alert alert-success');
}
if (optional_param('cancel', 0, PARAM_BOOL)) {
    $messages[] = html_writer::div('Pago cancelado. Puedes intentarlo de nuevo.', 'alert alert-warning');
}

if (empty($secretkey) || empty($priceid)) {
    $messages[] = html_writer::div('Stripe no está configurado. Contacta a soporte.', 'alert alert-danger');
}

if (optional_param('start', 0, PARAM_BOOL) && !empty($secretkey) && !empty($priceid)) {
    $sessionurl = local_stripe_create_checkout_session(
        $secretkey,
        $priceid,
        $successurl,
        $cancelurl,
        $USER
    );
    if ($sessionurl) {
        redirect($sessionurl);
    } else {
        $messages[] = html_writer::div('No se pudo iniciar el pago. Intenta más tarde.', 'alert alert-danger');
    }
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

/**
 * Create a Stripe checkout session.
 *
 * @param string $secretkey
 * @param string $priceid
 * @param string $successurl
 * @param string $cancelurl
 * @param stdClass $user
 * @return string|null
 */
function local_stripe_create_checkout_session(string $secretkey, string $priceid, string $successurl, string $cancelurl, stdClass $user): ?string {
    $payload = http_build_query([
        'mode' => 'subscription',
        'line_items[0][price]' => $priceid,
        'line_items[0][quantity]' => 1,
        'success_url' => $successurl,
        'cancel_url' => $cancelurl,
        'client_reference_id' => $user->id,
        'customer_email' => $user->email,
        'metadata[userid]' => $user->id,
    ]);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretkey,
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        $data = json_decode($response, true);
        return $data['url'] ?? null;
    }

    return null;
}
