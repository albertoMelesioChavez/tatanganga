<?php
/**
 * Fix missing subscriber roles for users who paid
 * 
 * Run: php admin/cli/fix_missing_subscriber_roles.php --userid=123
 * Or: php admin/cli/fix_missing_subscriber_roles.php --email=user@example.com
 * Or: php admin/cli/fix_missing_subscriber_roles.php --list (to see candidates)
 */

define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once(__DIR__.'/../../local/stripe/lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'userid' => null,
        'email' => null,
        'list' => false,
        'confirm' => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($options['help']) {
    echo "Fix missing subscriber roles for users who paid

Options:
--userid=123        Assign subscriber role to user ID
--email=user@email  Assign subscriber role to user by email
--list              List users with Stripe customer ID but no subscriber role
--confirm           Actually perform the assignment (dry-run without this)
-h, --help          Print this help

Examples:
php admin/cli/fix_missing_subscriber_roles.php --list
php admin/cli/fix_missing_subscriber_roles.php --userid=123 --confirm
php admin/cli/fix_missing_subscriber_roles.php --email=user@example.com --confirm
";
    exit(0);
}

$suscriptor_role = $DB->get_record('role', ['shortname' => 'student_suscriptor']);
if (!$suscriptor_role) {
    cli_error("Role 'student_suscriptor' not found!");
}

// List mode: find users with Stripe customer ID but no subscriber role
if ($options['list']) {
    echo "=== USERS WITH STRIPE CUSTOMER ID BUT NO SUBSCRIBER ROLE ===\n\n";
    
    $sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.confirmed, up.value as customer_id
            FROM {user} u
            JOIN {user_preferences} up ON up.userid = u.id
            WHERE up.name = 'local_stripe_customer_id'
              AND u.deleted = 0
              AND NOT EXISTS (
                  SELECT 1 FROM {role_assignments} ra
                  WHERE ra.userid = u.id AND ra.roleid = :roleid
              )
            ORDER BY u.id";
    
    $users = $DB->get_records_sql($sql, ['roleid' => $suscriptor_role->id]);
    
    if (empty($users)) {
        echo "No users found with Stripe customer ID missing subscriber role.\n";
        echo "This is good - all paying customers have the role!\n";
    } else {
        echo "Found " . count($users) . " users who may need the subscriber role:\n\n";
        foreach ($users as $user) {
            echo "User ID: {$user->id}\n";
            echo "Email: {$user->email}\n";
            echo "Name: {$user->firstname} {$user->lastname}\n";
            echo "Confirmed: " . ($user->confirmed ? 'YES' : 'NO') . "\n";
            echo "Stripe Customer: {$user->customer_id}\n";
            echo "Command: php admin/cli/fix_missing_subscriber_roles.php --userid={$user->id} --confirm\n";
            echo "----------------------------------------\n";
        }
        
        echo "\nTo fix all at once, run:\n";
        foreach ($users as $user) {
            echo "php admin/cli/fix_missing_subscriber_roles.php --userid={$user->id} --confirm\n";
        }
    }
    exit(0);
}

// Get user
$user = null;
if ($options['userid']) {
    $user = $DB->get_record('user', ['id' => $options['userid'], 'deleted' => 0]);
    if (!$user) {
        cli_error("User with ID {$options['userid']} not found or deleted");
    }
} elseif ($options['email']) {
    $user = $DB->get_record('user', ['email' => $options['email'], 'deleted' => 0]);
    if (!$user) {
        cli_error("User with email {$options['email']} not found or deleted");
    }
} else {
    cli_error("Please specify --userid or --email (or use --list to see candidates)");
}

echo "=== USER INFORMATION ===\n";
echo "User ID: {$user->id}\n";
echo "Username: {$user->username}\n";
echo "Email: {$user->email}\n";
echo "Name: {$user->firstname} {$user->lastname}\n";
echo "Email Confirmed: " . ($user->confirmed ? 'YES' : 'NO') . "\n";
echo "Suspended: " . ($user->suspended ? 'YES' : 'NO') . "\n\n";

// Check if already has role
$context = context_system::instance();
if (user_has_role_assignment($user->id, $suscriptor_role->id, $context->id)) {
    echo "✓ User already has the student_suscriptor role in system context.\n";
    exit(0);
}

// Check Stripe customer ID
$customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
if ($customer_id) {
    echo "Stripe Customer ID: {$customer_id}\n";
} else {
    echo "⚠ WARNING: No Stripe customer ID found for this user.\n";
    echo "This user may not have completed a payment.\n";
}

echo "\n";

if (!$options['confirm']) {
    echo "=== DRY RUN MODE ===\n";
    echo "Would assign student_suscriptor role to user {$user->id} ({$user->email})\n";
    echo "Would enrol user in all courses\n";
    echo "\nTo actually perform this action, add --confirm flag\n";
    exit(0);
}

// Perform the assignment
echo "=== ASSIGNING SUBSCRIBER ROLE ===\n";

try {
    $success = local_stripe_assign_suscriptor_role($user->id);
    
    if ($success) {
        echo "✓ Successfully assigned student_suscriptor role to user {$user->id}\n";
        echo "✓ User enrolled in all courses\n";
        
        // Verify
        if (user_has_role_assignment($user->id, $suscriptor_role->id, $context->id)) {
            echo "✓ Verified: Role assignment confirmed\n";
        } else {
            echo "✗ ERROR: Role assignment failed verification\n";
        }
        
        echo "\nUser should now see premium features and have access to all content.\n";
        echo "They may need to log out and log back in to see changes.\n";
    } else {
        echo "✗ Failed to assign role. Check error logs for details.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
