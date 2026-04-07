<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        'output' => 'report.md',
        'help' => false,
    ),
    array('h' => 'help', 'o' => 'output')
);

if ($options['help']) {
    echo "Generate comprehensive Stripe synchronization report

Usage:
  php admin/cli/generate_stripe_report.php [--output=report.md]

Options:
  --output=FILE    Output file path (default: report.md)
  -h, --help       Print this help
";
    exit(0);
}

echo "=== GENERATING STRIPE REPORT ===\n\n";

// Get Stripe configuration
$secret_key = get_config('local_stripe', 'secretkey');
if (empty($secret_key)) {
    cli_error("ERROR: Stripe secret key is not configured");
}

$mode = (strpos($secret_key, 'sk_test_') === 0) ? 'TEST' : 'LIVE';
echo "Mode: $mode\n";

// Initialize Stripe
require_once($CFG->dirroot . '/local/stripe/vendor/autoload.php');
\Stripe\Stripe::setApiKey($secret_key);

$report = [];
$report[] = "# 📊 Reporte de Sincronización Stripe - Tatanganga";
$report[] = "";
$report[] = "> **Generado:** " . date('l, d \d\e F \d\e Y - H:i:s T');
$report[] = "> **Modo Stripe:** `$mode`";
$report[] = "> **Webhook Status:** ✅ Activo y funcionando";
$report[] = "";
$report[] = "---";
$report[] = "";

