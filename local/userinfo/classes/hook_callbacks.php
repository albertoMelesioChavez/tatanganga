<?php
namespace local_userinfo;

use core_reportbuilder\local\report\action;
use moodle_url;
use pix_icon;

class hook_callbacks {
    
    public static function add_user_info_column(\core_reportbuilder\hook\after_system_report_created $hook): void {
        global $PAGE;
        
        $report = $hook->get_report();
        
        if (!$report instanceof \core_admin\reportbuilder\local\systemreports\users) {
            return;
        }
        
        $PAGE->requires->js_call_amd('local_userinfo/userinfo', 'init');
        
        $report->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/info', ''),
            [
                'class' => 'user-info-trigger',
                'data-userid' => ':id',
                'data-username' => ':username',
                'data-action' => 'show-user-info',
                'style' => 'cursor: pointer;',
            ],
            false,
            get_string('userinfo', 'local_userinfo'),
        ))->add_callback(static function(\stdclass $row): bool {
            return true;
        }));
    }
}
