<?php
// Capabilities for local_stripe.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/stripe:managesettings' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/stripe:issuscriptor' => [
        'riskbitmask' => 0,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'student_suscriptor' => CAP_ALLOW,
        ],
    ],
];
