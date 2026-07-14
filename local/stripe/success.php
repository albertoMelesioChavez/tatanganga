<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$sessionid = optional_param('session_id', '', PARAM_ALPHANUMEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/success.php'));
$PAGE->set_title('¡Suscripción Exitosa!');
$PAGE->set_heading('¡Suscripción Exitosa!');

// Immediately verify the checkout session with Stripe and assign the role.
// This ensures the role is granted even if the webhook hasn't arrived yet,
// and works identically in both TEST and LIVE mode.
$activation_status = 'pending'; // pending, success, already_active

if (!empty($sessionid)) {
    $secretkey = get_config('local_stripe', 'secretkey');
    if (!empty($secretkey)) {
        try {
            // Retrieve checkout session from Stripe API.
            $url = 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionid);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $secretkey,
            ]);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode === 200) {
                $session = json_decode($response, true);
                $paymentstatus = $session['payment_status'] ?? '';
                $customerid = $session['customer'] ?? '';
                $clientrefid = $session['client_reference_id'] ?? '';
                $metadata = $session['metadata'] ?? [];
                $sessionstatus = $session['status'] ?? '';
                $mode = $session['mode'] ?? '';

                // Only accept the completed subscription Checkout session that
                // was created for the currently authenticated Moodle user.
                if ($paymentstatus === 'paid' && $sessionstatus === 'complete' &&
                    $mode === 'subscription' && !empty($customerid)) {
                    // Determine the user recorded when this Checkout session was created.
                    $targetuserid = 0;
                    if (!empty($clientrefid) && is_numeric($clientrefid)) {
                        $targetuserid = (int)$clientrefid;
                    } else if (!empty($metadata['userid'])) {
                        $targetuserid = (int)$metadata['userid'];
                    }

                    // Never activate access from a session belonging to another user.
                    if ($targetuserid !== (int)$USER->id) {
                        // Mismatch — don't auto-assign to prevent abuse.
                        error_log("Stripe success: client_reference_id ($targetuserid) doesn't match logged-in user ($USER->id). Skipping auto-assign.");
                        $targetuserid = 0;
                    }

                    if ($targetuserid > 0) {
                        // Check if already has the role.
                        if (local_stripe_user_has_suscriptor_role($targetuserid)) {
                            $activation_status = 'already_active';
                        } else {
                            // Assign role and store customer ID.
                            local_stripe_assign_suscriptor_role($targetuserid);
                            local_stripe_store_customer_id($targetuserid, $customerid);
                            error_log("Stripe success page: Assigned role to user $targetuserid from checkout session $sessionid");
                            $activation_status = 'success';
                        }
                    }
                }
            } else {
                error_log("Stripe success page: Could not retrieve session $sessionid (HTTP $httpcode)");
            }
        } catch (Exception $e) {
            error_log("Stripe success page error: " . $e->getMessage());
        }
    }
}

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
                    
                    <?php if ($activation_status === 'success'): ?>
                    <div class="alert alert-success mt-4">
                        <h5>✅ ¡Acceso Premium activado!</h5>
                        <p>Tu acceso a todos los cursos ha sido habilitado automáticamente.</p>
                    </div>
                    <?php elseif ($activation_status === 'already_active'): ?>
                    <div class="alert alert-info mt-4">
                        <h5>✅ Tu acceso Premium ya estaba activo</h5>
                        <p>Ya tenías acceso completo a todos los cursos.</p>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mt-4">
                        <h5>¿Qué sigue?</h5>
                        <ul class="text-left">
                            <li>Tu acceso premium se activará en unos segundos</li>
                            <li>Tendrás acceso completo a todos los cursos</li>
                            <li>El banner de suscripción desaparecerá automáticamente</li>
                            <li>Tu suscripción se renovará automáticamente al finalizar el periodo contratado</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
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
