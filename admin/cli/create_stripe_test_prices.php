<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/stripe/vendor/autoload.php');

// Get Stripe secret key
$secretkey = get_config('local_stripe', 'secretkey');

if (empty($secretkey)) {
    cli_error('Stripe secret key not configured');
}

// Check if we're in TEST mode
if (strpos($secretkey, 'sk_test_') !== 0) {
    cli_error('ERROR: You must be in TEST mode to create test prices. Current mode is LIVE.');
}

\Stripe\Stripe::setApiKey($secretkey);

echo "\n";
echo "=================================================\n";
echo "  CREAR PRODUCTOS Y PRECIOS DE TEST EN STRIPE\n";
echo "=================================================\n\n";

echo "Modo: TEST ✓\n";
echo "Secret Key: " . substr($secretkey, 0, 20) . "...\n\n";

$created_prices = [];

try {
    // 1. Producto Mensual MXN
    echo "1. Creando producto: Tatanganga Mensual MXN...\n";
    $product1 = \Stripe\Product::create([
        'name' => 'Tatanganga Mensual MXN',
        'description' => 'Suscripción mensual a Tatanganga - Pesos Mexicanos',
    ]);
    echo "   Producto creado: {$product1->id}\n";
    
    $price1 = \Stripe\Price::create([
        'product' => $product1->id,
        'unit_amount' => 85000, // 850 MXN in cents
        'currency' => 'mxn',
        'recurring' => ['interval' => 'month'],
    ]);
    echo "   ✓ Price ID: {$price1->id}\n\n";
    $created_prices['mxn_monthly'] = $price1->id;
    
    // 2. Producto Anual MXN
    echo "2. Creando producto: Tatanganga Anual MXN...\n";
    $product2 = \Stripe\Product::create([
        'name' => 'Tatanganga Anual MXN',
        'description' => 'Suscripción anual a Tatanganga - Pesos Mexicanos (Ahorra $1,700)',
    ]);
    echo "   Producto creado: {$product2->id}\n";
    
    $price2 = \Stripe\Price::create([
        'product' => $product2->id,
        'unit_amount' => 850000, // 8500 MXN in cents
        'currency' => 'mxn',
        'recurring' => ['interval' => 'year'],
    ]);
    echo "   ✓ Price ID: {$price2->id}\n\n";
    $created_prices['mxn_yearly'] = $price2->id;
    
    // 3. Producto Mensual USD
    echo "3. Creando producto: Tatanganga Mensual USD...\n";
    $product3 = \Stripe\Product::create([
        'name' => 'Tatanganga Mensual USD',
        'description' => 'Suscripción mensual a Tatanganga - US Dollars',
    ]);
    echo "   Producto creado: {$product3->id}\n";
    
    $price3 = \Stripe\Price::create([
        'product' => $product3->id,
        'unit_amount' => 4800, // 48 USD in cents
        'currency' => 'usd',
        'recurring' => ['interval' => 'month'],
    ]);
    echo "   ✓ Price ID: {$price3->id}\n\n";
    $created_prices['usd_monthly'] = $price3->id;
    
    // 4. Producto Anual USD
    echo "4. Creando producto: Tatanganga Anual USD...\n";
    $product4 = \Stripe\Product::create([
        'name' => 'Tatanganga Anual USD',
        'description' => 'Suscripción anual a Tatanganga - US Dollars (Save $76)',
    ]);
    echo "   Producto creado: {$product4->id}\n";
    
    $price4 = \Stripe\Price::create([
        'product' => $product4->id,
        'unit_amount' => 50000, // 500 USD in cents
        'currency' => 'usd',
        'recurring' => ['interval' => 'year'],
    ]);
    echo "   ✓ Price ID: {$price4->id}\n\n";
    $created_prices['usd_yearly'] = $price4->id;
    
    echo "=================================================\n";
    echo "  ✓ TODOS LOS PRODUCTOS CREADOS EXITOSAMENTE\n";
    echo "=================================================\n\n";
    
    echo "Copia estos Price IDs en local/stripe/subscribe.php:\n\n";
    echo "\$plans_test = [\n";
    echo "    'mxn_monthly' => '{$created_prices['mxn_monthly']}',\n";
    echo "    'mxn_yearly' => '{$created_prices['mxn_yearly']}',\n";
    echo "    'usd_monthly' => '{$created_prices['usd_monthly']}',\n";
    echo "    'usd_yearly' => '{$created_prices['usd_yearly']}',\n";
    echo "];\n\n";
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    cli_error('Error de Stripe: ' . $e->getMessage());
} catch (Exception $e) {
    cli_error('Error: ' . $e->getMessage());
}

echo "Hecho.\n\n";
