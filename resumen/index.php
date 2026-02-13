<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3 of the License, or
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
 * Public resumen page.
 */

require_once(__DIR__ . '/../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/resumen/index.php'));
$PAGE->set_title('Resumen del proyecto');
$PAGE->set_heading('Resumen del proyecto');
$PAGE->set_pagelayout('standard');

$summarypath = $CFG->dirroot . '/RESUMEN_PROYECTO.md';
$content = '';
if (file_exists($summarypath)) {
    $content = file_get_contents($summarypath);
}

$renderer = $PAGE->get_renderer('core');

echo $OUTPUT->header();

echo html_writer::start_div('container');

echo html_writer::tag(
    'div',
    format_text($content, FORMAT_MARKDOWN, ['context' => $context]),
    ['class' => 'resumen-content']
);

echo html_writer::end_div();

echo $OUTPUT->footer();
