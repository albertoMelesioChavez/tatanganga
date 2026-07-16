<?php
// Stripe webhook endpoint.

define('NO_DEBUG_DISPLAY', true);
define('CLI_SCRIPT', false);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$payload = file_get_contents('php://input');
$sigheader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhooksecret = get_config('local_stripe', 'webhooksecret');

if (empty($webhooksecret)) {
    http_response_code(500);
    echo 'Webhook secret not configured';
    exit;
}

if (!local_stripe_verify_signature($payload, $sigheader, $webhooksecret)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$event = json_decode($payload, true);
if (empty($event['id']) || empty($event['type'])) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$type = $event['type'];
$data = $event['data']['object'] ?? [];
$eventid = $event['id'];

if (local_stripe_event_processed($eventid)) {
    http_response_code(200);
    echo 'ok';
    exit;
}

$userid = null;
$handled = false;

switch ($type) {
    case 'checkout.session.completed':
        $customerid = $data['customer'] ?? null;
        $userid = 0;
        $identification_method = 'unknown';

        // Grant access only for a completed subscription Checkout that has
        // either been paid or legitimately requires no payment (for example,
        // a configured trial). Never activate access from another Checkout
        // mode or an unpaid session.
        $checkoutmode = $data['mode'] ?? '';
        $checkoutstatus = $data['status'] ?? '';
        $paymentstatus = $data['payment_status'] ?? '';
        if ($checkoutmode !== 'subscription' || $checkoutstatus !== 'complete' ||
            !in_array($paymentstatus, ['paid', 'no_payment_required'], true)) {
            error_log(
                "Stripe webhook: ignored incomplete Checkout session {$eventid} " .
                "(mode={$checkoutmode}, status={$checkoutstatus}, payment={$paymentstatus})"
            );
            $handled = true;
            break;
        }
        
        // Priority 1: Try to get userid from client_reference_id (most reliable)
        if (!empty($data['client_reference_id'])) {
            $userid = (int)$data['client_reference_id'];
            $identification_method = 'client_reference_id';
            error_log("Stripe webhook: User identified by client_reference_id: {$userid}");
        }
        
        // Priority 2: Try metadata userid
        if (!$userid && !empty($data['metadata']['userid'])) {
            $userid = (int)$data['metadata']['userid'];
            $identification_method = 'metadata';
            error_log("Stripe webhook: User identified by metadata: {$userid}");
        }
        
        // Priority 3: Fallback to customer email (less reliable)
        if (!$userid && $customerid) {
            $secretkey = get_config('local_stripe', 'secretkey');
            if ($secretkey) {
                $email = local_stripe_get_customer_email($customerid, $secretkey);
                if ($email) {
                    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
                    if ($user) {
                        $userid = $user->id;
                        $identification_method = 'email_fallback';
                        error_log("Stripe webhook: User identified by email fallback: {$email} (ID: {$userid})");
                    } else {
                        error_log("Stripe webhook: No user found with email: {$email}");
                    }
                }
            }
        }
        
        // Process subscription
        if ($userid > 0) {
            // Verify user exists and is not deleted
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            if ($user) {
                local_stripe_assign_suscriptor_role($userid);
                if ($customerid) {
                    local_stripe_store_customer_id($userid, $customerid);
                }
                error_log("Stripe webhook: Successfully processed subscription for user {$userid} via {$identification_method}");
            } else {
                error_log("Stripe webhook: User {$userid} not found or deleted");
            }
        } else {
            error_log("Stripe webhook: Could not determine userid for checkout session. Customer ID: " . ($customerid ?? 'none'));
        }
        $handled = true;
        break;

    case 'invoice.payment_failed':
    case 'customer.subscription.deleted':
    case 'customer.subscription.updated':
        $customerid = $data['customer'] ?? null;
        $status = $data['status'] ?? null;
        if ($customerid) {
            $userid = local_stripe_find_user_by_customer($customerid);
            if ($userid) {
                if ($type === 'invoice.payment_failed' || $type === 'customer.subscription.deleted' || ($status !== 'active' && $status !== 'trialing')) {
                    local_stripe_remove_suscriptor_role($userid);
                } else if ($status === 'active' || $status === 'trialing') {
                    local_stripe_assign_suscriptor_role($userid);
                }
            }
        }
        $handled = true;
        break;
}

if ($handled) {
    local_stripe_mark_event_processed($eventid, $type, $userid);
}

http_response_code(200);
echo 'ok';

/**
 * Verify Stripe signature header.
 *
 * @param string $payload
 * @param string $sigheader
 * @param string $secret
 * @return bool
 */
function local_stripe_verify_signature(string $payload, string $sigheader, string $secret): bool {
    if (empty($sigheader)) {
        return false;
    }
    $parts = explode(',', $sigheader);
    $timestamp = null;
    $signatures = [];
    foreach ($parts as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
        if ($k === 't') {
            $timestamp = $v;
        } elseif ($k === 'v1' && !empty($v)) {
            $signatures[] = $v;
        }
    }
    if (!$timestamp || empty($signatures) || !ctype_digit($timestamp)) {
        return false;
    }
    // Stripe's default tolerance is five minutes. Reject stale signatures to
    // prevent a captured request from being replayed later.
    if (abs(time() - (int)$timestamp) > 300) {
        return false;
    }
    $signedpayload = $timestamp . '.' . $payload;
    $computed = hash_hmac('sha256', $signedpayload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($computed, $signature)) {
            return true;
        }
    }
    return false;
}
