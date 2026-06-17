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
    echo "Remove student_suscriptor role from users without active Stripe subscription.\n\n";
    echo "Options:\n";
    echo "  --execute     Execute the cleanup (dry-run by default)\n";
    echo "  -h, --help    Print this help\n\n";
    echo "Example:\n";
    echo "  php admin/cli/cleanup_expired_subscriptions.php --execute\n";
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

$activecustomerids = [];
$has_more = true;
$starting_after = null;
$total_fetched = 0;

while ($has_more) {
    $url = 'https://api.stripe.com/v1/subscriptions?status=active&limit=100';
    if ($starting_after) {
        $url .= '&starting_after=' . $starting_after;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
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
    
    foreach ($data['data'] as $sub) {
        $activecustomerids[] = $sub['customer'];
    }
    
    $total_fetched += count($data['data']);
    $has_more = $data['has_more'] ?? false;
    if ($has_more && !empty($data['data'])) {
        $last_sub = end($data['data']);
        $starting_after = $last_sub['id'];
    }
}

echo "Found " . $total_fetched . " active subscriptions in Stripe\n";
echo "Active customer IDs: " . count($activecustomerids) . "\n\n";

// Get all users with student_suscriptor role
$context = context_system::instance();
$role = $DB->get_record('role', ['shortname' => 'student_suscriptor']);
if (!$role) {
    cli_error('Role student_suscriptor not found');
}

$sql = "SELECT DISTINCT u.id, u.username, u.email, up.value as customer_id
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        LEFT JOIN {user_preferences} up ON up.userid = u.id AND up.name = 'local_stripe_customer_id'
        WHERE ra.roleid = :roleid AND ctx.id = :contextid";

$users = $DB->get_records_sql($sql, ['roleid' => $role->id, 'contextid' => $context->id]);

echo "Users with student_suscriptor role in Moodle: " . count($users) . "\n\n";

$removed = 0;
$kept = 0;
$nocustomerid = 0;

foreach ($users as $user) {
    if (empty($user->customer_id)) {
        echo "⚠️  {$user->username} (ID: {$user->id}) - No customer_id stored, skipping\n";
        $nocustomerid++;
        continue;
    }
    
    // Check if customer has active subscription
    if (in_array($user->customer_id, $activecustomerids)) {
        echo "✓  {$user->username} (ID: {$user->id}) - Has active subscription\n";
        $kept++;
        continue;
    }
    
    // User has role but no active subscription
    if ($dryrun) {
        echo "→  {$user->username} (ID: {$user->id}) - Would remove student_suscriptor role (no active subscription)\n";
    } else {
        local_stripe_remove_suscriptor_role($user->id);
        echo "❌ {$user->username} (ID: {$user->id}) - Removed student_suscriptor role (no active subscription)\n";
    }
    $removed++;
}

echo "\n=== Summary ===\n";
echo "Total users with role: " . count($users) . "\n";
echo "Kept (active subscription): $kept\n";
echo "Removed/Would remove (no active subscription): $removed\n";
echo "Skipped (no customer_id): $nocustomerid\n";

if ($dryrun) {
    echo "\nRun with --execute to apply changes\n";
}
