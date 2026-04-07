<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/subscribe.php'));
$PAGE->set_title('Suscripción Premium');
$PAGE->set_heading('Suscripción Premium');

// Check if user already has subscription
$roleid = local_stripe_get_suscriptor_role_id();
$context = context_system::instance();
$has_subscription = user_has_role_assignment($USER->id, $roleid, $context->id);

if ($has_subscription) {
    redirect(new moodle_url('/'), 'Ya tienes una suscripción activa', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Get Stripe configuration
$publishablekey = get_config('local_stripe', 'publishablekey');
$secretkey = get_config('local_stripe', 'secretkey');
$priceid = get_config('local_stripe', 'priceid');

if (empty($publishablekey) || empty($secretkey) || empty($priceid)) {
    print_error('Stripe no está configurado correctamente. Contacta al administrador.');
}

// Create checkout session using Stripe API directly (no library needed)
try {
    // Prepare nested arrays for line_items and metadata
    $postdata = [
        'client_reference_id' => (string)$USER->id,
        'customer_email' => $USER->email,
        'line_items[0][price]' => $priceid,
        'line_items[0][quantity]' => 1,
        'mode' => 'subscription',
        'success_url' => $CFG->wwwroot . '/local/stripe/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $CFG->wwwroot . '/local/stripe/cancel.php',
        'metadata[userid]' => $USER->id,
        'metadata[username]' => $USER->username,
        'metadata[email]' => $USER->email,
        'subscription_data[metadata][moodle_userid]' => $USER->id,
        'subscription_data[metadata][moodle_username]' => $USER->username,
    ];
    
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
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
        $errormsg = isset($error['error']['message']) ? $error['error']['message'] : 'Error creating checkout session';
        throw new Exception($errormsg);
    }
    
    $session = json_decode($response, true);
    
    if (empty($session['url'])) {
        throw new Exception('No checkout URL returned from Stripe');
    }
    
    // Redirect to Stripe Checkout
    redirect($session['url']);
    
} catch (Exception $e) {
    error_log('Stripe checkout error: ' . $e->getMessage());
    print_error('Error al crear la sesión de pago: ' . $e->getMessage());
}
