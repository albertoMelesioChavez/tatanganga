<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('users', new admin_externalpage(
        'local_userinfo_browse',
        get_string('pluginname', 'local_userinfo'),
        new moodle_url('/local/userinfo/index.php'),
        'moodle/user:viewdetails'
    ));
}
