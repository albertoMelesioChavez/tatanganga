<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

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
    echo "Fast sync Stripe subscriptions (role assignment only)

This script quickly assigns roles without enrolling in courses.
Users will be auto-enrolled when they access courses.

Options:
--execute         Actually perform the sync
-h, --help        Print out this help

Example:
\$ php admin/cli/sync_fast.php
\$ php admin/cli/sync_fast.php --execute
";
    exit(0);
}

$execute = $options['execute'];

echo "=== FAST STRIPE SYNC ===\n\n";

if (!$execute) {
    echo "*** DRY RUN MODE ***\n\n";
}

$subscriptions = [
    ['email' => 'lojarocho@gmail.com', 'customer_id' => 'cus_UGMYWJzrSfhK9e'],
    ['email' => 'so.lesito_01@hotmail.com', 'customer_id' => 'cus_UGLoIyjQmRPxO7'],
    ['email' => 'yostinalan@gmail.com', 'customer_id' => 'cus_UGLgfqTIisg1W9'],
    ['email' => 'arturprado43@gmail.com', 'customer_id' => 'cus_UGL98DHDfbSMET'],
    ['email' => 'ochoaelcolorado@gmail.com', 'customer_id' => 'cus_UGKZJ8FzmQnppn'],
    ['email' => 'alex.castellanos@outlook.com', 'customer_id' => 'cus_UGJNTce0rXo7HP'],
    ['email' => 'sheeshoo16@gmail.com', 'customer_id' => 'cus_UGIMlC5a25zAhF'],
    ['email' => 'egalfran@gmail.com', 'customer_id' => 'cus_UGIE3J6oncd5gm'],
    ['email' => 'marielc_islasm@hotmail.com', 'customer_id' => 'cus_UGHhVA6uUbVJuY'],
    ['email' => 'joankagilallman@gmail.com', 'customer_id' => 'cus_UGHTTFYc0Tp7TL'],
    ['email' => 'annyrojasgallardo5602@gmail.com', 'customer_id' => 'cus_UGGgnuExDjGUVI'],
    ['email' => 'barrenajeremias@gmail.com', 'customer_id' => 'cus_UGFqdqtDcEOvK6'],
    ['email' => 'lulugg369@yahoo.com', 'customer_id' => 'cus_UGEwN5mMWyvfg7'],
    ['email' => 'misbichitos13@hotmail.com', 'customer_id' => 'cus_UGDMN6nRnbstCJ'],
    ['email' => 'yackeline_satizabal@hotmail.com', 'customer_id' => 'cus_UGCx8WHa2a878h'],
    ['email' => 'angelaarias22@aol.com', 'customer_id' => 'cus_UGCjFX27GeXVEu'],
    ['email' => 'analiher17@gmail.coma', 'customer_id' => 'cus_UGCQ21IvJ9PSCv'],
    ['email' => 'marymouse2425@gmail.com', 'customer_id' => 'cus_UGBwixekHidDcR'],
    ['email' => 'arisvaldez0@gmail.com', 'customer_id' => 'cus_UGBkiNVKofO0C4'],
    ['email' => 'angelnana447@gmail.com', 'customer_id' => 'cus_UGAxxcjhv0UZis'],
    ['email' => 'razalas44@gmail.com', 'customer_id' => 'cus_UGAh53KYIrGgFf'],
    ['email' => 'alfonso.rj502@gmail.com', 'customer_id' => 'cus_UGASWr4KBMhbO4'],
    ['email' => 'brianavictoria2009@gmail.com', 'customer_id' => 'cus_UGAMR0S2gMMmLV'],
    ['email' => 'msrodriguezyezdo@gmail.com', 'customer_id' => 'cus_UGA9uuZ4BWTE0F'],
    ['email' => 'leiryn88@gmail.com', 'customer_id' => 'cus_UGA2qKoWwhgpTe'],
    ['email' => 'neptuno719@gmail.com', 'customer_id' => 'cus_UG9y0xWm2Mplry'],
    ['email' => 'cindirios97@gmail.com', 'customer_id' => 'cus_UG9ts4kuw7l4I9'],
    ['email' => 'zvcast7416@gmail.com', 'customer_id' => 'cus_UG9tJgfDFYDhm9'],
    ['email' => 'pablomartinez.holguin@gmail.com', 'customer_id' => 'cus_UG9kN9zEHzRKq1'],
    ['email' => 'hunter3001mar@gmail.com', 'customer_id' => 'cus_UG9bUdaIpXkWNI'],
    ['email' => 'alearcila1878@gmail.com', 'customer_id' => 'cus_UG9dDt9RCy5ndN'],
    ['email' => 'flakita_ivys@hotmail.com', 'customer_id' => 'cus_UG9YTiZuzTGV58'],
    ['email' => 'mp1oscarnowell@gmail.com', 'customer_id' => 'cus_UG9QgY06fVBEOS'],
    ['email' => 'liliana.kit@hotmail.com', 'customer_id' => 'cus_UG9Bu9OpJOD12I'],
    ['email' => 'soulderful@gmail.com', 'customer_id' => 'cus_UG8eM4XKepXX9w'],
];

// Get role ID
$roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
if (!$roleid) {
    cli_error("ERROR: Role 'student_suscriptor' not found");
}

$context = context_system::instance();
$synced = 0;
$skipped = 0;
$errors = 0;

foreach ($subscriptions as $sub) {
    $email = $sub['email'];
    $customer_id = $sub['customer_id'];
    
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    
    if (!$user) {
        $errors++;
        continue;
    }
    
    echo "Processing: " . fullname($user) . " ($email)... ";
    
    $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
    $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
    
    if ($has_role && $stored_customer_id === $customer_id) {
        echo "Already synced\n";
        $skipped++;
        continue;
    }
    
    if ($execute) {
        // Assign role
        if (!$has_role) {
            role_assign($roleid, $user->id, $context->id);
        }
        
        // Store customer ID
        if ($stored_customer_id !== $customer_id) {
            set_user_preference('local_stripe_customer_id', $customer_id, $user->id);
        }
        
        echo "✓ Synced\n";
        $synced++;
    } else {
        echo "Would sync\n";
        $synced++;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Synced: $synced\n";
echo "Already synced: $skipped\n";
echo "Errors: $errors\n";

if ($execute && $synced > 0) {
    echo "\n✓ Done! Run: php admin/cli/purge_caches.php\n";
}
