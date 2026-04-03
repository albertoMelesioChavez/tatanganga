<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        'mode' => '',
        'help' => false,
    ),
    array('h' => 'help', 'm' => 'mode')
);

if ($options['help'] || empty($options['mode'])) {
    echo "Switch between Stripe TEST and LIVE modes

Usage:
  php admin/cli/switch_stripe_mode.php --mode=test
  php admin/cli/switch_stripe_mode.php --mode=live

Options:
  --mode=test|live  Switch to TEST or LIVE mode
  -h, --help        Print this help

Current configuration will be backed up before switching.
";
    exit(0);
}

$mode = strtolower($options['mode']);

if ($mode !== 'test' && $mode !== 'live') {
    cli_error("Invalid mode. Use 'test' or 'live'");
}

echo "=== SWITCHING TO " . strtoupper($mode) . " MODE ===\n\n";

// Get current keys
$current_pub = get_config('local_stripe', 'publishablekey');
$current_sec = get_config('local_stripe', 'secretkey');
$current_webhook = get_config('local_stripe', 'webhooksecret');

echo "Current configuration:\n";
echo "  Publishable: " . substr($current_pub, 0, 20) . "...\n";
echo "  Secret: " . substr($current_sec, 0, 20) . "...\n";
if ($current_webhook) {
    echo "  Webhook: " . substr($current_webhook, 0, 20) . "...\n";
}
echo "\n";

if ($mode === 'test') {
    // Switch to TEST mode
    $test_pub = get_config('local_stripe', 'publishablekey_test');
    $test_sec = get_config('local_stripe', 'secretkey_test');
    $test_webhook = get_config('local_stripe', 'webhooksecret_test');
    
    if (empty($test_pub) || empty($test_sec)) {
        cli_error("TEST keys not found in database. Please save them first.");
    }
    
    // Backup current (LIVE) keys
    set_config('publishablekey_live', $current_pub, 'local_stripe');
    set_config('secretkey_live', $current_sec, 'local_stripe');
    if ($current_webhook) {
        set_config('webhooksecret_live', $current_webhook, 'local_stripe');
    }
    
    // Switch to TEST
    set_config('publishablekey', $test_pub, 'local_stripe');
    set_config('secretkey', $test_sec, 'local_stripe');
    if ($test_webhook) {
        set_config('webhooksecret', $test_webhook, 'local_stripe');
    }
    
    echo "✓ Switched to TEST mode\n";
    echo "  Publishable: " . substr($test_pub, 0, 20) . "...\n";
    echo "  Secret: " . substr($test_sec, 0, 20) . "...\n";
    if ($test_webhook) {
        echo "  Webhook: " . substr($test_webhook, 0, 20) . "...\n";
    }
    
} else {
    // Switch to LIVE mode
    $live_pub = get_config('local_stripe', 'publishablekey_live');
    $live_sec = get_config('local_stripe', 'secretkey_live');
    $live_webhook = get_config('local_stripe', 'webhooksecret_live');
    
    if (empty($live_pub) || empty($live_sec)) {
        cli_error("LIVE keys not found in database. Please save them first.");
    }
    
    // Backup current (TEST) keys
    set_config('publishablekey_test', $current_pub, 'local_stripe');
    set_config('secretkey_test', $current_sec, 'local_stripe');
    if ($current_webhook) {
        set_config('webhooksecret_test', $current_webhook, 'local_stripe');
    }
    
    // Switch to LIVE
    set_config('publishablekey', $live_pub, 'local_stripe');
    set_config('secretkey', $live_sec, 'local_stripe');
    if ($live_webhook) {
        set_config('webhooksecret', $live_webhook, 'local_stripe');
    }
    
    echo "✓ Switched to LIVE mode\n";
    echo "  Publishable: " . substr($live_pub, 0, 20) . "...\n";
    echo "  Secret: " . substr($live_sec, 0, 20) . "...\n";
    if ($live_webhook) {
        echo "  Webhook: " . substr($live_webhook, 0, 20) . "...\n";
    }
}

echo "\n⚠️  IMPORTANT: Purge caches after switching modes\n";
echo "Run: php admin/cli/purge_caches.php\n";
