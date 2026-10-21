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
 * Google Calendar block.
 *
 * @package    block_gcalendar
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_gcalendar extends block_base {

    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('gcalendar', 'block_gcalendar');
    }

    /**
     * Return the content object.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $calendarid = 'tatanganga.casareligiosa@gmail.com';
        $embedparams = [
            'src' => $calendarid,
            'ctz' => 'America/Mazatlan',
            'hl' => 'es_419',
            'mode' => 'AGENDA',
            'showTitle' => 0,
            'showNav' => 1,
            'showDate' => 1,
            'showPrint' => 0,
            'showTabs' => 0,
            'showCalendars' => 0,
            'showTz' => 0,
        ];
        $embedurl = new moodle_url('https://calendar.google.com/calendar/embed', $embedparams);
        $calendarurl = new moodle_url('https://calendar.google.com/calendar/render', ['cid' => $calendarid]);

        $intro = html_writer::start_div('gcalendar-intro');
        $intro .= html_writer::div(
            $OUTPUT->pix_icon('i/calendar', '', 'core', ['class' => 'gcalendar-intro-icon']),
            'gcalendar-icon-wrap',
            ['aria-hidden' => 'true']
        );
        $intro .= html_writer::start_div('gcalendar-intro-copy');
        $intro .= html_writer::tag('h3', get_string('upcomingsessions', 'block_gcalendar'));
        $intro .= html_writer::tag('p', get_string('intro', 'block_gcalendar'));
        $intro .= html_writer::end_div();
        $intro .= html_writer::end_div();

        $iframe = html_writer::tag('iframe', '', [
            'src' => $embedurl->out(false),
            'title' => get_string('frametitle', 'block_gcalendar'),
            'loading' => 'lazy',
            'frameborder' => '0',
            'scrolling' => 'no',
        ]);

        $footer = html_writer::start_div('gcalendar-actions');
        $footer .= html_writer::link(
            $calendarurl,
            get_string('opencalendar', 'block_gcalendar'),
            [
                'class' => 'gcalendar-open-link',
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]
        );
        $footer .= html_writer::tag('span', get_string('timezone', 'block_gcalendar'), [
            'class' => 'gcalendar-timezone',
        ]);
        $footer .= html_writer::end_div();

        $this->content = new stdClass();
        $this->content->text = html_writer::div(
            $intro . html_writer::div($iframe, 'gcalendar-frame-wrap') . $footer,
            'gcalendar-shell'
        );
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Allow multiple instances of this block.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Where this block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'my' => true,
            'site-index' => true,
            'course-view' => true,
            'all' => false,
        ];
    }

    /**
     * Has config.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }
}
