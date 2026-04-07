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
if (empty($event['type'])) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$type = $event['type'];
$data = $event['data']['object'] ?? [];

switch ($type) {
    case 'checkout.session.completed':
        $customerid = $data['customer'] ?? null;
        $userid = 0;
        
        // Try to get userid from client_reference_id or metadata
        if (!empty($data['client_reference_id'])) {
            $userid = (int)$data['client_reference_id'];
        } elseif (!empty($data['metadata']['userid'])) {
            $userid = (int)$data['metadata']['userid'];
        }
        
        // If no userid, try to find user by customer email
        if (!$userid && $customerid) {
            require_once(__DIR__ . '/vendor/autoload.php');
            $secretkey = get_config('local_stripe', 'secretkey');
            if ($secretkey) {
                \Stripe\Stripe::setApiKey($secretkey);
                try {
                    $customer = \Stripe\Customer::retrieve($customerid);
                    if (!empty($customer->email)) {
                        $user = $DB->get_record('user', ['email' => $customer->email, 'deleted' => 0]);
                        if ($user) {
                            $userid = $user->id;
                            error_log("Stripe webhook: Found user by email: {$customer->email} (ID: {$userid})");
                        } else {
                            error_log("Stripe webhook: No user found with email: {$customer->email}");
                        }
                    }
                } catch (Exception $e) {
                    error_log("Stripe webhook: Error retrieving customer: " . $e->getMessage());
                }
            }
        }
        
        if ($userid > 0) {
            local_stripe_assign_suscriptor_role($userid);
            if ($customerid) {
                local_stripe_store_customer_id($userid, $customerid);
            }
        } else {
            error_log("Stripe webhook: Could not determine userid for checkout session");
        }
        break;

    case 'invoice.payment_failed':
    case 'customer.subscription.deleted':
    case 'customer.subscription.updated':
        $customerid = $data['customer'] ?? null;
        $status = $data['status'] ?? null;
        if ($customerid) {
            $userid = local_stripe_find_user_by_customer($customerid);
            if ($userid) {
                if ($type === 'invoice.payment_failed' || $type === 'customer.subscription.deleted' || $status !== 'active') {
                    local_stripe_remove_suscriptor_role($userid);
                }
            }
        }
        break;
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
    $signature = null;
    foreach ($parts as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
        if ($k === 't') {
            $timestamp = $v;
        } elseif ($k === 'v1') {
            $signature = $v;
        }
    }
    if (!$timestamp || !$signature) {
        return false;
    }
    $signedpayload = $timestamp . '.' . $payload;
    $computed = hash_hmac('sha256', $signedpayload, $secret);
    return hash_equals($computed, $signature);
}
