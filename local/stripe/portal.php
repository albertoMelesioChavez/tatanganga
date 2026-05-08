<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/portal.php'));
$PAGE->set_title('Gestionar Suscripción');
$PAGE->set_heading('Gestionar Suscripción');

$customerid = get_user_preferences('local_stripe_customer_id', null, $USER->id);

if (empty($customerid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('No tienes una suscripción activa o no encontramos tus datos de facturación.', 'warning');
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
    exit;
}

$secretkey = get_config('local_stripe', 'secretkey');

if (empty($secretkey)) {
    throw new moodle_exception('error', 'local_stripe', '', null, 'Stripe no está configurado correctamente.');
}

try {
    $postdata = [
        'customer' => $customerid,
        'return_url' => $CFG->wwwroot . '/my/',
    ];
    
    $ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretkey,
    ]);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode !== 200 && $httpcode !== 201) {
        $error = json_decode($response, true);
        $errormsg = isset($error['error']['message']) ? $error['error']['message'] : 'Error creating portal session';
        throw new Exception($errormsg);
    }
    
    $session = json_decode($response, true);
    
    if (empty($session['url'])) {
        throw new Exception('No portal URL returned from Stripe');
    }
    
    // Redirect to Stripe Customer Portal
    redirect($session['url']);
    
} catch (Exception $e) {
    error_log('Stripe portal error: ' . $e->getMessage());
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Error al acceder al portal de facturación: ' . $e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
}
