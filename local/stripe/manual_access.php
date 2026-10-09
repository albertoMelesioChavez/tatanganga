<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_stripe_manual_access');
require_capability('moodle/site:config', context_system::instance());

$action = optional_param('action', 'search', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$baseurl = new moodle_url('/local/stripe/manual_access.php');
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('manualaccesstitle', 'local_stripe'));
$PAGE->set_heading(get_string('manualaccesstitle', 'local_stripe'));

$customdata = ['action' => 'search'];
if (in_array($action, ['grant', 'cancel'], true)) {
    $target = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);
    $start = max(time(), local_stripe_manual_access_end($userid));
    $customdata = [
        'action' => $action, 'user' => $target, 'start' => $start,
        'end' => local_stripe_add_calendar_month($start),
        'requestid' => optional_param('requestid', bin2hex(random_bytes(32)), PARAM_ALPHANUM),
    ];
}
$form = new \local_stripe\form\manual_access_form($baseurl, $customdata);
if ($form->is_cancelled()) {
    redirect($baseurl);
}
$failure = null;
if ($data = $form->get_data()) {
    // Moodle forms validate POST + sesskey; keep the mutation boundary explicit.
    require_sesskey();
    if ($data->action === 'search') {
        $target = local_stripe_find_manual_user($data->identifier);
        redirect(new moodle_url($baseurl, ['action' => 'grant', 'userid' => $target->id]));
    }
    try {
        if ($data->action === 'grant') {
            $grant = local_stripe_grant_manual_month((int)$data->userid, (int)$USER->id, $data->requestid);
            $message = get_string('manualgrantsuccess', 'local_stripe', (object)[
                'user' => s(fullname($target)), 'date' => userdate($grant->timeend),
            ]);
            if ($grant->status === 'cancelled' || $grant->timeend <= time()) {
                $message = get_string('manualalreadyprocessed', 'local_stripe');
            }
            $notification = \core\output\notification::NOTIFY_SUCCESS;
        } else {
            $result = local_stripe_cancel_manual_access((int)$data->userid, (int)$USER->id, $data->reason);
            $notification = \core\output\notification::NOTIFY_SUCCESS;
            if (!$result['count']) {
                $message = get_string('manualnothingtocancel', 'local_stripe');
            } else if ($result['access'] === null) {
                $message = get_string('manualcancelpending', 'local_stripe');
                $notification = \core\output\notification::NOTIFY_WARNING;
            } else {
                $message = get_string($result['access'] ? 'manualcancelretained' : 'manualcancelsuccess', 'local_stripe');
            }
        }
    } catch (Throwable $e) {
        error_log('Offline access action failed: ' . $e->getMessage());
        $failure = get_string($data->action === 'grant' ? 'manualgrantfailed' : 'manualcancelfailed', 'local_stripe');
    }
    if ($failure === null) {
        redirect($baseurl, $message, null, $notification);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualaccesstitle', 'local_stripe'));
echo html_writer::tag('p', get_string('manualaccessdescription', 'local_stripe'));
if ($failure !== null) {
    echo $OUTPUT->notification($failure, \core\output\notification::NOTIFY_ERROR);
}
echo $OUTPUT->heading(get_string($action === 'cancel' ? 'manualcancelconfirmtitle' :
    'manualgrantheading', 'local_stripe'), 3);
$form->display();
if (in_array($action, ['grant', 'cancel'], true)) {
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading(get_string('manualhistory', 'local_stripe'), 3);
$total = $DB->count_records('local_stripe_manual');
$perpage = 25;
$page = min($page, max(0, (int)ceil($total / $perpage) - 1));
$grants = $DB->get_records('local_stripe_manual', null, 'timecreated DESC, id DESC', '*', $page * $perpage, $perpage);
$peopleids = [];
foreach ($grants as $grant) {
    $peopleids[] = $grant->userid;
    $peopleids[] = $grant->createdby;
    if ($grant->cancelledby) {
        $peopleids[] = $grant->cancelledby;
    }
}
$people = $peopleids ? $DB->get_records_list('user', 'id', array_unique($peopleids)) : [];
$name = static function($id) use ($people): string {
    return isset($people[$id]) ? s(fullname($people[$id])) : '#' . (int)$id;
};
$table = new html_table();
$table->head = [
    get_string('user'), get_string('manualperiod', 'local_stripe'), get_string('status'),
    get_string('manualcreatedby', 'local_stripe'), get_string('manualcancellation', 'local_stripe'),
    get_string('actions'),
];
$now = time();
foreach ($grants as $grant) {
    $status = $grant->status;
    if ($status === 'active' && $grant->timeend <= $now) {
        $status = 'expired';
    } else if ($status === 'active' && $grant->timestart > $now) {
        $status = 'scheduled';
    }
    $pending = in_array($status, ['cancelled', 'expired'], true) && !$grant->timeprocessed;
    $statuslabel = get_string('manualstatus' . $status, 'local_stripe');
    if ($pending) {
        $statuslabel .= html_writer::div(get_string('manualpending', 'local_stripe'), 'small');
    }
    $target = $people[$grant->userid] ?? null;
    $actions = '';
    if ($target && !$target->deleted) {
        $actions = html_writer::link(new moodle_url($baseurl, [
            'action' => 'grant', 'userid' => $grant->userid,
        ]), get_string('manualrenewbutton', 'local_stripe'), ['class' => 'btn btn-sm btn-secondary']);
        if (in_array($status, ['active', 'scheduled'], true)) {
            $actions .= ' ' . html_writer::link(new moodle_url($baseurl, [
                'action' => 'cancel', 'userid' => $grant->userid,
            ]), get_string('manualcancelbutton', 'local_stripe'), ['class' => 'btn btn-sm btn-outline-danger']);
        }
    }
    $cancellation = '—';
    if ($grant->timecancelled) {
        $cancellation = $name($grant->cancelledby) . html_writer::div(userdate($grant->timecancelled), 'small');
        if ($grant->cancellationreason !== null && $grant->cancellationreason !== '') {
            $cancellation .= html_writer::div(s($grant->cancellationreason), 'small text-break');
        }
    }
    $table->data[] = [
        $name($grant->userid) . ($target ? html_writer::div(s($target->email), 'small text-break') : ''),
        userdate($grant->timestart) . html_writer::div(userdate($grant->timeend), 'small'),
        $statuslabel,
        $name($grant->createdby) . html_writer::div(userdate($grant->timecreated), 'small'),
        $cancellation,
        $actions,
    ];
}
if ($grants) {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);
} else {
    echo html_writer::tag('p', get_string('manualnohistory', 'local_stripe'));
}
echo $OUTPUT->footer();
