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
 * Calendario page - Google Calendar embed.
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('id', SITEID, PARAM_INT);

if ($courseid && $courseid != SITEID) {
    $course = get_course($courseid);
    require_login($course);
    $context = context_course::instance($course->id);
    $PAGE->set_course($course);
} else {
    require_login();
    $context = context_system::instance();
}

$PAGE->set_url(new moodle_url('/local/calendario/index.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('calendario', 'local_calendario'));
$PAGE->set_heading(get_string('calendario', 'local_calendario'));
$PAGE->set_pagelayout('incourse');

// Mark the Calendario tab as active in the secondary navigation.
if ($secondarynav = $PAGE->secondarynav) {
    $node = $secondarynav->get('local_calendario_nav');
    if ($node) {
        $node->make_active();
    }
}

echo $OUTPUT->header();

echo '<div style="display:flex; justify-content:center; padding: 1.5rem 0;">';
echo '<iframe src="https://calendar.google.com/calendar/embed?src=tatanganga.casareligiosa%40gmail.com&ctz=America%2FMazatlan" style="border: 0; border-radius: 12px;" width="100%" height="700" frameborder="0" scrolling="no"></iframe>';
echo '</div>';

echo $OUTPUT->footer();
