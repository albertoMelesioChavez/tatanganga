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

namespace local_calendario;

use core\hook\navigation\secondary_extend;
use core\hook\output\before_standard_top_of_body_html_generation;
use navigation_node;
use moodle_url;
use context_course;

/**
 * Hook callbacks for local_calendario.
 *
 * @package    local_calendario
 * @copyright  2026 Tatanganga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add "Calendario" to the secondary navigation.
     *
     * @param secondary_extend $hook
     */
    public static function add_calendario_nav(secondary_extend $hook): void {
        // Disabled - calendar is now a dashboard block.
    }

    /**
     * Inject subscription banner for non-subscribers on course pages.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function inject_subscription_banner(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $USER, $DB, $COURSE;

        // Only on course pages (not site home).
        if (!isset($COURSE->id) || $COURSE->id <= 1) {
            return;
        }

        // Skip for admins.
        if (is_siteadmin()) {
            return;
        }

        // Skip for guests / not logged in.
        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Check if user has student_suscriptor role in this course.
        $suscriptorroleid = $DB->get_field('role', 'id', ['shortname' => 'student_suscriptor']);
        if (!$suscriptorroleid) {
            return;
        }

        $context = context_course::instance($COURSE->id);
        $hassuscriptor = $DB->record_exists('role_assignments', [
            'roleid' => $suscriptorroleid,
            'userid' => $USER->id,
            'contextid' => $context->id,
        ]);

        if ($hassuscriptor) {
            return;
        }

        // Find the Stripe enrolment URL (course 5 = Mentalidad has stripe).
        $stripecourseid = $DB->get_field_sql(
            "SELECT courseid FROM {enrol} WHERE enrol = 'stripepayment' AND status = 0 LIMIT 1"
        );
        if ($stripecourseid) {
            $payurl = new moodle_url('/enrol/index.php', ['id' => $stripecourseid]);
        } else {
            $payurl = new moodle_url('/');
        }

        $html = '<div class="suscripcion-banner">'
            . '<div class="suscripcion-banner-content">'
            . '<div class="suscripcion-banner-icon">🔓</div>'
            . '<div class="suscripcion-banner-text">'
            . '<strong>Desbloquea todo el contenido</strong>'
            . '<span>Suscríbete por $100 MXN/mes y accede a todas las clases, grabaciones y recursos exclusivos.</span>'
            . '</div>'
            . '<a href="' . $payurl->out(false) . '" class="btn suscripcion-banner-btn">Suscribirme ahora</a>'
            . '</div>'
            . '</div>';

        $hook->add_html($html);
    }
}
