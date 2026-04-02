<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$userid = required_param('userid', PARAM_INT);

$context = context_system::instance();
if (!has_capability('moodle/user:update', $context) && !has_capability('moodle/user:viewdetails', $context)) {
    throw new moodle_exception('nopermissions');
}

$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

$roles = [];
$sql = "SELECT DISTINCT r.id, r.shortname, r.name, ctx.contextlevel, ctx.instanceid
        FROM {role} r
        JOIN {role_assignments} ra ON ra.roleid = r.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.userid = :userid
        ORDER BY ctx.contextlevel, r.sortorder";

$roleassignments = $DB->get_records_sql($sql, ['userid' => $userid]);

foreach ($roleassignments as $ra) {
    $contextname = '';
    switch ($ra->contextlevel) {
        case CONTEXT_SYSTEM:
            $contextname = get_string('coresystem');
            break;
        case CONTEXT_COURSE:
            $course = $DB->get_record('course', ['id' => $ra->instanceid], 'id, shortname, fullname');
            if ($course) {
                $contextname = format_string($course->fullname);
            }
            break;
        case CONTEXT_COURSECAT:
            $category = $DB->get_record('course_categories', ['id' => $ra->instanceid], 'id, name');
            if ($category) {
                $contextname = format_string($category->name);
            }
            break;
        default:
            $contextname = get_string('context' . $ra->contextlevel, 'core');
    }
    
    $rolename = !empty($ra->name) ? format_string($ra->name) : $ra->shortname;
    
    $roles[] = [
        'name' => $rolename,
        'shortname' => $ra->shortname,
        'context' => $contextname,
        'contextlevel' => $ra->contextlevel,
    ];
}

$courses = [];
$sql = "SELECT DISTINCT c.id, c.shortname, c.fullname, c.visible,
               ue.timestart, ue.timeend, ue.status as enrolstatus
        FROM {course} c
        JOIN {enrol} e ON e.courseid = c.id
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        WHERE ue.userid = :userid AND c.id > 1
        ORDER BY c.fullname";

$enrolments = $DB->get_records_sql($sql, ['userid' => $userid]);

foreach ($enrolments as $enrol) {
    $status = 'active';
    if ($enrol->enrolstatus != 0) {
        $status = 'suspended';
    } elseif ($enrol->timestart > 0 && $enrol->timestart > time()) {
        $status = 'notstarted';
    } elseif ($enrol->timeend > 0 && $enrol->timeend < time()) {
        $status = 'expired';
    }
    
    $courses[] = [
        'id' => $enrol->id,
        'shortname' => format_string($enrol->shortname),
        'fullname' => format_string($enrol->fullname),
        'visible' => $enrol->visible,
        'status' => $status,
        'url' => (new moodle_url('/course/view.php', ['id' => $enrol->id]))->out(false),
    ];
}

$stripecustomerid = get_user_preferences('local_stripe_customer_id', null, $userid);

$response = [
    'success' => true,
    'user' => [
        'id' => $user->id,
        'fullname' => fullname($user),
        'email' => $user->email,
        'username' => $user->username,
        'confirmed' => (bool)$user->confirmed,
        'suspended' => (bool)$user->suspended,
    ],
    'roles' => $roles,
    'courses' => $courses,
    'stripe' => [
        'customerid' => $stripecustomerid,
    ],
];

header('Content-Type: application/json');
echo json_encode($response);
