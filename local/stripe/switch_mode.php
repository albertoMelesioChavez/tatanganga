<?php
// Minimal endpoint for switching Stripe mode - returns JSON only
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Validate session and admin capability
if (!isloggedin() || isguestuser()) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_sesskey();

$newmode = required_param('mode', PARAM_ALPHA);

if ($newmode !== 'test' && $newmode !== 'live') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid mode']);
    exit;
}

// Check admin capability
$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Switch mode
$current_pub = get_config('local_stripe', 'publishablekey');
$current_sec = get_config('local_stripe', 'secretkey');
$current_webhook = get_config('local_stripe', 'webhooksecret');

if ($newmode === 'test') {
    $test_pub = get_config('local_stripe', 'publishablekey_test');
    $test_sec = get_config('local_stripe', 'secretkey_test');
    $test_webhook = get_config('local_stripe', 'webhooksecret_test');
    
    if (empty($test_pub) || empty($test_sec)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se encontraron las claves de TEST']);
        exit;
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
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Cambiado a modo TEST exitosamente']);
    exit;
    
} else {
    $live_pub = get_config('local_stripe', 'publishablekey_live');
    $live_sec = get_config('local_stripe', 'secretkey_live');
    $live_webhook = get_config('local_stripe', 'webhooksecret_live');
    
    if (empty($live_pub) || empty($live_sec)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se encontraron las claves de LIVE']);
        exit;
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
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Cambiado a modo LIVE exitosamente']);
    exit;
}
