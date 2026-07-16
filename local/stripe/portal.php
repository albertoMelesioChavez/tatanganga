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

/**
 * Helper: create a Stripe billing portal session for a given customer ID.
 * Returns the response array or throws on HTTP error.
 */
function local_stripe_create_portal_session(string $customerid, string $secretkey, string $returnurl): array {
    $postdata = [
        'customer'   => $customerid,
        'return_url' => $returnurl,
    ];

    $ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);

    $response  = curl_exec($ch);
    $httpcode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpcode !== 200 && $httpcode !== 201) {
        $errormsg = $decoded['error']['message'] ?? 'Error creating portal session';
        throw new Exception($errormsg, $httpcode);
    }

    return $decoded;
}

/**
 * Helper: search for a Stripe customer by email and return the first customer ID found.
 * Returns null if no customer is found.
 */
function local_stripe_find_live_customer_by_email(string $email, string $secretkey): ?string {
    $url = 'https://api.stripe.com/v1/customers/search?query=' . urlencode('email:"' . $email . '"') . '&limit=5';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200) {
        return null;
    }

    $data = json_decode($response, true);

    // Return the first matching customer that has at least one active subscription.
    if (!empty($data['data'])) {
        foreach ($data['data'] as $customer) {
            // Prefer customers with active subscriptions.
            $suburl = 'https://api.stripe.com/v1/subscriptions?customer=' . $customer['id'] . '&status=active&limit=1';
            $ch2 = curl_init($suburl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);
            $subresponse = curl_exec($ch2);
            curl_close($ch2);
            $subdata = json_decode($subresponse, true);
            if (!empty($subdata['data'])) {
                return $customer['id'];
            }
        }
        // Fallback: return first customer even without active subscription
        // (so they can at least see their billing history).
        return $data['data'][0]['id'];
    }

    return null;
}

$returnurl = $CFG->wwwroot . '/my/';

try {
    $session = local_stripe_create_portal_session($customerid, $secretkey, $returnurl);

    if (empty($session['url'])) {
        throw new Exception('No portal URL returned from Stripe');
    }

    redirect($session['url']);

} catch (Exception $e) {
    $errormsg = $e->getMessage();

    // Detect test/live mode mismatch: the stored customer ID belongs to the opposite mode.
    $is_mode_mismatch = (
        stripos($errormsg, 'similar object exists in test mode') !== false ||
        stripos($errormsg, 'similar object exists in live mode') !== false ||
        stripos($errormsg, 'No such customer') !== false
    );

    if ($is_mode_mismatch) {
        error_log("Stripe portal: stored customer ID '{$customerid}' is from the wrong mode. Searching by email: {$USER->email}");

        // Try to find the correct live-mode customer by email.
        $livecustomerid = local_stripe_find_live_customer_by_email($USER->email, $secretkey);

        if ($livecustomerid) {
            // Update stored customer ID to the correct live-mode one.
            local_stripe_store_customer_id($USER->id, $livecustomerid);
            error_log("Stripe portal: updated customer ID for user {$USER->id} from '{$customerid}' to '{$livecustomerid}'");

            try {
                $session = local_stripe_create_portal_session($livecustomerid, $secretkey, $returnurl);
                if (!empty($session['url'])) {
                    redirect($session['url']);
                }
            } catch (Exception $e2) {
                error_log('Stripe portal retry error: ' . $e2->getMessage());
            }
        }

        // No live customer found for this email: clear the stale test ID and show plans.
        error_log("Stripe portal: no live customer found for {$USER->email}. Clearing stale customer ID.");
        unset_user_preference('local_stripe_customer_id', $USER->id);

        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            'No encontramos una suscripción activa en modo de producción para tu cuenta. ' .
            'Si ya te suscribiste, por favor contacta a soporte. Si deseas suscribirte, elige un plan a continuación.',
            'warning'
        );
        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/stripe/plans.php'),
                'Ver planes de suscripción',
                ['class' => 'btn btn-primary']
            ),
            'text-center mt-3'
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Generic error.
    error_log('Stripe portal error: ' . $errormsg);
    echo $OUTPUT->header();
    echo $OUTPUT->notification('Error al acceder al portal de facturación: ' . $errormsg, 'error');
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
}
