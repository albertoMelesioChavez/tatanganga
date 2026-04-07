<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_stripe_admin');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/stripe/admin.php'));
$PAGE->set_title(get_string('pluginname', 'local_stripe'));
$PAGE->set_heading(get_string('pluginname', 'local_stripe'));

// Handle actions
if ($action === 'download_report' && confirm_sesskey()) {
    require_once(__DIR__ . '/vendor/autoload.php');
    
    $secret_key = get_config('local_stripe', 'secretkey');
    if (empty($secret_key)) {
        print_error('Stripe secret key not configured');
    }
    
    \Stripe\Stripe::setApiKey($secret_key);
    $mode = (strpos($secret_key, 'sk_test_') === 0) ? 'TEST' : 'LIVE';
    
    // Generate report
    $report = generate_stripe_report_content();
    
    // Send file
    header('Content-Type: text/markdown');
    header('Content-Disposition: attachment; filename="stripe_report_' . date('Y-m-d_His') . '.md"');
    echo $report;
    exit;
}

if ($action === 'sync_now' && confirm_sesskey()) {
    if ($confirm) {
        // Run sync
        require_once(__DIR__ . '/vendor/autoload.php');
        $secret_key = get_config('local_stripe', 'secretkey');
        \Stripe\Stripe::setApiKey($secret_key);
        
        $result = sync_stripe_subscriptions_fast();
        redirect(new moodle_url('/local/stripe/admin.php'), 
                 "Sincronizados: {$result['synced']}, Ya sincronizados: {$result['already_synced']}, Errores: {$result['errors']}", 
                 null, 
                 \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // Show confirmation
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('pluginname', 'local_stripe'));
        
        $continue = new moodle_url('/local/stripe/admin.php', ['action' => 'sync_now', 'confirm' => 1, 'sesskey' => sesskey()]);
        $cancel = new moodle_url('/local/stripe/admin.php');
        
        echo $OUTPUT->confirm('¿Estás seguro de que quieres sincronizar todos los usuarios con suscripciones activas de Stripe?', $continue, $cancel);
        echo $OUTPUT->footer();
        exit;
    }
}

