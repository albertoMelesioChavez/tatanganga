<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/stripe/portal.php'));
$PAGE->set_title('Gestionar Suscripción');
$PAGE->set_heading('Gestionar Suscripción');

$customerid = get_user_preferences('local_stripe_customer_id', null, $USER->id);
$secretkey = get_config('local_stripe', 'secretkey');

if (empty($secretkey)) {
    throw new moodle_exception('error', 'local_stripe', '', null, 'Stripe no está configurado correctamente.');
}

/**
 * Helper: create a Stripe billing portal session for a given customer ID.
 * Returns the response array or throws on HTTP error.
 */
function local_stripe_create_portal_session(
    string $customerid,
    string $secretkey,
    string $returnurl,
    string $locale
): array {
    $postdata = [
        'customer'   => $customerid,
        'return_url' => $returnurl,
        'locale' => $locale,
    ];

    $ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $curlerror = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('No fue posible conectar con Stripe: ' . $curlerror);
    }

    $decoded = json_decode($response, true);

    if ($httpcode !== 200 && $httpcode !== 201) {
        $errormsg = $decoded['error']['message'] ?? 'Error creating portal session';
        throw new Exception($errormsg, $httpcode);
    }

    if (!is_array($decoded)) {
        throw new Exception('Stripe devolvió una respuesta inválida al crear el portal.');
    }

    return $decoded;
}

/**
 * Redirect immediately to a Stripe-hosted Billing Portal session.
 *
 * A header is preferred, while the HTML fallbacks cover hosts where output or
 * debugging prevented PHP from sending the redirect header.
 */
function local_stripe_redirect_to_billing_portal(string $url): never {
    $parts = parse_url($url);
    $scheme = strtolower($parts['scheme'] ?? '');
    $host = strtolower($parts['host'] ?? '');

    if ($scheme !== 'https' || ($host !== 'billing.stripe.com' && !str_ends_with($host, '.stripe.com'))) {
        throw new Exception('Stripe devolvió una URL de portal inválida.');
    }

    \core\session\manager::write_close();

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: ' . $url, true, 303);
    }

    $safeurl = s($url);
    $jsonurl = json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<!doctype html><html><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $safeurl . '">';
    echo '<title>Stripe</title></head><body>';
    echo '<script>window.location.replace(' . $jsonurl . ');</script>';
    echo '<a href="' . $safeurl . '">Continuar a Stripe</a>';
    echo '</body></html>';
    exit;
}

/**
 * Helper: search for a Stripe customer by email and return the first customer ID found.
 * Returns null if no customer is found.
 */
function local_stripe_find_live_customer_by_email(string $email, string $secretkey): ?string {
    $url = 'https://api.stripe.com/v1/customers?email=' . rawurlencode($email) . '&limit=100';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $curlerror = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpcode !== 200) {
        error_log('Stripe portal: customer lookup failed. ' . ($curlerror ?: 'HTTP ' . $httpcode));
        return null;
    }

    $data = json_decode($response, true);

    // Return the first matching customer that has at least one active subscription.
    if (!empty($data['data'])) {
        foreach ($data['data'] as $customer) {
            // Prefer customers with active subscriptions.
            $suburl = 'https://api.stripe.com/v1/subscriptions?customer=' .
                rawurlencode($customer['id']) . '&status=all&limit=100';
            $ch2 = curl_init($suburl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secretkey]);
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            $subresponse = curl_exec($ch2);
            curl_close($ch2);
            $subdata = json_decode($subresponse, true);
            foreach ($subdata['data'] ?? [] as $subscription) {
                if (in_array($subscription['status'] ?? '', ['active', 'trialing'], true)) {
                    return $customer['id'];
                }
            }
        }
        // Fallback: return first customer even without active subscription
        // (so they can at least see their billing history).
        return $data['data'][0]['id'];
    }

    return null;
}

// Older subscriptions may have granted the Moodle role without saving the
// Stripe customer ID. Recover it before deciding that billing is unavailable.
if (empty($customerid)) {
    $customerid = local_stripe_find_live_customer_by_email($USER->email, $secretkey);
    if (!empty($customerid)) {
        local_stripe_store_customer_id($USER->id, $customerid);
    }
}

if (empty($customerid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification('No encontramos datos de facturación para tu cuenta.', 'warning');
    echo $OUTPUT->continue_button(new moodle_url('/local/stripe/plans.php'));
    echo $OUTPUT->footer();
    exit;
}

$returnurl = $CFG->wwwroot . '/my/';
$locale = local_stripe_get_stripe_locale();

try {
    $session = local_stripe_create_portal_session($customerid, $secretkey, $returnurl, $locale);

    if (empty($session['url'])) {
        throw new Exception('No portal URL returned from Stripe');
    }

    error_log("Stripe portal: redirecting Moodle user {$USER->id} to Stripe Billing Portal.");
    local_stripe_redirect_to_billing_portal($session['url']);

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
                $session = local_stripe_create_portal_session($livecustomerid, $secretkey, $returnurl, $locale);
                if (!empty($session['url'])) {
                    error_log(
                        "Stripe portal: redirecting recovered Moodle user {$USER->id} to Stripe Billing Portal."
                    );
                    local_stripe_redirect_to_billing_portal($session['url']);
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
