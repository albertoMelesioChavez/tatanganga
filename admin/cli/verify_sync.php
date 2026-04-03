<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

echo "=== VERIFICATION REPORT ===\n\n";

// Get role ID
$roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
if (!$roleid) {
    cli_error("ERROR: Role 'student_suscriptor' not found");
}

// Count total subscribers
$total_subscribers = $DB->count_records_sql("
    SELECT COUNT(DISTINCT ra.userid)
    FROM {role_assignments} ra
    WHERE ra.roleid = :roleid
", ['roleid' => $roleid]);

echo "✓ Total users with student_suscriptor role: $total_subscribers\n\n";

// Count users with Stripe Customer ID
$total_with_stripe = $DB->count_records('user_preferences', [
    'name' => 'local_stripe_customer_id'
]);

echo "✓ Total users with Stripe Customer ID: $total_with_stripe\n\n";

// Get list of subscribers with Stripe ID
echo "=== SUBSCRIBERS WITH STRIPE ID ===\n";
$subscribers = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, u.email, up.value as customer_id
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {user_preferences} up ON up.userid = u.id
    WHERE ra.roleid = :roleid
      AND up.name = 'local_stripe_customer_id'
      AND u.deleted = 0
    ORDER BY u.lastname, u.firstname
", ['roleid' => $roleid]);

$count = 0;
foreach ($subscribers as $sub) {
    $count++;
    echo sprintf("%2d. %-30s %-35s %s\n", 
        $count,
        fullname($sub),
        $sub->email,
        substr($sub->customer_id, 0, 20) . '...'
    );
}

echo "\n=== SUBSCRIBERS WITHOUT STRIPE ID ===\n";
$no_stripe = $DB->get_records_sql("
    SELECT u.id, u.firstname, u.lastname, u.email
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    WHERE ra.roleid = :roleid
      AND u.deleted = 0
      AND NOT EXISTS (
          SELECT 1 FROM {user_preferences} up
          WHERE up.userid = u.id
            AND up.name = 'local_stripe_customer_id'
      )
    ORDER BY u.lastname, u.firstname
", ['roleid' => $roleid]);

if (empty($no_stripe)) {
    echo "✓ All subscribers have Stripe Customer ID\n";
} else {
    foreach ($no_stripe as $user) {
        echo "  - " . fullname($user) . " ($user->email)\n";
    }
}

echo "\n=== COURSE ENROLLMENT CHECK ===\n";
// Check how many courses exist
$total_courses = $DB->count_records_sql("
    SELECT COUNT(*) FROM {course} WHERE id > 1
");
echo "Total courses: $total_courses\n";

// Sample check: verify first 3 subscribers are enrolled
$sample_users = array_slice($subscribers, 0, min(3, count($subscribers)));
foreach ($sample_users as $user) {
    $enrolled_count = $DB->count_records_sql("
        SELECT COUNT(DISTINCT c.id)
        FROM {course} c
        JOIN {enrol} e ON e.courseid = c.id
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        WHERE ue.userid = :userid AND c.id > 1
    ", ['userid' => $user->id]);
    
    echo "  " . fullname($user) . ": enrolled in $enrolled_count courses\n";
}

echo "\n=== SUMMARY ===\n";
echo "✓ Subscribers with role: $total_subscribers\n";
echo "✓ Subscribers with Stripe ID: " . count($subscribers) . "\n";
echo "✓ Subscribers without Stripe ID: " . count($no_stripe) . "\n";

if (count($subscribers) >= 29) {
    echo "\n✅ SUCCESS: At least 29 users are properly synced!\n";
} else {
    echo "\n⚠️  WARNING: Expected at least 29 synced users, found " . count($subscribers) . "\n";
}

echo "\n=== NEXT STEPS ===\n";
echo "1. Users should refresh browser (Ctrl+Shift+R)\n";
echo "2. Banner should disappear for users with student_suscriptor role\n";
echo "3. Users will auto-enroll in courses when they access them\n";
