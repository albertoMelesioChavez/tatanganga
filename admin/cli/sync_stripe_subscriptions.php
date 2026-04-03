<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/stripe/lib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'execute' => false,
    ),
    array('h' => 'help')
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Sync Stripe subscriptions with Moodle users

This script fetches all active subscriptions from Stripe and:
1. Finds the corresponding Moodle user by email
2. Assigns the student_suscriptor role if not already assigned
3. Stores the Stripe customer ID
4. Enrolls the user in all courses

Options:
--execute         Actually perform the sync (without this, it's a dry run)
-h, --help        Print out this help

Example:
\$ php admin/cli/sync_stripe_subscriptions.php
\$ php admin/cli/sync_stripe_subscriptions.php --execute
";
    exit(0);
}

$execute = $options['execute'];

echo "=== STRIPE SUBSCRIPTION SYNC ===\n\n";

if (!$execute) {
    echo "*** DRY RUN MODE - No changes will be made ***\n";
    echo "Use --execute to actually sync users\n\n";
}

// Get Stripe configuration
$secret_key = get_config('local_stripe', 'secretkey');
if (empty($secret_key)) {
    cli_error("ERROR: Stripe secret key is not configured");
}

echo "Fetching subscriptions from Stripe...\n";

// Initialize Stripe
require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
\Stripe\Stripe::setApiKey($secret_key);

try {
    // Fetch all active subscriptions
    $subscriptions = \Stripe\Subscription::all([
        'status' => 'active',
        'limit' => 100,
    ]);
    
    echo "Found " . count($subscriptions->data) . " active subscriptions\n\n";
    
    $synced = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($subscriptions->data as $subscription) {
        // Get customer details
        $customer = \Stripe\Customer::retrieve($subscription->customer);
        $email = $customer->email;
        $customer_id = $customer->id;
        
        echo "----------------------------------------\n";
        echo "Processing: $email\n";
        echo "Customer ID: $customer_id\n";
        
        // Find user in Moodle by email
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        
        if (!$user) {
            echo "  ✗ User not found in Moodle\n";
            $errors++;
            continue;
        }
        
        echo "  ✓ Found user: " . fullname($user) . " (ID: {$user->id})\n";
        
        // Check if user already has the role
        $roleid = local_stripe_get_suscriptor_role_id();
        $context = context_system::instance();
        $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
        
        // Check if customer ID is already stored
        $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
        
        if ($has_role && $stored_customer_id === $customer_id) {
            echo "  ✓ Already synced (has role and customer ID)\n";
            $skipped++;
            continue;
        }
        
        if ($execute) {
            // Assign role
            if (!$has_role) {
                echo "  → Assigning student_suscriptor role...\n";
                if (local_stripe_assign_suscriptor_role($user->id)) {
                    echo "  ✓ Role assigned successfully\n";
                } else {
                    echo "  ✗ Failed to assign role\n";
                    $errors++;
                    continue;
                }
            } else {
                echo "  ✓ User already has role\n";
            }
            
            // Store customer ID
            if ($stored_customer_id !== $customer_id) {
                echo "  → Storing Stripe customer ID...\n";
                set_user_preference('local_stripe_customer_id', $customer_id, $user->id);
                echo "  ✓ Customer ID stored\n";
            } else {
                echo "  ✓ Customer ID already stored\n";
            }
            
            $synced++;
        } else {
            echo "  → Would assign role and store customer ID (dry run)\n";
            $synced++;
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total subscriptions: " . count($subscriptions->data) . "\n";
    echo "Synced: $synced\n";
    echo "Already synced: $skipped\n";
    echo "Errors: $errors\n";
    
    if (!$execute && $synced > 0) {
        echo "\nRun with --execute to actually sync these users\n";
    }
    
    if ($execute && $synced > 0) {
        echo "\n✓ Sync completed successfully!\n";
        echo "\nNext steps:\n";
        echo "1. Run: php admin/cli/purge_caches.php\n";
        echo "2. Ask users to refresh their browser (Ctrl+Shift+R)\n";
    }
    
} catch (Exception $e) {
    cli_error("ERROR: " . $e->getMessage());
}
