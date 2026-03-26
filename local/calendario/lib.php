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
 * @param iterable $courses Iterable of course records.
 * @return array Filtered course records.
 */
function local_calendario_filter_community_course(iterable $courses): array {
    $communitycourseid = (int) (get_config('local_calendario', 'communitycourseid') ?: 12);
    $communityshortname = (string) (get_config('local_calendario', 'communityshortname') ?: 'comunidad');
    $communityidnumber = (string) (get_config('local_calendario', 'communityidnumber') ?: '999');

    $filteredcourses = [];
    foreach ($courses as $key => $course) {
        if (!is_object($course)) {
            $filteredcourses[$key] = $course;
            continue;
        }

        if (isset($course->id) && (int) $course->id === $communitycourseid) {
            continue;
        }

        if (isset($course->shortname) && (string) $course->shortname === $communityshortname) {
            continue;
        }

        if (isset($course->idnumber) && (string) $course->idnumber === $communityidnumber) {
            continue;
        }

        $filteredcourses[$key] = $course;
    }

    return $filteredcourses;
}