if ($action === 'switch_mode' && confirm_sesskey()) {
    $newmode = required_param('mode', PARAM_ALPHA);
    
    if ($newmode !== 'test' && $newmode !== 'live') {
        print_error('Invalid mode');
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
            redirect(new moodle_url('/local/stripe/admin.php'), 
                     'No se encontraron las claves de TEST. Por favor configúralas primero.', 
                     null, 
                     \core\output\notification::NOTIFY_ERROR);
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
        
        purge_all_caches();
        
        redirect(new moodle_url('/local/stripe/admin.php'), 
                 'Cambiado a modo TEST exitosamente', 
                 null, 
                 \core\output\notification::NOTIFY_SUCCESS);
        
    } else {
        $live_pub = get_config('local_stripe', 'publishablekey_live');
        $live_sec = get_config('local_stripe', 'secretkey_live');
        $live_webhook = get_config('local_stripe', 'webhooksecret_live');
        
        if (empty($live_pub) || empty($live_sec)) {
            redirect(new moodle_url('/local/stripe/admin.php'), 
                     'No se encontraron las claves de LIVE. Por favor configúralas primero.', 
                     null, 
                     \core\output\notification::NOTIFY_ERROR);
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
        
        purge_all_caches();
        
        redirect(new moodle_url('/local/stripe/admin.php'), 
                 'Cambiado a modo LIVE exitosamente', 
                 null, 
                 \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_stripe'));

// Get current configuration
$publishablekey = get_config('local_stripe', 'publishablekey');
$secretkey = get_config('local_stripe', 'secretkey');
$webhooksecret = get_config('local_stripe', 'webhooksecret');

$mode = 'NOT CONFIGURED';
if (!empty($secretkey)) {
    $mode = (strpos($secretkey, 'sk_test_') === 0) ? 'TEST' : 'LIVE';
}

// Get sync status
$sync_status = get_sync_status();

?>

<div class="stripe-admin-dashboard">
    
    <!-- Current Status -->
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="card-title">Estado Actual</h3>
            <table class="table table-sm">
                <tr>
                    <th>Modo:</th>
                    <td>
                        <span class="badge badge-<?php echo $mode === 'LIVE' ? 'success' : ($mode === 'TEST' ? 'warning' : 'danger'); ?>">
                            <?php echo $mode; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Publishable Key:</th>
                    <td><code><?php echo !empty($publishablekey) ? substr($publishablekey, 0, 25) . '...' : 'No configurada'; ?></code></td>
                </tr>
                <tr>
                    <th>Secret Key:</th>
                    <td><code><?php echo !empty($secretkey) ? substr($secretkey, 0, 20) . '...' : 'No configurada'; ?></code></td>
                </tr>
                <tr>
                    <th>Webhook Secret:</th>
                    <td><code><?php echo !empty($webhooksecret) ? substr($webhooksecret, 0, 20) . '...' : 'No configurado'; ?></code></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Sync Status -->
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="card-title">Estado de Sincronización</h3>
            <table class="table table-sm">
                <tr>
                    <th>Usuarios con rol de suscriptor:</th>
                    <td><strong><?php echo $sync_status['with_role']; ?></strong></td>
                </tr>
                <tr>
                    <th>Usuarios con Stripe Customer ID:</th>
                    <td><strong><?php echo $sync_status['with_customer_id']; ?></strong></td>
                </tr>
                <tr>
                    <th>Usuarios sin Stripe Customer ID:</th>
                    <td><strong><?php echo $sync_status['without_customer_id']; ?></strong></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Actions -->
    <div class="card mb-3">
        <div class="card-body">
            <h3 class="card-title">Acciones</h3>
            
            <div class="btn-group mb-3" role="group">
                <a href="<?php echo new moodle_url('/local/stripe/admin.php', ['action' => 'download_report', 'sesskey' => sesskey()]); ?>" 
                   class="btn btn-primary">
                    <i class="fa fa-download"></i> Descargar Reporte Completo
                </a>
                
                <a href="<?php echo new moodle_url('/local/stripe/admin.php', ['action' => 'sync_now', 'sesskey' => sesskey()]); ?>" 
                   class="btn btn-success">
                    <i class="fa fa-sync"></i> Sincronizar Ahora
                </a>
            </div>
            
            <hr>
            
            <h4>Cambiar Modo</h4>
            <div class="btn-group" role="group">
                <?php if ($mode !== 'TEST'): ?>
                <a href="<?php echo new moodle_url('/local/stripe/admin.php', ['action' => 'switch_mode', 'mode' => 'test', 'sesskey' => sesskey()]); ?>" 
                   class="btn btn-warning">
                    <i class="fa fa-flask"></i> Cambiar a TEST
                </a>
                <?php endif; ?>
                
                <?php if ($mode !== 'LIVE'): ?>
                <a href="<?php echo new moodle_url('/local/stripe/admin.php', ['action' => 'switch_mode', 'mode' => 'live', 'sesskey' => sesskey()]); ?>" 
                   class="btn btn-success">
                    <i class="fa fa-check"></i> Cambiar a LIVE
                </a>
                <?php endif; ?>
            </div>
            
            <hr>
            
            <h4>Configuración</h4>
            <a href="<?php echo new moodle_url('/admin/settings.php', ['section' => 'local_stripe']); ?>" 
               class="btn btn-secondary">
                <i class="fa fa-cog"></i> Configurar API Keys
            </a>
        </div>
    </div>
    
</div>

<?php

echo $OUTPUT->footer();

// Helper functions

function get_sync_status() {
    global $DB;
    
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
    $context = context_system::instance();
    
    $with_role = $DB->count_records('role_assignments', [
        'roleid' => $roleid,
        'contextid' => $context->id,
    ]);
    
    $with_customer_id = $DB->count_records('user_preferences', [
        'name' => 'local_stripe_customer_id',
    ]);
    
    return [
        'with_role' => $with_role,
        'with_customer_id' => $with_customer_id,
        'without_customer_id' => max(0, $with_role - $with_customer_id),
    ];
}

function generate_stripe_report_content() {
    global $DB, $CFG;
    
    require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
    
    $secret_key = get_config('local_stripe', 'secretkey');
    \Stripe\Stripe::setApiKey($secret_key);
    
    $mode = (strpos($secret_key, 'sk_test_') === 0) ? 'TEST' : 'LIVE';
    
    $report = [];
    $report[] = "# Reporte de Sincronización Stripe - Tatanganga";
    $report[] = "";
    $report[] = "**Fecha:** " . date('Y-m-d H:i:s');
    $report[] = "**Modo:** $mode";
    $report[] = "";
    
    // Get subscriptions
    $all_subscriptions = [];
    $has_more = true;
    $starting_after = null;
    
    while ($has_more) {
        $params = ['status' => 'active', 'limit' => 100];
        if ($starting_after) {
            $params['starting_after'] = $starting_after;
        }
        
        $subscriptions = \Stripe\Subscription::all($params);
        $all_subscriptions = array_merge($all_subscriptions, $subscriptions->data);
        
        $has_more = $subscriptions->has_more;
        if ($has_more && !empty($subscriptions->data)) {
            $starting_after = end($subscriptions->data)->id;
        }
    }
    
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
    $context = context_system::instance();
    
    $synced = 0;
    $not_synced = 0;
    $not_registered = 0;
    
    foreach ($all_subscriptions as $subscription) {
        $customer = \Stripe\Customer::retrieve($subscription->customer);
        $user = $DB->get_record('user', ['email' => $customer->email, 'deleted' => 0]);
        
        if (!$user) {
            $not_registered++;
            continue;
        }
        
        $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
        $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
        
        if ($has_role && $stored_customer_id) {
            $synced++;
        } else {
            $not_synced++;
        }
    }
    
    $report[] = "## Resumen";
    $report[] = "";
    $report[] = "- Total suscripciones activas: " . count($all_subscriptions);
    $report[] = "- Usuarios sincronizados: $synced";
    $report[] = "- Usuarios sin sincronizar: $not_synced";
    $report[] = "- Usuarios no registrados: $not_registered";
    $report[] = "";
    
    return implode("\n", $report);
}

function sync_stripe_subscriptions_fast() {
    global $DB, $CFG;
    
    require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
    require_once($CFG->dirroot . '/local/stripe/lib.php');
    
    $secret_key = get_config('local_stripe', 'secretkey');
    \Stripe\Stripe::setApiKey($secret_key);
    
    $all_subscriptions = [];
    $has_more = true;
    $starting_after = null;
    
    while ($has_more) {
        $params = ['status' => 'active', 'limit' => 100];
        if ($starting_after) {
            $params['starting_after'] = $starting_after;
        }
        
        $subscriptions = \Stripe\Subscription::all($params);
        $all_subscriptions = array_merge($all_subscriptions, $subscriptions->data);
        
        $has_more = $subscriptions->has_more;
        if ($has_more && !empty($subscriptions->data)) {
            $starting_after = end($subscriptions->data)->id;
        }
    }
    
    $synced = 0;
    $already_synced = 0;
    $errors = 0;
    
    $roleid = local_stripe_get_suscriptor_role_id();
    $context = context_system::instance();
    
    foreach ($all_subscriptions as $subscription) {
        try {
            $customer = \Stripe\Customer::retrieve($subscription->customer);
            $user = $DB->get_record('user', ['email' => $customer->email, 'deleted' => 0]);
            
            if (!$user) {
                $errors++;
                continue;
            }
            
            $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
            $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
            
            if ($has_role && $stored_customer_id === $customer->id) {
                $already_synced++;
                continue;
            }
            
            if (!$has_role) {
                role_assign($roleid, $user->id, $context->id);
            }
            
            if ($stored_customer_id !== $customer->id) {
                set_user_preference('local_stripe_customer_id', $customer->id, $user->id);
            }
            
            $synced++;
            
        } catch (Exception $e) {
            $errors++;
        }
    }
    
    return [
        'synced' => $synced,
        'already_synced' => $already_synced,
        'errors' => $errors,
    ];
}
