<?php
// Settings for local_stripe.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add admin page
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_stripe_admin',
        'Stripe Dashboard',
        new moodle_url('/local/stripe/admin.php'),
        'moodle/site:config'
    ));
    
    $settings = new admin_settingpage('local_stripe', 'Stripe Configuración');

    $settings->add(new admin_setting_heading(
        'local_stripe/livekeys',
        'Claves de Producción (LIVE)',
        'Estas son las claves que se usan actualmente en producción'
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/publishablekey',
        'Publishable key (LIVE)',
        'Clave pública (pk_live_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/secretkey',
        'Secret key (LIVE)',
        'Clave secreta (sk_live_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/webhooksecret',
        'Webhook secret (LIVE)',
        'Signing secret (whsec_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_stripe/testkeys',
        'Claves de Prueba (TEST)',
        'Estas claves se usan cuando cambias a modo TEST desde el dashboard'
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/publishablekey_test',
        'Publishable key (TEST)',
        'Clave pública de prueba (pk_test_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/secretkey_test',
        'Secret key (TEST)',
        'Clave secreta de prueba (sk_test_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/webhooksecret_test',
        'Webhook secret (TEST)',
        'Signing secret de prueba (whsec_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_stripe/otherconfig',
        'Otras Configuraciones',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/priceid',
        'Price ID',
        'Price ID del plan mensual (price_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/successurl',
        'Success URL',
        'URL de éxito después del pago',
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/cancelurl',
        'Cancel URL',
        'URL de cancelación',
        '',
        PARAM_URL
    ));

    $ADMIN->add('localplugins', $settings);
}
