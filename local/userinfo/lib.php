<?php
defined('MOODLE_INTERNAL') || die();

function local_userinfo_before_footer() {
    global $PAGE;
    
    // Only load on admin user browsing page
    if ($PAGE->pagetype === 'admin-user') {
        $PAGE->requires->js_call_amd('local_userinfo/userinfo', 'init');
    }
}
