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
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '<div style="width:100%; overflow:hidden;">'
            . '<iframe src="https://calendar.google.com/calendar/embed?src=tatanganga.casareligiosa%40gmail.com&ctz=America%2FMazatlan"'
            . ' style="border:0; border-radius:8px; width:100%; height:600px;" frameborder="0" scrolling="no"></iframe>'
            . '</div>';
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
