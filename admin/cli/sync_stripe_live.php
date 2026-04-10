<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/stripe/lib.php');

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'execute' => false],
    ['h' => 'help', 'e' => 'execute']
);

if ($options['help']) {
    echo "Sync active Stripe subscriptions with Moodle roles.\n\n";
    echo "Options:\n";
    echo "  --execute     Execute the sync (dry-run by default)\n";
    echo "  -h, --help    Print this help\n\n";
    echo "Example:\n";
    echo "  php admin/cli/sync_stripe_live.php --execute\n";
    exit(0);
}

$execute = $options['execute'];
$dryrun = !$execute;

if ($dryrun) {
    echo "DRY RUN MODE - No changes will be made\n";
    echo "Use --execute to apply changes\n\n";
}

$secretkey = get_config('local_stripe', 'secretkey');
if (empty($secretkey)) {
    cli_error('Stripe secret key not configured');
}

echo "Fetching active subscriptions from Stripe...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/subscriptions?status=active&limit=100');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $secretkey . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200) {
    cli_error("Failed to fetch subscriptions from Stripe: HTTP $httpcode");
}

$data = json_decode($response, true);
if (!isset($data['data'])) {
    cli_error('Invalid response from Stripe API');
}

$subscriptions = $data['data'];
echo "Found " . count($subscriptions) . " active subscriptions in Stripe\n\n";

$assigned = 0;
$skipped = 0;
$notfound = 0;

foreach ($subscriptions as $sub) {
    $customerid = $sub['customer'];
    $subid = $sub['id'];
    
    // Try to find user by customer ID
    $userid = local_stripe_find_user_by_customer($customerid);
    
    if (!$userid) {
        // Try metadata
        if (!empty($sub['metadata']['moodle_userid'])) {
            $userid = (int)$sub['metadata']['moodle_userid'];
        }
    }
    
    if (!$userid) {
        echo "⚠️  Subscription $subid (customer $customerid) - User not found in Moodle\n";
        $notfound++;
        continue;
    }
    
    $user = $DB->get_record('user', ['id' => $userid]);
    if (!$user) {
        echo "⚠️  User ID $userid not found\n";
        $notfound++;
        continue;
    }
    
    // Check if user already has role
    $hasrole = local_stripe_user_has_suscriptor_role($userid);
    
    if ($hasrole) {
        echo "✓  {$user->username} (ID: $userid) - Already has student_suscriptor role\n";
        $skipped++;
        continue;
    }
    
    if ($dryrun) {
        echo "→  {$user->username} (ID: $userid) - Would assign student_suscriptor role\n";
    } else {
        local_stripe_assign_suscriptor_role($userid);
        // Store customer ID if not stored
        if (!local_stripe_find_user_by_customer($customerid)) {
            local_stripe_store_customer_id($userid, $customerid);
        }
        echo "✅ {$user->username} (ID: $userid) - Assigned student_suscriptor role\n";
    }
    $assigned++;
}

echo "\n=== Summary ===\n";
echo "Total subscriptions: " . count($subscriptions) . "\n";
echo "Assigned/Would assign: $assigned\n";
echo "Already had role: $skipped\n";
echo "User not found: $notfound\n";

if ($dryrun) {
    echo "\nRun with --execute to apply changes\n";
}