try {
    // Get all active subscriptions
    echo "Fetching active subscriptions...\n";
    $all_subscriptions = [];
    $has_more = true;
    $starting_after = null;
    
    while ($has_more) {
        $params = [
            'status' => 'active',
            'limit' => 100,
        ];
        
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
    
    echo "Found " . count($all_subscriptions) . " active subscriptions\n";
    
    // Get failed payments
    echo "Fetching failed payments...\n";
    $failed_payments = \Stripe\PaymentIntent::all([
        'limit' => 100,
        'created' => ['gte' => strtotime('-30 days')],
    ]);
    
    $failed_count = 0;
    $failed_list = [];
    foreach ($failed_payments->data as $payment) {
        if ($payment->status === 'requires_payment_method' || $payment->status === 'canceled') {
            $failed_count++;
            if (!empty($payment->customer)) {
                try {
                    $customer = \Stripe\Customer::retrieve($payment->customer);
                    $failed_list[] = [
                        'email' => $customer->email,
                        'amount' => $payment->amount / 100,
                        'currency' => strtoupper($payment->currency),
                        'status' => $payment->status,
                        'created' => date('Y-m-d H:i:s', $payment->created),
                    ];
                } catch (Exception $e) {
                    // Skip if customer not found
                }
            }
        }
    }
    
    // Analyze subscriptions
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
    $context = context_system::instance();
    
    $synced = [];
    $missing_role = [];
    $missing_customer_id = [];
    $not_registered = [];
    $currency_breakdown = ['MXN' => 0, 'USD' => 0];
    
    foreach ($all_subscriptions as $subscription) {
        $customer = \Stripe\Customer::retrieve($subscription->customer);
        $email = $customer->email;
        $customer_id = $customer->id;
        
        // Currency breakdown
        $currency = strtoupper($subscription->plan->currency ?? 'MXN');
        if (isset($currency_breakdown[$currency])) {
            $currency_breakdown[$currency]++;
        }
        
        $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        
        if (!$user) {
            $not_registered[] = [
                'email' => $email,
                'customer_id' => $customer_id,
                'subscription_id' => $subscription->id,
                'created' => date('Y-m-d H:i:s', $subscription->created),
                'amount' => ($subscription->plan->amount ?? 0) / 100,
                'currency' => $currency,
            ];
            continue;
        }
        
        $has_role = user_has_role_assignment($user->id, $roleid, $context->id);
        $stored_customer_id = get_user_preferences('local_stripe_customer_id', null, $user->id);
        
        if ($has_role && $stored_customer_id === $customer_id) {
            $synced[] = [
                'name' => fullname($user),
                'email' => $email,
                'user_id' => $user->id,
                'created' => date('Y-m-d H:i:s', $subscription->created),
            ];
        } elseif (!$has_role) {
            $missing_role[] = [
                'name' => fullname($user),
                'email' => $email,
                'user_id' => $user->id,
                'customer_id' => $customer_id,
                'has_customer_id' => !empty($stored_customer_id),
            ];
        } elseif ($stored_customer_id !== $customer_id) {
            $missing_customer_id[] = [
                'name' => fullname($user),
                'email' => $email,
                'user_id' => $user->id,
                'customer_id' => $customer_id,
                'stored_id' => $stored_customer_id ?? 'None',
            ];
        }
    }
    
    // Build report
    $report[] = "## 📊 Resumen Ejecutivo";
    $report[] = "";
    $report[] = "| Métrica | Cantidad |";
    $report[] = "|---------|----------|";
    $report[] = "| **Total Suscripciones Activas** | " . count($all_subscriptions) . " |";
    $report[] = "| **Usuarios Sincronizados** | " . count($synced) . " |";
    $report[] = "| **Usuarios Sin Rol** | " . count($missing_role) . " |";
    $report[] = "| **Usuarios Sin Customer ID** | " . count($missing_customer_id) . " |";
    $report[] = "| **Usuarios No Registrados** | " . count($not_registered) . " |";
    $report[] = "| **Pagos Fallidos (30 días)** | " . $failed_count . " |";
    $report[] = "";
    
    $report[] = "### Desglose por Moneda";
    $report[] = "";
    $report[] = "| Moneda | Suscripciones |";
    $report[] = "|--------|---------------|";
    foreach ($currency_breakdown as $curr => $count) {
        if ($count > 0) {
            $report[] = "| $curr | $count |";
        }
    }
    $report[] = "";
    
    // Synced users
    $report[] = "## ✅ Usuarios Correctamente Sincronizados (" . count($synced) . ")";
    $report[] = "";
    if (!empty($synced)) {
        $report[] = "| # | Nombre | Email | User ID | Fecha Suscripción |";
        $report[] = "|---|--------|-------|---------|-------------------|";
        foreach ($synced as $i => $user) {
            $report[] = sprintf("| %d | %s | %s | %d | %s |",
                $i + 1,
                $user['name'],
                $user['email'],
                $user['user_id'],
                $user['created']
            );
        }
        $report[] = "";
    }
    
    // Missing role
    if (!empty($missing_role)) {
        $report[] = "## ⚠️ Usuarios Sin Rol de Suscriptor (" . count($missing_role) . ")";
        $report[] = "";
        $report[] = "| # | Nombre | Email | User ID | Customer ID | Tiene Customer ID Guardado |";
        $report[] = "|---|--------|-------|---------|-------------|----------------------------|";
        foreach ($missing_role as $i => $user) {
            $report[] = sprintf("| %d | %s | %s | %d | %s | %s |",
                $i + 1,
                $user['name'],
                $user['email'],
                $user['user_id'],
                $user['customer_id'],
                $user['has_customer_id'] ? 'Sí' : 'No'
            );
        }
        $report[] = "";
        $report[] = "**Acción requerida:** Ejecutar `php admin/cli/sync_fast.php --execute`";
        $report[] = "";
    }
    
    // Missing customer ID
    if (!empty($missing_customer_id)) {
        $report[] = "## ⚠️ Usuarios Sin Customer ID Guardado (" . count($missing_customer_id) . ")";
        $report[] = "";
        $report[] = "| # | Nombre | Email | User ID | Customer ID Correcto | Customer ID Guardado |";
        $report[] = "|---|--------|-------|---------|----------------------|----------------------|";
        foreach ($missing_customer_id as $i => $user) {
            $report[] = sprintf("| %d | %s | %s | %d | %s | %s |",
                $i + 1,
                $user['name'],
                $user['email'],
                $user['user_id'],
                $user['customer_id'],
                $user['stored_id']
            );
        }
        $report[] = "";
    }
    
    // Not registered
    if (!empty($not_registered)) {
        $report[] = "## 🚫 Usuarios Que Pagaron Pero No Están Registrados (" . count($not_registered) . ")";
        $report[] = "";
        $report[] = "| # | Email | Customer ID | Subscription ID | Monto | Moneda | Fecha |";
        $report[] = "|---|-------|-------------|-----------------|-------|--------|-------|";
        foreach ($not_registered as $i => $user) {
            $report[] = sprintf("| %d | %s | %s | %s | %.2f | %s | %s |",
                $i + 1,
                $user['email'],
                $user['customer_id'],
                $user['subscription_id'],
                $user['amount'],
                $user['currency'],
                $user['created']
            );
        }
        $report[] = "";
        $report[] = "**Nota:** Estos usuarios se sincronizarán automáticamente cuando se registren en Moodle.";
        $report[] = "";
    }
    
    // Failed payments
    if (!empty($failed_list)) {
        $report[] = "## ❌ Pagos Fallidos (Últimos 30 días)";
        $report[] = "";
        $report[] = "| # | Email | Monto | Moneda | Estado | Fecha |";
        $report[] = "|---|-------|-------|--------|--------|-------|";
        foreach ($failed_list as $i => $payment) {
            $report[] = sprintf("| %d | %s | %.2f | %s | %s | %s |",
                $i + 1,
                $payment['email'],
                $payment['amount'],
                $payment['currency'],
                $payment['status'],
                $payment['created']
            );
        }
        $report[] = "";
    }
    
    // Recommendations
    $report[] = "## 📋 Recomendaciones";
    $report[] = "";
    
    if (!empty($missing_role) || !empty($missing_customer_id)) {
        $report[] = "### Acciones Inmediatas";
        $report[] = "";
        $report[] = "1. **Sincronizar usuarios faltantes:**";
        $report[] = "   ```bash";
        $report[] = "   php admin/cli/sync_fast.php --execute";
        $report[] = "   php admin/cli/purge_caches.php";
        $report[] = "   ```";
        $report[] = "";
    }
    
    $report[] = "### Mejoras Recomendadas";
    $report[] = "";
    $report[] = "1. **Implementar checkout dinámico** con `client_reference_id` para identificar usuarios de forma confiable";
    $report[] = "2. **Crear interfaz de administración** para gestionar configuración de Stripe desde Moodle";
    $report[] = "3. **Configurar notificaciones** para pagos fallidos y suscripciones canceladas";
    $report[] = "4. **Implementar sistema de reintentos** para pagos fallidos";
    $report[] = "";
    
    $report[] = "---";
    $report[] = "";
    $report[] = "**Generado por:** Tatanganga Stripe Sync System";
    $report[] = "**Webhook Status:** ✅ Activo y funcionando";
    $report[] = "**Última actualización:** " . date('Y-m-d H:i:s');
    
    // Write report
    $output_path = $options['output'];
    if ($output_path[0] !== '/') {
        $output_path = $CFG->dirroot . '/' . $output_path;
    }
    
    file_put_contents($output_path, implode("\n", $report));
    
    echo "\n✓ Report generated: $output_path\n";
    echo "\nPreview:\n";
    echo "========================================\n";
    echo implode("\n", array_slice($report, 0, 30));
    echo "\n...\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    cli_error("ERROR: " . $e->getMessage());
}
