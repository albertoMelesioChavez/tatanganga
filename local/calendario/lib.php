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
 * Library functions for local_calendario.
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Navigation is handled via the hook callback in classes/hook_callbacks.php
// using the core\hook\navigation\secondary_extend hook.

/**
 * Filter out the global community course from course listings.
 *
 * @param array $courses Array of course records.
 * @return array Filtered course records.
 */
function local_calendario_filter_community_course(array $courses): array {
    $communitycourseid = (int) (get_config('local_calendario', 'communitycourseid') ?: 12);
    $communityshortname = (string) (get_config('local_calendario', 'communityshortname') ?: 'comunidad');
    $communityidnumber = (string) (get_config('local_calendario', 'communityidnumber') ?: '999');

    return array_filter($courses, static function($course) use (
        $communitycourseid,
        $communityshortname,
        $communityidnumber
    ) {
        if (!is_object($course)) {
            return true;
        }

        if (isset($course->id) && (int) $course->id === $communitycourseid) {
            return false;
        }

        if (isset($course->shortname) && (string) $course->shortname === $communityshortname) {
            return false;
        }

        if (isset($course->idnumber) && (string) $course->idnumber === $communityidnumber) {
            return false;
        }

        return true;
    });
}
