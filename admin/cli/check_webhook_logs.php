<?php
/**
 * Check recent Stripe webhook activity from error logs
 * 
 * Run: php admin/cli/check_webhook_logs.php
 */

define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

echo "=== STRIPE WEBHOOK LOG CHECKER ===\n\n";

// Check if webhook secret is configured
$webhook_secret = get_config('local_stripe', 'webhooksecret');
if (empty($webhook_secret)) {
    echo "✗ CRITICAL: Webhook secret is NOT configured!\n";
    echo "  This means webhooks are being rejected.\n";
    echo "  Configure it at: Site administration > Plugins > Local plugins > Stripe\n\n";
} else {
    echo "✓ Webhook secret is configured\n";
    echo "  Secret: " . substr($webhook_secret, 0, 10) . "...\n\n";
}

// Check if publishable key is configured
$publishable_key = get_config('local_stripe', 'publishablekey');
if (empty($publishable_key)) {
    echo "✗ WARNING: Publishable key is NOT configured\n\n";
} else {
    echo "✓ Publishable key is configured\n";
    echo "  Key: " . substr($publishable_key, 0, 20) . "...\n\n";
}

// Check if secret key is configured
$secret_key = get_config('local_stripe', 'secretkey');
if (empty($secret_key)) {
    echo "✗ WARNING: Secret key is NOT configured\n\n";
} else {
    echo "✓ Secret key is configured\n";
    echo "  Key: " . substr($secret_key, 0, 20) . "...\n\n";
}

echo "=== CHECKING ERROR LOGS ===\n\n";

// Try to find error log location
$error_log_paths = [
    ini_get('error_log'),
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    '/var/log/nginx/error.log',
    $CFG->dataroot . '/error_log',
    '/tmp/error_log',
];

$found_log = null;
foreach ($error_log_paths as $path) {
    if (!empty($path) && file_exists($path) && is_readable($path)) {
        $found_log = $path;
        break;
    }
}

if ($found_log) {
    echo "Found error log at: {$found_log}\n\n";
    echo "Recent Stripe-related log entries (last 50 lines):\n";
    echo "----------------------------------------\n";
    
    $cmd = "grep -i 'stripe' " . escapeshellarg($found_log) . " | tail -50";
    $output = shell_exec($cmd);
    
    if (empty($output)) {
        echo "No Stripe-related entries found in error log.\n";
        echo "This could mean:\n";
        echo "  1. No webhooks have been received\n";
        echo "  2. Webhooks are being received but not logged\n";
        echo "  3. Error log is in a different location\n";
    } else {
        echo $output;
    }
} else {
    echo "Could not locate error log file.\n";
    echo "Tried the following locations:\n";
    foreach ($error_log_paths as $path) {
        if (!empty($path)) {
            echo "  - {$path}\n";
        }
    }
    echo "\nTo manually check logs, run:\n";
    echo "  grep -i 'stripe' /path/to/error_log | tail -50\n";
}

echo "\n\n=== WEBHOOK ENDPOINT ===\n";
echo "Your webhook URL should be: https://tatanganga.cloud/local/stripe/webhook.php\n";
echo "Configure this in Stripe Dashboard > Developers > Webhooks\n\n";

echo "=== REQUIRED WEBHOOK EVENTS ===\n";
echo "Make sure these events are enabled in Stripe:\n";
echo "  - checkout.session.completed\n";
echo "  - customer.subscription.deleted\n";
echo "  - customer.subscription.updated\n";
echo "  - invoice.payment_failed\n\n";

echo "=== TESTING WEBHOOK ===\n";
echo "To test if webhook endpoint is accessible:\n";
echo "  curl -I https://tatanganga.cloud/local/stripe/webhook.php\n";
echo "  (Should return HTTP 200 or 400, not 404)\n\n";

echo "=== NEXT STEPS ===\n";
echo "1. Run: php admin/cli/diagnose_subscribers.php\n";
echo "   (To see current subscriber status)\n\n";
echo "2. Run: php admin/cli/fix_missing_subscriber_roles.php --list\n";
echo "   (To find users who paid but don't have the role)\n\n";
echo "3. Check Stripe Dashboard > Developers > Webhooks > [your webhook]\n";
echo "   (To see webhook delivery status and any errors)\n\n";
