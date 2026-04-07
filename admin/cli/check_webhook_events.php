<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

echo "=== CHECKING STRIPE WEBHOOK EVENTS ===\n\n";

// Get Stripe configuration
$secret_key = get_config('local_stripe', 'secretkey');
if (empty($secret_key)) {
    cli_error("ERROR: Stripe secret key is not configured");
}

// Check mode
$mode = (strpos($secret_key, 'sk_test_') === 0) ? 'TEST' : 'LIVE';
echo "Current mode: $mode\n\n";

// Initialize Stripe
require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
\Stripe\Stripe::setApiKey($secret_key);

try {
    // Get events from the last 7 days
    $events = \Stripe\Event::all([
        'limit' => 100,
        'type' => 'checkout.session.completed',
        'created' => [
            'gte' => strtotime('-7 days'),
        ],
    ]);
    
    echo "Found " . count($events->data) . " checkout.session.completed events in the last 7 days\n\n";
    
    if (empty($events->data)) {
        echo "No events found. This might mean:\n";
        echo "1. No payments were made in the last 7 days\n";
        echo "2. You're in the wrong mode (TEST vs LIVE)\n";
        exit(0);
    }
    
    echo "=== RECENT CHECKOUT EVENTS ===\n\n";
    
    foreach ($events->data as $event) {
        $session = $event->data->object;
        $created = date('Y-m-d H:i:s', $event->created);
        
        echo "Event ID: {$event->id}\n";
        echo "  Created: {$created}\n";
        echo "  Customer ID: " . ($session->customer ?? 'N/A') . "\n";
        
        // Try to get customer email
        if (!empty($session->customer)) {
            try {
                $customer = \Stripe\Customer::retrieve($session->customer);
                echo "  Customer Email: {$customer->email}\n";
                
                // Check if user exists in Moodle
                $user = $DB->get_record('user', ['email' => $customer->email, 'deleted' => 0]);
                if ($user) {
                    echo "  Moodle User: Found (ID: {$user->id}, {$user->firstname} {$user->lastname})\n";
                    
                    // Check if has role
                    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
                    $context = context_system::instance();
                    $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
                    echo "  Has subscriber role: " . ($has_role ? 'Yes ✓' : 'No ✗') . "\n";
                    
                    // Check if has customer ID stored
                    $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
                    echo "  Customer ID stored: " . ($stored_customer_id ? 'Yes ✓' : 'No ✗') . "\n";
                } else {
                    echo "  Moodle User: NOT FOUND ✗\n";
                }
            } catch (Exception $e) {
                echo "  Error retrieving customer: " . $e->getMessage() . "\n";
            }
        }
        
        echo "  ---\n";
    }
    
} catch (Exception $e) {
    cli_error("ERROR: " . $e->getMessage());
}
