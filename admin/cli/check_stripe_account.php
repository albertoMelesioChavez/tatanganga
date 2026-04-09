<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/stripe/vendor/autoload.php');

$secretkey = get_config('local_stripe', 'secretkey');

if (empty($secretkey)) {
    cli_error('Stripe secret key not configured');
}

\Stripe\Stripe::setApiKey($secretkey);

echo "\n";
echo "=================================================\n";
echo "  STRIPE ACCOUNT INFO\n";
echo "=================================================\n\n";

try {
    $account = \Stripe\Account::retrieve();
    
    echo "Account ID: {$account->id}\n";
    echo "Account Name: " . ($account->business_profile->name ?? 'N/A') . "\n";
    echo "Email: {$account->email}\n";
    echo "Country: {$account->country}\n";
    echo "Mode: " . (strpos($secretkey, 'sk_test_') === 0 ? 'TEST' : 'LIVE') . "\n\n";
    
    echo "=================================================\n\n";
    
} catch (Exception $e) {
    cli_error('Error: ' . $e->getMessage());
}
