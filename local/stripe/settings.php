<?php
// Settings for local_stripe.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_stripe', 'Stripe Suscripción');

    $settings->add(new admin_setting_configtext(
        'local_stripe/publishablekey',
        'Publishable key',
        'Clave pública (pk_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/secretkey',
        'Secret key',
        'Clave secreta (sk_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/priceid',
        'Price ID',
        'Price ID del plan mensual (price_...)',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_stripe/webhooksecret',
        'Webhook secret',
        'Signing secret (whsec_...)',
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
