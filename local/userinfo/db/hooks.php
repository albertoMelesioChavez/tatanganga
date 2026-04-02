<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_reportbuilder\hook\after_system_report_created::class,
        'callback' => \local_userinfo\hook_callbacks::class . '::add_user_info_column',
    ],
];
