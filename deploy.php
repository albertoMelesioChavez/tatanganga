<?php
// GitHub Webhook - Auto deploy on push
// Configure your secret in GitHub Webhooks settings

$secret = 'tatanganga-deploy-2026';

// Verify GitHub signature
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$payload = file_get_contents('php://input');

if (empty($signature)) {
    http_response_code(403);
    die('No signature');
}

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    die('Invalid signature');
}

// Only deploy on push to main
$data = json_decode($payload, true);
if (isset($data['ref']) && $data['ref'] !== 'refs/heads/main') {
    echo 'Not main branch, skipping.';
    exit;
}

// Run deploy
$output = [];
$dir = dirname(__FILE__);
exec("cd $dir && git pull origin main 2>&1", $output);
exec("cd $dir && php admin/cli/purge_caches.php 2>&1", $output);

// Log
$log = date('Y-m-d H:i:s') . "\n" . implode("\n", $output) . "\n---\n";
file_put_contents($dir . '/deploy.log', $log, FILE_APPEND);

echo implode("\n", $output);
