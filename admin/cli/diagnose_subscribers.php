<?php
/**
 * Diagnostic script for subscriber issues
 * 
 * Run: php admin/cli/diagnose_subscribers.php
 */

define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

// Get the student_suscriptor role
$suscriptor_role = $DB->get_record('role', ['shortname' => 'student_suscriptor']);
if (!$suscriptor_role) {
    cli_error("Role 'student_suscriptor' not found!");
}

echo "=== SUBSCRIBER DIAGNOSTIC REPORT ===\n\n";
echo "Role ID: {$suscriptor_role->id}\n";
echo "Role Name: {$suscriptor_role->name}\n\n";

// Get all users with student_suscriptor role
$sql = "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname, u.confirmed, u.suspended, u.deleted,
               ra.contextid, ra.timemodified as role_assigned_time
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        WHERE ra.roleid = :roleid
        ORDER BY ra.timemodified DESC";

$subscribers = $DB->get_records_sql($sql, ['roleid' => $suscriptor_role->id]);

echo "Total subscribers found: " . count($subscribers) . "\n\n";

if (empty($subscribers)) {
    echo "No subscribers found. This might be the problem!\n";
    exit(0);
}

echo "=== SUBSCRIBER DETAILS ===\n\n";

foreach ($subscribers as $user) {
    echo "----------------------------------------\n";
    echo "User ID: {$user->id}\n";
    echo "Username: {$user->username}\n";
    echo "Email: {$user->email}\n";
    echo "Name: {$user->firstname} {$user->lastname}\n";
    echo "Email Confirmed: " . ($user->confirmed ? 'YES' : 'NO - UNCONFIRMED') . "\n";
    echo "Suspended: " . ($user->suspended ? 'YES' : 'NO') . "\n";
    echo "Deleted: " . ($user->deleted ? 'YES' : 'NO') . "\n";
    echo "Role Assigned: " . userdate($user->role_assigned_time) . "\n";
    echo "Context ID: {$user->contextid}\n";
    
    // Check if context is system context
    $context = context::instance_by_id($user->contextid);
    echo "Context Level: " . get_string('context' . $context->contextlevel, 'core') . "\n";
    
    // Check Stripe customer ID
    $customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
    echo "Stripe Customer ID: " . ($customer_id ? $customer_id : 'NOT SET') . "\n";
    
    // Check course enrolments
    $enrolments = $DB->get_records_sql("
        SELECT c.id, c.shortname, c.fullname
        FROM {course} c
        JOIN {enrol} e ON e.courseid = c.id
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        WHERE ue.userid = :userid AND c.id > 1
        ORDER BY c.id
    ", ['userid' => $user->id]);
    
    echo "Enrolled in " . count($enrolments) . " courses\n";
    if (!empty($enrolments)) {
        foreach ($enrolments as $course) {
            echo "  - [{$course->id}] {$course->shortname}: {$course->fullname}\n";
        }
    }
    
    echo "\n";
}

echo "\n=== RECENT STRIPE WEBHOOK ACTIVITY ===\n";
echo "Check error_log for entries containing 'Stripe:'\n";
echo "grep 'Stripe:' /path/to/error_log | tail -20\n\n";

echo "=== USERS WITH UNCONFIRMED EMAIL ===\n";
$unconfirmed = $DB->get_records_sql("
    SELECT u.id, u.username, u.email, u.firstname, u.lastname
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    WHERE ra.roleid = :roleid AND u.confirmed = 0
", ['roleid' => $suscriptor_role->id]);

if (empty($unconfirmed)) {
    echo "All subscribers have confirmed their email.\n";
} else {
    echo "Found " . count($unconfirmed) . " subscribers with unconfirmed email:\n";
    foreach ($unconfirmed as $user) {
        echo "  - [{$user->id}] {$user->email} ({$user->firstname} {$user->lastname})\n";
    }
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. Check if webhook secret is configured: Site admin > Plugins > Local plugins > Stripe\n";
echo "2. Verify Stripe webhook is sending events to: https://tatanganga.cloud/local/stripe/webhook.php\n";
echo "3. Check Stripe dashboard for webhook delivery status\n";
echo "4. Review error logs for 'Stripe:' entries\n";
echo "5. If users paid but don't have the role, manually assign it or re-trigger webhook\n\n";
