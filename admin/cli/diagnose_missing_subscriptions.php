<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/stripe/lib.php');

echo "=== DIAGNOSING MISSING SUBSCRIPTIONS ===\n\n";

// Get Stripe configuration
$secret_key = get_config('local_stripe', 'secretkey');
if (empty($secret_key)) {
    cli_error("ERROR: Stripe secret key is not configured");
}

// Check if we're in test or live mode
$mode = (strpos($secret_key, 'sk_test_') === 0) ? 'TEST' : 'LIVE';
echo "Current mode: $mode\n\n";

// Initialize Stripe
require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
\Stripe\Stripe::setApiKey($secret_key);

try {
    // Fetch all active subscriptions from Stripe
    echo "Fetching active subscriptions from Stripe...\n";
    $all_subscriptions = [];
    $has_more = true;
    $starting_after = null;
    
    while ($has_more) {
        $params = [
            'status' => 'active',
            'limit' => 100,
        ];
        
        if ($starting_after) {
            $params['starting_after'] = $starting_after;
        }
        
        $subscriptions = \Stripe\Subscription::all($params);
        $all_subscriptions = array_merge($all_subscriptions, $subscriptions->data);
        
        $has_more = $subscriptions->has_more;
        if ($has_more && !empty($subscriptions->data)) {
            $starting_after = end($subscriptions->data)->id;
        }
    }
    
    echo "Found " . count($all_subscriptions) . " active subscriptions in Stripe\n\n";
    
    // Get subscriber role
    $roleid = local_stripe_get_suscriptor_role_id();
    $context = context_system::instance();
    
    $missing = [];
    $synced = [];
    $not_registered = [];
    
    foreach ($all_subscriptions as $subscription) {
        // Get customer details
        $customer = \Stripe\Customer::retrieve($subscription->customer);
        $email = $customer->email;
        $customer_id = $customer->id;
        
        // Find user in Moodle
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        
        if (!$user) {
            $not_registered[] = [
                'email' => $email,
                'customer_id' => $customer_id,
                'subscription_id' => $subscription->id,
                'created' => date('Y-m-d H:i:s', $subscription->created),
            ];
            continue;
        }
        
        // Check if user has role
        $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
        
        // Check if customer ID is stored
        $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
        
        if (!$has_role || $stored_customer_id !== $customer_id) {
            $missing[] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $email,
                'fullname' => fullname($user),
                'has_role' => $has_role ? 'Yes' : 'No',
                'stored_customer_id' => $stored_customer_id ?: 'None',
                'stripe_customer_id' => $customer_id,
                'subscription_created' => date('Y-m-d H:i:s', $subscription->created),
            ];
        } else {
            $synced[] = $email;
        }
    }
    
    // Display results
    echo "=== RESULTS ===\n\n";
    
    echo "✓ Properly synced: " . count($synced) . " users\n";
    echo "✗ Missing role/customer ID: " . count($missing) . " users\n";
    echo "⚠ Not registered in Moodle: " . count($not_registered) . " users\n\n";
    
    if (!empty($missing)) {
        echo "=== USERS WITH ACTIVE SUBSCRIPTION BUT MISSING ROLE/CUSTOMER ID ===\n\n";
        foreach ($missing as $user) {
            echo "User: {$user['fullname']} ({$user['email']})\n";
            echo "  User ID: {$user['user_id']}\n";
            echo "  Has role: {$user['has_role']}\n";
            echo "  Stored Customer ID: {$user['stored_customer_id']}\n";
            echo "  Stripe Customer ID: {$user['stripe_customer_id']}\n";
            echo "  Subscription created: {$user['subscription_created']}\n";
            echo "  ---\n";
        }
        echo "\n";
    }
    
    if (!empty($not_registered)) {
        echo "=== USERS WITH ACTIVE SUBSCRIPTION BUT NOT REGISTERED IN MOODLE ===\n\n";
        foreach ($not_registered as $user) {
            echo "Email: {$user['email']}\n";
            echo "  Customer ID: {$user['customer_id']}\n";
            echo "  Subscription ID: {$user['subscription_id']}\n";
            echo "  Created: {$user['created']}\n";
            echo "  ---\n";
        }
        echo "\n";
    }
    
    // Provide fix command
    if (!empty($missing)) {
        echo "=== TO FIX MISSING USERS ===\n\n";
        echo "Run the following command to sync these users:\n";
        echo "php admin/cli/sync_stripe_subscriptions.php --execute\n\n";
        
        echo "Or sync them individually:\n";
        foreach ($missing as $user) {
            echo "php admin/cli/fix_missing_subscriber_roles.php --email={$user['email']} --confirm\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    cli_error("ERROR: " . $e->getMessage());
}
