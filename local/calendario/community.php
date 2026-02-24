<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Community redirect page.
 *
 * Receives a course module ID (cmid) or course ID, finds or creates
 * a discussion thread in the community forum, auto-enrols the user
 * in the community course, and redirects to the discussion.
 *
 * Without parameters, redirects to the community forum index.
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

require_login();

// Community course and forum IDs.
define('LOCAL_CALENDARIO_COMMUNITY_COURSEID', 12);
define('LOCAL_CALENDARIO_COMMUNITY_FORUMID', 12);

$cmid = optional_param('cmid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$communitycourseid = LOCAL_CALENDARIO_COMMUNITY_COURSEID;
$communityforumid = LOCAL_CALENDARIO_COMMUNITY_FORUMID;

// Ensure community course and forum exist.
$communitycourse = $DB->get_record('course', ['id' => $communitycourseid], '*', MUST_EXIST);
$communityforum = $DB->get_record('forum', ['id' => $communityforumid], '*', MUST_EXIST);

// Auto-enrol user in community course if not already enrolled.
$context = context_course::instance($communitycourseid);
if (!is_enrolled($context, $USER)) {
    $enrolplugin = enrol_get_plugin('manual');
    $enrolinstances = $DB->get_records('enrol', [
        'courseid' => $communitycourseid,
        'enrol' => 'manual',
        'status' => ENROL_INSTANCE_ENABLED,
    ]);
    if (empty($enrolinstances)) {
        // Create a manual enrol instance if none exists.
        $enrolplugin->add_instance($communitycourse);
        $enrolinstances = $DB->get_records('enrol', [
            'courseid' => $communitycourseid,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ]);
    }
    $enrolinstance = reset($enrolinstances);
    if ($enrolinstance) {
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $enrolplugin->enrol_user($enrolinstance, $USER->id, $studentroleid);
    }
}

// If no cmid or courseid, redirect to the community forum index.
if (!$cmid && !$courseid) {
    $cm = get_coursemodule_from_instance('forum', $communityforumid, $communitycourseid);
    if ($cm) {
        redirect(new moodle_url('/mod/forum/view.php', ['id' => $cm->id]));
    }
    redirect(new moodle_url('/course/view.php', ['id' => $communitycourseid]));
}

// Determine the discussion subject from the source activity or course.
$subject = '';
$sourcecourse = null;

if ($cmid) {
    $cm = get_coursemodule_from_id('', $cmid, 0, true);
    if ($cm) {
        $sourcecourse = $DB->get_record('course', ['id' => $cm->course]);
        $activityname = format_string($cm->name);
        $coursename = $sourcecourse ? format_string($sourcecourse->fullname) : '';
        $subject = $activityname;
        if ($coursename) {
            $subject .= ' — ' . $coursename;
        }
    }
} else if ($courseid) {
    $sourcecourse = $DB->get_record('course', ['id' => $courseid]);
    if ($sourcecourse) {
        $subject = format_string($sourcecourse->fullname);
    }
}

if (empty($subject)) {
    $subject = get_string('community', 'local_calendario');
}

// Look for an existing discussion with this subject in the community forum.
$discussion = $DB->get_record_select(
    'forum_discussions',
    'forum = ? AND name = ?',
    [$communityforumid, $subject],
    '*',
    IGNORE_MULTIPLE
);

if (!$discussion) {
    // Create the discussion automatically.
    $forumcm = get_coursemodule_from_instance('forum', $communityforumid, $communitycourseid);

    $discussionobj = new stdClass();
    $discussionobj->course = $communitycourseid;
    $discussionobj->forum = $communityforumid;
    $discussionobj->name = $subject;
    $discussionobj->message = get_string('community_thread_intro', 'local_calendario', $subject);
    $discussionobj->messageformat = FORMAT_HTML;
    $discussionobj->messagetrust = 0;
    $discussionobj->mailnow = 0;
    $discussionobj->groupid = -1;
    $discussionobj->attachments = null;
    $discussionobj->itemid = 0;

    $message = '';
    $discussionid = forum_add_discussion($discussionobj, null, $message, $USER->id);
    if ($discussionid) {
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
    }
}

// Redirect to the discussion.
if ($discussion) {
    redirect(new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id]));
}

// Fallback: redirect to forum index.
$cm = get_coursemodule_from_instance('forum', $communityforumid, $communitycourseid);
if ($cm) {
    redirect(new moodle_url('/mod/forum/view.php', ['id' => $cm->id]));
}
redirect(new moodle_url('/course/view.php', ['id' => $communitycourseid]));
